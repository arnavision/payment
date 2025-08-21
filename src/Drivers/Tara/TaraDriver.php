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
    private $amount;
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
            'password' => $this->config['password'],
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

        $result           = $request->post('result');
        $token            = $request->post('token');
        $channelRefNumber = $request->post('channelRefNumber');
        $payment_id       = $request->post('additionalData');

        $this->payment_id   = $payment_id;
        $this->token        = $token;
        $this->ref_num      = $channelRefNumber;

        $paymentGatewayLog = PaymentGatewayLog::get_log($payment_id);
        $this->amount = $paymentGatewayLog->amount;



        if($result == 0){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, $this->ref_num, null);
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


    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'tara', $extra);

        try {

            $authResult = $this->getTokenAuth();

            $bearer_token = $authResult['accessToken'] ?? null;

            if (!$bearer_token) {
                abort(500, 'Failed to get authentication token');
            }

            $requestData = [
                'additionalData' => $payment_id,
                'callBackUrl' => $callback,
                'vat' => '0',
                'amount' => $amount,
                'mobile' => $extra['mobile'] ?? null,
                'orderid' => $payment_id,
                'ip' => $this->getIp(),
                'serviceAmountList' => [
                    [
                        'serviceId' => $this->config['service_id'],
                        'amount' => $amount
                    ]
                ],
                'taraInvoiceItemList' => $extra['invoiceItems']
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $bearer_token
            ])->post($this->URL_TOKEN, $requestData);


            PaymentGatewayLog::pay_log($payment_id, $response->body());

            if ($response->successful()) {

                $responseData = $response->json();

                if (isset($responseData['token'])){

                    // آماده سازی داده‌های درخواست
                    $paymentData = [
                        'username' => $this->config['username'],
                        'token' => $responseData['token'],
                    ];

                    return $this->redirectWithForm($this->URL_PURCHASE, $paymentData);

                }

            }

            abort(500, 'خطا در اتصال به درگاه پرداخت');

        } catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

    }




    public function verify()
    {

        $authResult = $this->getTokenAuth();

        $accessToken = $authResult['accessToken'] ?? null;

        if (!$accessToken){
            abort(500, 'access token dont exist');
        }

        // آماده سازی داده‌های درخواست
        $verifyData = [
            'ip' => $this->getIp(),
            'token' => $this->token,
        ];

        // ارسال درخواست وریفای به درگاه تارا
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ])->post($this->URL_VERIFY, $verifyData);


        $result = $response->json();

        PaymentGatewayLog::verify_log($this->payment_id, $response->body(), $this->ref_num, null);

        if(isset($result['result']) && isset($result['amount']) && $result['result'] == 0 && $result['amount'] == $this->amount){
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
