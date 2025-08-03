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


    public function get_token($amount, $orderId, $mobile, $invoiceItems)
    {
        try {

            $authResult = $this->getTokenAuth();

            $token = $authResult['accessToken'] ?? null;

            if (!$token) {
                abort(500, 'Failed to get authentication token');
            }

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


            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post($this->URL_TOKEN, $requestData);

            if ($response->failed()) {
                abort(500, 'Payment gateway request failed');
            }

            $responseData = $response->json();

            return $responseData['token'] ?? null;

        } catch (Exception $e) {
            Log::error('Payment error: ' . $e->getMessage());
            throw $e;
        }
    }


    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'tara', $extra);


        try {
            // دریافت توکن احراز هویت
            $token = $this->get_token($amount, $payment_id, $extra['mobile'] ?? '');

            // آماده سازی داده‌های درخواست
            $paymentData = [
                'username' => $this->config['username'],
                'token' => $token,
                'amount' => $amount,
                'order_id' => $payment_id,
                'callback_url' => $callback,
                'mobile' => $extra['mobile'] ?? null,
            ];

            // ارسال درخواست به درگاه تارا
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getTokenAuth()
            ])->post( $this->URL_PURCHASE, $paymentData);


            PaymentGatewayLog::pay_log($payment_id, $response->body());

            if ($response->successful() && $response['status'] == 'success') {
                return $this->redirectWithForm($response['payment_url']);
            }
            else{
                abort(500, ($response['message'] ?? 'Unknown error') );
            }

            throw new \Exception('Payment request failed: ' . ($response['message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }
    }

    public function verify()
    {

        // آماده سازی داده‌های درخواست
        $verifyData = [
            'ip' => $this->getIp(),
            'token' => $this->token,
        ];

        // ارسال درخواست وریفای به درگاه تارا
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token
        ])->post($this->baseUrl . '/purchaseVerify', $verifyData);


        $result = $response->json();


        PaymentGatewayLog::verifyLog(
            $this->payment_id,
            $response->body()
        );


        // ذخیره اطلاعات تراکنش
        if ($response->successful() && isset($result['status']) && $result['status'] === 'success') {
            return [
                'status'  => 1,
                'message' => 'Confirm is ok',
            ];
        }
        else{
            return [
                'status'  => 0,
                'message' => 'Confirm is not ok',
            ];
        }

    }


    public function settle()
    {
        PaymentGatewayLog::settle_log($this->payment_id, 'tara has not settle',1);
        return [
            'status' => 1
        ];
    }


}
