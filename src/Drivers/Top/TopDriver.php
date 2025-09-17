<?php

namespace Arnavision\PaymentGateway\Drivers\Top;


use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use yii\easyii\modules\paymentmethod\api\Payment;
use yii\easyii\modules\shopcart\api\Shopcart;





class TopDriver extends Driver{


    const URL          = 'https://pay.top.ir/api/WPG';
    const URL_TOKEN    = self::URL.'/CreateOrder';

    const URL_VERIFY   = self::URL.'/ConfirmPurchase';
    const URL_REVERT   = self::URL.'/ReversePurchase';
    const URL_STATUS   = self::URL.'/GetOrderHistory';



    private $username;
    private $password;



    private $trace_number;
    private $state;
    private $ref_num;

    private $callback_url;



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
        $this->config             = config('payment.gateways.top');
        $this->username           = $this->config['username'];
        $this->password           = $this->config['password'];
    }



    public function setParamsCallback(Request $request)
    {

        $token  = $request->input('token');

        $payment = PaymentGatewayLog::get_log_by_token($token);


        $this->payment_id   = $payment->payment_id;
        $this->token        = $token;


        $this->callback_url = $payment->callback;

        if($this->payment_id && $token){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($this->payment_id, json_encode($request->all()), $this->state, null, null);
    }





    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'top', $extra);


        try {

            $param = [
                'MerchantOrderId'   => $payment_id,
                'MerchantOrderDate' => now()->format('Y-m-d\TH:i:s'),
                'AdditionalData'    => '',
                'Amount'            => $amount,
                'CallBackUrl'       => $callback,
                'ReceptShowTime'    => 3,
                'walletCode'        => $this->username
            ];


            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::URL_TOKEN, $param);


            if ($response->failed()) {
                abort(500, 'خطا در اتصال به درگاه پرداخت');
            }


            PaymentGatewayLog::pay_log($payment_id, $response->body());

            $result = $response->json();

            if(isset($result['status']) && $result['status'] == 0 && isset($result['data']['serviceURL'])){
                PaymentGatewayLog::set_token($payment_id, $result['data']['token']);
                return $this->redirectWithForm($result['data']['serviceURL'], [], 'GET');
            }

            abort(500, 'خطا در اتصال به درگاه پرداخت');

        }
        catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

    }





    public function verify()
    {


        $data = [
            'token'               => $this->token,
            'MerchantOrderId'     => $this->payment_id,
            'transactionDateTime' => now()->format('Y-m-d\TH:i:s'),
            'additionalData'      => '',
        ];


        $response = Http::withOptions([
            'verify' => false,
        ])
            ->withBasicAuth($this->username, $this->password)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post(self::URL_VERIFY, $data);


        if ($response->failed()) {

            $this->revert();

            return [
                'status'  => 0,
                'message' => 'Confirm is not ok',
            ];

        }

        $result = $response->json();

        PaymentGatewayLog::verify_log($this->payment_id, $response->body(), null, null);

        if (isset($result['status']) && $result['status'] == 0){

            return [
                'status'  => 1,
                'message' => 'Confirm is ok',
            ];

        }
        else{

            $this->revert();

            return [
                'status'  => 0,
                'message' => 'Confirm is not ok',
            ];

        }


    }






    public function revert(){

        $data = [
            'token' => $this->token,
            'MerchantOrderId' => $this->payment_id,
            'transactionDateTime' => now()->format('Y-m-d\TH:i:s'),
            'NewMerchantOrderId'  => $this->payment_id . rand(1,9),
            'additionalData'      => '',
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])
            ->withBasicAuth($this->username, $this->password)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post(self::URL_REVERT, $data);


        if ($response->failed()) {

            return [
                'status'  => 0,
                'message' => ''
            ];

        }

        $result = $response->json();


        PaymentGatewayLog::refund_log($this->payment_id, $response->body());


        if (isset($result['status']) && $result['status'] == 0){
            return [
                'status'  => 1,
                'message' => 'Operation Revert Success'
            ];
        }
        else{
            return [
                'status'  => 0,
                'message' => ''
            ];
        }

    }








    public function settle()
    {
        PaymentGatewayLog::settle_log($this->payment_id, 'top has not settle',1);
        return [
            'status' => 1
        ];
    }



}


