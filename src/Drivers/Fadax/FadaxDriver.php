<?php

namespace Arnavision\PaymentGateway\Drivers\Fadax;


use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use \Arnavision\PaymentGateway\Abstracts\Driver;


class FadaxDriver extends Driver
{
    protected $config;
    protected $ref_num;

    private $payment_id;
    private $transaction_id;
    private $token_bearer;
    private $trace_number;
    private $state;
    protected $URL_ELIGIBLE;
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
        $this->config = config('payment.gateways.fadax');
        $this->URL_ELIGIBLE = $this->config['api_url'] . '/supplier/v1/eligible';
        $this->URL_TOKEN = $this->config['api_url'] . '/supplier/v1/payment-token';
        $this->URL_PURCHASE = $this->config['api_url'] . '/api/ipgPurchase';
        $this->URL_VERIFY = $this->config['api_url'] . '/supplier/v1/verify';
        $this->token_bearer = $this->config['token_bearer'];
    }



    public function getIp(){
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }





    public function setParamsCallback(Request $request)
    {

        $state           = $request->post('state');
        $trackingNumber  = $request->post('fadax_tracking_number');
        $ref_number      = $request->post('refId');
        $transactionId   = $request->post('transactionId');


        $payment = PaymentGatewayLog::get_transaction_id($transactionId);

        $this->payment_id = $payment->payment_id;

        $this->ref_num      = $ref_number;
        $this->trace_number = $trackingNumber;

        if ($state == 'ok'){
            $this->state = true;
        }
        else{
            $this->state = false;
        }


        PaymentGatewayLog::callback_log($this->payment_id, json_encode($request->all()), $this->state, $this->ref_num, $this->trace_number);

    }


    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'fadax', $extra);

        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization: Bearer ' . $this->token
            ])->post($this->URL_TOKEN, $extra);


            $result = $response->json();


            PaymentGatewayLog::pay_log($payment_id, $response->body());

            PaymentGatewayLog::set_transaction_id($payment_id, ($result['response']['transactionId'] ?? '') );


            if ($response->successful() && isset($result['response']['status']) && $result['response']['status'] == 1001){
                return $this->redirectWithForm($result['response']['paymentPageURL']);
            }
            else{
                abort(500, 'response payment page url invalid');
            }

        } catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت' . $e->getMessage());
        }

    }


    public function verify()
    {

        $data = [
            'paymentToken'  => $token,
        ];


        $json = json_encode($data);



        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '. $this->token_bearer
        );

        $ch = curl_init($this->URL_VERIFY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, -1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


        $response = curl_exec($ch);
        if(curl_errno($ch)) {
            Payment::setLogResult($order_id, 'Error Code:'.curl_errno($ch) .'get token');
            throw new \yii\base\Exception('payment failed');
        }

        curl_close($ch);


        $result_verify = json_decode($response);

        Payment::setLogResult($order_id, $response);


        return $result_verify;




        if(isset($result->response->status) && isset($result->response->transactionId) && $result->response->status == 'ok' && $result->response->transactionId == $transactionId){


            return ['status'=> 1 ,'redirect_url' => Url::to([$paymentMethod->app_success_url.'?id='.$order_id])];
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
