<?php

namespace Arnavision\PaymentGateway\Drivers\Snapp;


use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use \Arnavision\PaymentGateway\Abstracts\Driver;
use yii\easyii\modules\paymentmethod\api\Payment;
use yii\easyii\modules\shopcart\api\Shopcart;

class SnappDriver extends Driver
{

    protected $config;
    protected $ref_num;

    private $payment_id;
    private $token;
    private $trace_number;
    private $state;
    private $amount;


    private $url_base;
    protected $url_eligible;
    protected $url_status;
    protected $url_token;
    protected $url_payment_token;
    protected $url_verify;
    protected $url_settle;
    protected $url_revert;
    protected $url_update;
    protected $url_cancel;




    private $username;
    private $password;
    private $client_id;
    private $client_secret;




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

        $this->config = config('payment.gateways.snapp');
        $this->url_base = $this->config['url_base'];

        $this->url_eligible = $this->url_base . '/api/online/offer/v1/eligible';
        $this->url_token = $this->url_base . '/api/online/v1/oauth/token';
        $this->url_payment_token = $this->url_base . '/api/online/payment/v1/token';
        $this->url_verify = $this->url_base . '/api/online/payment/v1/verify';
        $this->url_status = $this->url_base . '/api/online/payment/v1/status';
        $this->url_settle = $this->url_base . '/api/online/payment/v1/settle';
        $this->url_revert = $this->url_base . '/api/online/payment/v1/revert';
        $this->url_update = $this->url_base . '/api/online/payment/v1/update';
        $this->url_cancel = $this->url_base . '/api/online/payment/v1/cancel';


        $this->username  = $this->config['username'];
        $this->password  = $this->config['password'];
        $this->client_id = $this->config['client_id'];
        $this->client_secret = $this->config['client_secret'];

    }




    public function jwtToken(){

        $username = $this->username;
        $password = $this->password;
        $client_id = $this->client_id;
        $client_secret = $this->client_secret;


        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm() // استفاده از فرم برای ارسال داده‌ها
        ->post($this->url_verify, [
            'grant_type' => 'password',
            'scope' => 'online-merchant',
            'username' => $username,
            'password' => $password
        ]);

        $result = $response->json();

        if (!isset($result['access_token'])) {
            return null;
        }

        return $result['access_token'];

    }






    public function check_eligible($amount){

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . self::jwtToken(),
            ])->timeout(30)->get($this->url_eligible, [
                'amount' => $amount
            ]);

            $result = $response->json();

            if (isset($result['response']['eligible']) && $result['response']['eligible']) {

                return [
                    'status' => true,
                    'title'  => $result['response']['title_message'] ?? '',
                    'description' => $result['response']['description'] ?? '',
                    'error' => ''
                ];
            }

            else{
                return [
                    'status'  => false,
                    'message' => $result['message'] ?? null,
                    'error'   => $result['error_code'] ?? 1,
                    'title'   => '',
                    'description' => '',
                ];
            }
        }
        catch (\Exception $e){
            return [
                'status'  => false,
                'message' => $e->getMessage(),
                'error'   => $e->getCode(),
                'title'   => '',
                'description' => '',
            ];
        }

    }




    public function get_status(){


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . self::jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60) // تنظیم تایم‌اوت به 60 ثانیه
        ->get($this->url_status, [
            'paymentToken' => $this->token
        ]);

        if ($response->failed()) {

            $result = [
                'error' => $response->status(),
                'message'=> 'Request failed',
                'status' =>  -1,
                'transaction_id' =>  null,
                'amount' =>  null
            ];

            return $result;
        }


        $data = $response->json();


        $result = [
            'status' => $data['response']['status'] ?? -1,
            'transaction_id' => $data['response']['transactionId'] ?? null,
            'amount' => $data['response']['amount'] ?? null,
            'error'=>0,
            'message'=>''
        ];


        return $result;

    }







    public function cancel($paymentToken){

        $data = [
            'paymentToken' => $paymentToken
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60) // تنظیم تایم‌اوت به 60 ثانیه
        ->post($this->url_cancel, $data);


        if ($response->failed()) {

            $resultStatus = $this->get_status();

            if ($resultStatus['status'] == 'CANCEL'){
                return [
                    'status'  => true,
                    'message' => 'Operation Cancel Success'
                ];
            }
            else{
                return [
                    'status'  => false,
                    'message' => $resultStatus['message']
                ];
            }

        }


        $result = $response->json();


        if (isset($result['successful']) && $result['successful']){
            return [
                'status'  => true,
                'message' => 'Operation Cancel Success'
            ];
        }
        else{
            return [
                'status'  => false,
                'message' => $result['errorData']['message'] ?? ''
            ];
        }


    }






    public function update($paymentToken, $order_id ,$amount ,$discountAmount ,$shipping_price, $cartItems){


        $data = array(

            "amount"   => $amount,
            'cartList' => [
                [
                    'cartId'             => $order_id,
                    'cartItems'          => $cartItems,
                    'isShipmentIncluded' => true,
                    'isTaxIncluded'      => true,
                    "shippingAmount"     => $shipping_price,
                    "taxAmount"          => 0,
                    "totalAmount"        => $amount + $shipping_price,
                ]
            ],

            'paymentMethodTypeDto' => 'INSTALLMENT',
            'discountAmount'       => $discountAmount,
            'externalSourceAmount' => 0,
            'paymentToken'         => $paymentToken,

        );



        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_update, $data); // ارسال داده‌ها به صورت JSON


        if ($response->failed()) {

            $resultStatus = $this->get_status();

            if ($resultStatus['status'] == 'SETTLE'){

                if (isset($resultStatus['amount']) && $resultStatus['amount'] == $amount + $shipping_price){
                    return [
                        'status'  => true,
                        'message' => 'Operation Update Success'
                    ];
                }

                else{
                    return [
                        'status'  => false,
                        'message' => $resultStatus['message'] ?? ''
                    ];
                }

            }
            else{
                return [
                    'status'  => false,
                    'message' => $resultStatus['message'] ?? ''
                ];
            }

        }



        $result = $response->json();

        if (isset($result['successful']) && $result['successful']){

            return [
                'status'  => true,
                'message' => 'Operation Update Success'
            ];
        }
        else{
            return [
                'status'  => false,
                'message' => $result['errorData']['message'] ?? ''
            ];
        }

    }






    public function revert(){

        $data = [
            'paymentToken' => $this->token
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_revert, $data);


        if ($response->failed()) {

            $resultStatus = $this->get_status();

            PaymentGatewayLog::refund_log($this->payment_id, json_encode($resultStatus) );

            if ($resultStatus['status'] == 'REVERT'){

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

        $result = $response->json();

        PaymentGatewayLog::refund_log($this->payment_id, $response->body() );

        if (isset($result['successful']) && $result['successful']){
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







    public function setParamsCallback(Request $request)
    {

        $state         = $request->post('state');
        $amount        = $request->post('amount');
        $payment_id    = $request->post('transactionId');

        $this->payment_id = $payment_id;
        $this->amount     = $amount;

        $payment = PaymentGatewayLog::get_log($payment_id);

        $this->token = $payment->token;

        if ($state == 'OK'){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, $this->ref_num, $this->trace_number);
    }








    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'tara', $extra);

        try {

            $items = $extra['invoiceItems'] ?? null;

            if (!$items){
                abort(500,'invoiceItems empty' );
            }


            $cartItems = [];
            foreach ($items as $item){

                $cartItems [] = [
                    'amount'         => $item['fee']??null,
                    'category'       => $item['category']??null,
                    'count'          => $item['count']??null,
                    'id'             => $item['product_id']??null,
                    'name'           => $item['name']??null,
                    'commissionType' => '100',
                ];

            }


            $total_amount = $amount;

            $mobile = substr($extra['mobile'], 1);
            $mobile = '+98' . $mobile;

            $data = array(

                "amount"   => $amount,
                'cartList' => [
                    [
                        'cartId'             => $payment_id,
                        'cartItems'          => $cartItems,
                        'isShipmentIncluded' => true,
                        'isTaxIncluded'      => true,
                        "shippingAmount"     => $extra['shipping_price'] ?? 0,
                        "taxAmount"          => 0,
                        "totalAmount"        => $total_amount,
                    ]
                ],

                'mobile'               => $mobile,
                'paymentMethodTypeDto' => 'INSTALLMENT',
                'returnURL'            => $callback,
                'transactionId'        => $payment_id,
                'discountAmount'       => 0,
                'externalSourceAmount' => 0,

            );


            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->jwtToken(),
                'Content-Type' => 'application/json',
            ])->post($this->url_payment_token, $data);


            if ($response->failed()) {
                abort(500);
            }


            $result = $response->json();
            PaymentGatewayLog::pay_log($payment_id, $response->body());

            if (isset($result['response']['paymentPageUrl'])){

                $token = $result['response']['paymentToken'] ?? '';
                $pageUrl = $result['response']['paymentPageUrl'];

                PaymentGatewayLog::set_token($payment_id, $token);

                return $this->redirectWithForm($pageUrl, []);

            }

            abort(500, 'خطا در اتصال به درگاه پرداخت');

        } catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

    }




    public function verify()
    {

        $data = [
            'paymentToken' => $this->token
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_verify, $data);


        if ($response->failed()) {

            $resultStatus = $this->get_status();

            PaymentGatewayLog::verify_log($this->payment_id, json_encode($resultStatus), $this->ref_num, $this->trace_number);

            if ($resultStatus['status'] == 'VERIFY'){
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


        $result = $response->json();

        PaymentGatewayLog::verify_log($this->payment_id, $response->body(), $this->ref_num, $this->trace_number);

        if (isset($result['successful']) && $result['successful']){

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










    public function settle(){

        $data = [
            'paymentToken' => $this->token
        ];


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_settle, $data); // ارسال داده‌ها به صورت JSON



        if ($response->failed()) {

            $resultStatus = $this->get_status();

            if ($resultStatus['status'] == 'SETTLE'){
                PaymentGatewayLog::settle_log($this->payment_id, json_encode($resultStatus),1);
                return [
                    'status'  => 1
                ];
            }
            else{

                PaymentGatewayLog::settle_log($this->payment_id, json_encode($resultStatus),0);
                return [
                    'status'  => 0
                ];
            }

        }


        $result = $response->json();


        if (isset($result['successful']) && $result['successful']){

            PaymentGatewayLog::settle_log($this->payment_id, $response->body(),1);

            return [
                'status' => 1
            ];
        }
        else{

            PaymentGatewayLog::settle_log($this->payment_id, $response->body(),0);

            return [
                'status' => 0
            ];
        }



    }




}
