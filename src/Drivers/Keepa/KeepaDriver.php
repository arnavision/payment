<?php

namespace Arnavision\PaymentGateway\Drivers\Keepa;

use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use Throwable;

class KeepaDriver extends Driver implements GatewayInterface
{
    protected array $config;

    protected KeepaClient $client;

    protected ?string $payment_id = null;
    protected ?string $trace_number = null;
    protected ?string $ref_num = null;
    protected ?string $token = null;

    protected bool $state = false;
    protected int $amount = 0;
    private $inquiry = null;
    public function __construct()
    {
        $this->config = config('payment.gateways.keepa', []);

        $this->client = new KeepaClient(
            baseUrl: (string) ($this->config['base_url'] ?? ''),
            clientId: (string) ($this->config['client_id'] ?? ''),
            clientSecret: (string) ($this->config['client_secret'] ?? ''),
            terminalId: $this->config['terminal_id'] ?? '',
            timeout: (int)($this->config['timeout'] ?? 30)
        );
    }

    public function setClient(KeepaClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getPaymentId()
    {
        return $this->payment_id;
    }

    public function getRefNum()
    {
        return $this->ref_num;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getTraceNum()
    {
        return $this->trace_number;
    }

    public function setParamsCallback(Request $request)
    {
        $this->token = $request->query('token') ?: $request->input('token');

        if (!$this->token) {
            $this->state = false;
            return;
        }

        $paymentLog = PaymentGatewayLog::get_log_by_token($this->token);

        $this->payment_id = (string) $paymentLog->payment_id;
        $this->amount = (int) $paymentLog->amount;
        $this->trace_number = $this->token;
        $this->ref_num = null;

        /*
         * در کیپا callback فقط یعنی کاربر از کیپا برگشته.
         * موفقیت قطعی فقط بعد از inquiry/verify مشخص می‌شود.
         */
        $this->state = true;

        PaymentGatewayLog::callback_log(
            $this->payment_id,
            json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            1,
            $this->ref_num,
            $this->trace_number
        );
    }

    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {
        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409, 'Duplicate Payment ID!');
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'keepa', $extra);

        try {
            $result = $this->client->getPaymentToken(
                invoiceNumber: $payment_id,
                amount: $amount,
                callbackUrl: $callback,
                payload: $extra['payload'] ?? null,
                items: $extra['items'] ?? $extra['invoiceItems'] ?? []
            );

            $token = $result['token'] ?? null;
            $paymentUrl = $result['paymentUrl'] ?? null;

            PaymentGatewayLog::pay_log(
                $payment_id,
                json_encode($result, JSON_UNESCAPED_UNICODE)
            );

            if (!$token || !$paymentUrl) {
                abort(500, 'دریافت توکن پرداخت کیپا با خطا مواجه شد.');
            }

            PaymentGatewayLog::set_token($payment_id, $token);

            return $this->redirectWithForm($paymentUrl, ['token'=>$token], 'GET');
        } catch (Throwable $e) {
            PaymentGatewayLog::pay_log(
                $payment_id,
                json_encode([
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE)
            );

            abort(500, 'Keepa payment request failed: ' . $e->getMessage());
        }
    }

    public function setParamsCallback(Request $request)
    {
        /*
         * طبق مستند کیپا callback با متد GET و فقط با token برمی‌گردد:
         * {callbackUrl}?token=...
         */
        $this->token = $request->query('token') ?: $request->input('token');

        $inquiry = $this->client->inquiry($this->token);
        $this->inquiry = $inquiry;
        $this->payment_id = $inquiry['invoice_number'] ?? null;
        if (!$this->token) {
            $this->state = false;
            return;
        }

        $log = PaymentGatewayLog::get_log($this->payment_id);

        if (!$log) {
            $this->state = false;
            return;
        }

        $this->payment_id = (string)$log->payment_id;
        $this->amount = (int)$log->amount;
        $this->trace_number = null;
        $this->ref_num = null;

        if (KeepaStatus::isVerified($inquiry['status']) || KeepaStatus::isWaitingToVerify($inquiry['status'])) {

            $this->state = true;
        } else {
            $this->state = false;
        }

        PaymentGatewayLog::callback_log(
            $this->payment_id,
            json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            1,
            $this->ref_num,
            $this->trace_number
        );
    }

    public function verify()
    {
        try {
            $inquiry = $this->client->inquiry($this->token);
            $paymentStatus = $inquiry['status'] ?? null;


            $paymentStatus = $this->inquiry['status'] ?? null;
            $log = PaymentGatewayLog::get_log($this->payment_id);
            if (KeepaStatus::isVerified($paymentStatus)) {
                $this->ref_num = $log->ref_num ?? null;
                $this->trace_number = $this->ref_num;

                PaymentGatewayLog::verify_log(
                    $this->payment_id,
                    json_encode([
                        'inquiry' => $this->inquiry,
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode(['inquiry' => $inquiry], JSON_UNESCAPED_UNICODE),
                    $this->ref_num,
                    $this->trace_number
                );

                return [
                    'status' => 1,
                    'message' => 'payment already verified',
                    'ref_num' => $this->ref_num,
                    'trace_number' => $this->trace_number,
                    'inquiry' => $this->inquiry,
                ];
            }

            if (!KeepaStatus::isWaitingToVerify($paymentStatus)) {
                PaymentGatewayLog::verify_log(
                    $this->payment_id,
                    json_encode([
                        'inquiry' => $this->inquiry,
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode(['inquiry' => $inquiry], JSON_UNESCAPED_UNICODE),
                    $this->ref_num,
                    $this->trace_number
                );

                return [
                    'status' => 0,
                    'message' => $this->inquiry['statusTitle'] ?? KeepaStatus::title($paymentStatus),
                    'code' => $paymentStatus,
                    'inquiry' => $this->inquiry,
                ];
            }

            $verify = $this->client->verify(
                token: $this->token,
                amount: $this->amount
            );

            $this->ref_num = $verify['refNum'] ?? null;
            $this->trace_number = $this->ref_num ;

            PaymentGatewayLog::verify_log(
                $this->payment_id,
                json_encode([
                    'inquiry' => $this->inquiry,
                    'verify' => $verify,
                ], JSON_UNESCAPED_UNICODE),
                $this->ref_num,
                $this->trace_number
            );

            if (!empty($verify['refNum'])) {
                return [
                    'status' => 1,
                    'message' => $verify['description'] ?? 'payment done',
                    'ref_num' => $this->ref_num,
                    'trace_number' => $this->trace_number,
                    'verify' => $verify,
                    'inquiry' => $this->inquiry,
                ];
            }

            return [
                'status' => 0,
                'message' => 'Keepa verify failed.',
                'verify' => $verify,
                'inquiry' => $this->inquiry,
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
        PaymentGatewayLog::settle_log(
            $this->payment_id,
            'Keepa payment verified by verify endpoint; no separate settle call.',
            1
        );

        return [
            'status' => 1,
            'message' => 'settled',
        ];
    }

    protected function findLogByToken(string $token)
    {
        /*
         * چون callback کیپا فقط token دارد، باید token پرداخت در pay_log ذخیره شده باشد.
         * این قسمت را اگر نام ستون لاگ پکیج شما متفاوت است، مطابق مدل PaymentGatewayLog اصلاح کن.
         */
        $query = PaymentGatewayLog::query();

        foreach (['token', 'payment_token', 'ref_num', 'trace_number'] as $column) {
            try {
                $found = (clone $query)->where($column, $token)->first();

                if ($found) {
                    return $found;
                }
            } catch (Throwable) {
                // اگر ستون وجود نداشت، رد می‌شویم.
            }
        }

        /*
         * fallback: جستجو داخل فیلد pay_response اگر وجود داشته باشد.
         */
        foreach (['pay_response', 'pay_log', 'pay_result', 'response'] as $column) {
            try {
                $found = PaymentGatewayLog::where($column, 'like', '%' . $token . '%')->first();

                if ($found) {
                    return $found;
                }
            } catch (Throwable) {
                // اگر ستون وجود نداشت، رد می‌شویم.
            }
        }

        return null;
    }

    public function findPayme()
    {

    }
}
