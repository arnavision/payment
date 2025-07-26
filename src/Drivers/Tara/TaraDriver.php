<?php

namespace Arnavision\PaymentGateway\Drivers\Tara;


use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use \Arnavision\PaymentGateway\Abstracts\Driver;
use yii\easyii\modules\paymentmethod\api\Payment;
use yii\easyii\modules\shopcart\api\Shopcart;

class TaraDriver extends Driver
{
    protected $config;
    protected $ref_num;

    private $payment_id;
    private $trace_number;
    private $state;
    protected $URL_AUTH;
    protected $URL_TOKEN;
    protected $URL_PURCHASE;
    protected $URL_VERIFY;
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

    public function __construct()
    {
        $this->config = config('payment.gateways.tara');
        $this->URL_AUTH = $this->config['api_url'] . '/v2/authenticate';
        $this->URL_TOKEN = $this->config['api_url'] . '/getToken';
        $this->URL_PURCHASE = $this->config['api_url'] . '/ipgPurchase';
        $this->URL_VERIFY = $this->config['api_url'] . '/purchaseVerify';
    }


    public function getTokenAuth()
    {
        $url =  $this->URL_AUTH ;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'username' => $this->config['username'],
            'password' => $this->config['password'], // اصلاح شد: باید password باشد نه username
        ]);
        if ($response->failed()) {
            return [
                'error' => 'Failed to authenticate',
                'status' => $response->status(),
            ];
        }

        return $response->json();
    }


    public function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }


    public function get_token($amount, $orderId, $mobile)
    {
        try {
            // دریافت توکن احراز هویت
            $authResult = $this->getTokenAuth();
            dd($authResult);
            Payment::logResult($orderId, $authResult);

            $token = $authResult['accessToken'] ?? null;

            if (!$token) {
                throw new Exception('Failed to get authentication token');
            }

            Payment::setTokenTara($orderId, $token);

            // دریافت اطلاعات کالاها
            $goods = Shopcart::getGoods();

            if (!$goods || $goods->isEmpty()) {
                throw new Exception('Cart is empty');
            }

            // آماده سازی آیتم های فاکتور
            $invoiceItems = $goods->map(function ($good) use ($orderId) {
                return [
                    'name' => $good->item->title,
                    'code' => $good->item->code,
                    'count' => $good->count,
                    'unit' => 9,
                    'fee' => $good->price,
                    'group' => 2,
                    'groupTitle' => 'آجیل و خشکبار',
                    'data' => 'test',
                    'orderid' => $orderId
                ];
            })->toArray();

            // آماده سازی داده های درخواست
            $requestData = [
                'additionalData' => $orderId,
                'callBackUrl' => $this->callbackUrl,
                'vat' => '0',
                'amount' => $amount,
                'mobile' => $mobile,
                'orderid' => $orderId,
                'ip' => request()->ip(),
                'serviceAmountList' => [
                    [
                        'serviceId' => $this->config['service_id'],
                        'amount' => $amount
                    ]
                ],
                'taraInvoiceItemList' => $invoiceItems
            ];

            // ارسال درخواست به درگاه پرداخت
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post($this->URL_TOKEN);

            if ($response->failed()) {
                Payment::logResult($orderId, 'Error: ' . $response->status());
                throw new Exception('Payment gateway request failed');
            }

            $responseData = $response->json();
            Payment::logResult($orderId, $responseData);

            return $responseData['token'] ?? null;

        } catch (Exception $e) {
            Log::error('Payment error: ' . $e->getMessage());
            throw $e;
        }
    }


    public function pay(int $amount, string $paymentId, string $callbackUrl, array $extra = []): RedirectionForm
    {
        // شروع لاگ تراکنش
//        PaymentGatewayLog::startLog($amount, $paymentId, $callbackUrl, 'tara', $extra);

        try {
            // دریافت توکن احراز هویت
            $token = $this->get_token($amount, $paymentId, $extra['mobile'] ?? '');

            // آماده سازی داده‌های درخواست
            $paymentData = [
                'username' => $this->config['username'],
                'token' => $token,
                'amount' => $amount,
                'order_id' => $paymentId,
                'callback_url' => $callbackUrl,
                'mobile' => $extra['mobile'] ?? null,
                // سایر پارامترهای مورد نیاز درگاه تارا
            ];

            // ارسال درخواست به درگاه تارا
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getTokenAuth()
            ])->post( $this->URL_PURCHASE, $paymentData);

            // ذخیره لاگ پاسخ
            PaymentGatewayLog::payLog($paymentId, $response->json());

            if ($response->successful() && $response['status'] == 'success') {
                // ریدایرکت به صفحه پرداخت بانک
                return $this->redirectWithForm($response['payment_url']);
            }

            throw new \Exception('Payment request failed: ' . ($response['message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            // ذخیره خطا در لاگ
            PaymentGatewayLog::errorLog($paymentId, $e->getMessage());
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }
    }

    public function verify()
    {
        try {
            // دریافت توکن احراز هویت از دیتابیس
            $tokenBearer = Payment::getTokenTara($orderId);

            if (!$tokenBearer) {
                throw new Exception('توکن پرداخت یافت نشد');
            }

            // آماده سازی داده‌های درخواست
            $verifyData = [
                'ip' => $ip,
                'token' => $token
            ];

            // ارسال درخواست وریفای به درگاه تارا
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $tokenBearer
            ])->post($this->baseUrl . '/purchaseVerify', $verifyData);

            // ذخیره لاگ پاسخ
            Payment::setLogResult($orderId, $response->body());

            $result = $response->json();

            // ذخیره اطلاعات تراکنش
            if ($response->successful() && isset($result['status']) && $result['status'] === 'success') {
                PaymentGatewayLog::verifyLog(
                    $orderId,
                    $response->body(),
                    $result['ref_num'] ?? null,
                    $result['trace_number'] ?? null
                );

                return [
                    'status' => true,
                    'message' => 'تراکنش با موفقیت تایید شد',
                    'data' => $result
                ];
            }

            throw new Exception($result['message'] ?? 'خطا در تایید تراکنش');

        } catch (Exception $e) {
            // ذخیره خطا در لاگ
            Payment::setLogResult($orderId, 'Verify Error: ' . $e->getMessage());

            return [
                'status' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }


    public function settle()
    {

        PaymentGatewayLog::settle_log($this->payment_id, 'melli has not settle',1);
        return [
            'status' => 1
        ];
    }

    public function setParamsCallback(Request $request)
    {

        $payment_id  = $request->post('OrderId');
        $Token       = $request->post('token');
        $ResCode     = $request->post('ResCode');

        $this->payment_id = $payment_id;
        $this->token      = $Token;

        if ($ResCode == "0"){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, $this->ref_num, $this->trace_number);
    }
}
