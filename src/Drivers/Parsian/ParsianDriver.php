<?php

namespace Arnavision\PaymentGateway\Drivers\Parsian;

use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use Throwable;

class ParsianDriver extends Driver implements GatewayInterface
{
    protected array $config;

    protected ?string $payment_id = null;
    protected ?string $trace_number = null;
    protected ?string $ref_num = null;
    protected ?string $token = null;
    protected ?string $callback_amount = null;

    protected bool $state = false;
    protected int $amount = 0;

    protected ParsianClient $client;

    public function __construct()
    {
        $this->config = config('payment.gateways.parsian');

        $this->client = new ParsianClient(
            loginAccount: (string) $this->config['login_account'],
            saleUrl: (string) $this->config['sale_url'],
            confirmUrl: (string) $this->config['confirm_url'],
            reverseUrl: (string) $this->config['reverse_url'],
            timeout: (int) ($this->config['timeout'] ?? 10),
            sslVerify: (bool) ($this->config['ssl_verify'] ?? false)
        );
    }

    public function getPaymentId()
    {
        return $this->payment_id;
    }

    public function getTraceNum()
    {
        return $this->trace_number;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getRefNum()
    {
        return $this->ref_num;
    }

    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {
        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409, 'Duplicate Payment ID!');
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'parsian', $extra);

        try {
            $result = $this->client->salePaymentRequest(
                orderId: $payment_id,
                amount: $amount,
                callbackUrl: $callback,
                additionalData: $extra['description'] ?? '',
                originator: $extra['mobile'] ?? $extra['phone'] ?? ''
            );

            PaymentGatewayLog::pay_log(
                $payment_id,
                json_encode($result, JSON_UNESCAPED_UNICODE),
                $result['token'] ?? null
            );

            if (
                ParsianStatus::isSuccessful($result['status'] ?? null)
                && !empty($result['token'])
            ) {
                $this->token = (string) $result['token'];

                return $this->redirectWithForm(
                    (string) $this->config['payment_url'],
                    ['token'=>$this->token],
                    'GET'
                );
            }

            abort(
                500,
                ParsianStatus::message($result['status'] ?? null, $result['message'] ?? null)
            );
        } catch (Throwable $e) {
            PaymentGatewayLog::pay_log(
                $payment_id,
                json_encode([
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE)
            );

            abort(500, 'Parsian payment request failed: ' . $e->getMessage());
        }
    }

    public function setParamsCallback(Request $request)
    {
        $this->payment_id = (string) $request->post('OrderId');
        $this->token = $request->post('Token') ? (string) $request->post('Token') : null;
        $this->callback_amount = $request->post('Amount') ? (string) $request->post('Amount') : null;
        $this->ref_num = $request->post('RRN') ? (string) $request->post('RRN') : null;

        $status = $request->post('status');

        $this->trace_number = $this->ref_num ?: $this->token;

        /*
         * طبق کد رسمی، فقط اگر status === "0" و Token وجود داشت وارد verify می‌شویم.
         */
        $this->state = ((string) $status === '0' && !empty($this->token));

        $log = PaymentGatewayLog::get_log($this->payment_id);
        $this->amount = (int) $log->amount;

        PaymentGatewayLog::callback_log(
            $this->payment_id,
            json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            $this->state ? 1 : 0,
            $this->ref_num,
            $this->trace_number
        );
    }

    public function verify()
    {
        if (!$this->state) {
            return [
                'status' => 0,
                'message' => 'Payment callback status is not successful.',
            ];
        }

        if (!$this->token || !$this->payment_id) {
            return [
                'status' => 0,
                'message' => 'Parsian callback parameters are incomplete.',
            ];
        }

        try {
            /*
             * طبق کد رسمی پارسیان، verify با ConfirmPaymentWithAmount انجام می‌شود
             * و Amount + OrderId هم باید ارسال شود.
             */
            $result = $this->client->confirmPaymentWithAmount(
                token: $this->token,
                amount: $this->callback_amount ?: $this->amount,
                orderId: $this->payment_id
            );

            $this->ref_num = !empty($result['RRN']) ? (string) $result['RRN'] : $this->ref_num;
            $this->trace_number = $this->ref_num ?: $this->token;

            PaymentGatewayLog::verify_log(
                $this->payment_id,
                json_encode($result, JSON_UNESCAPED_UNICODE),
                $this->ref_num,
                $this->trace_number
            );

            if (
                ParsianStatus::isSuccessful($result['status'] ?? null)
                && !empty($this->ref_num)
            ) {
                return [
                    'status' => 1,
                    'message' => 'payment done',
                    'ref_num' => $this->ref_num,
                    'trace_number' => $this->trace_number,
                    'card_number' => $result['card_number'] ?? null,
                ];
            }

            return [
                'status' => 0,
                'message' => ParsianStatus::message($result['status'] ?? null, $result['message'] ?? null),
                'code' => $result['status'] ?? null,
            ];
        } catch (Throwable $e) {
            PaymentGatewayLog::verify_log(
                $this->payment_id,
                json_encode([
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE),
                $this->ref_num,
                $this->trace_number
            );

            return [
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function settle()
    {
        /*
         * در مستندات ارسالی شما متد reverse وجود دارد، اما settle جداگانه وجود ندارد.
         * تایید نهایی همان ConfirmPaymentWithAmount است.
         */
        PaymentGatewayLog::settle_log(
            $this->payment_id,
            'Parsian payment confirmed by ConfirmPaymentWithAmount; no separate settle call.',
            1
        );

        return [
            'status' => 1,
            'message' => 'settled',
        ];
    }

    public function reverse(): bool
    {
        if (!$this->token) {
            return false;
        }

        return $this->client->reversalRequest($this->token);
    }
}
