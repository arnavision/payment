<?php

namespace Arnavision\PaymentGateway\Drivers\Keepa;

use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KeepaDriver extends Driver
{
    protected array $config;

    protected ?string $ref_num = null;
    protected ?string $trace_number = null;
    protected ?bool $state = null;

    protected ?string $payment_id = null;
    protected ?string $token = null;

    protected string $base_url;
    protected string $url_payment_token;
    protected string $url_verify;
    protected string $url_status;
    protected string $url_settle;
    protected string $url_revert;
    protected $amount;

    public function __construct()
    {
        $this->config = config('payment.gateways.keepa', []);

        $this->base_url = rtrim($this->config['base_url'] ?? 'https://api.kipaa.ir', '/');

        $this->url_payment_token = $this->base_url . '/ipg/v2/supplier/request_payment_token';
        $this->url_verify = $this->base_url . '/ipg/v2/supplier/verify_transaction';
        $this->url_status = $this->base_url . '/ipg/v2/supplier/get_transaction';
        $this->url_settle = $this->base_url . '/ipg/v2/supplier/confirm_transaction';
        $this->url_revert = $this->base_url . '/vpos/v3/supplier/refund_transaction';
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

    protected function jwtToken(): string
    {
        $token = $this->config['token'] ?? null;

        if (!$token) {
            abort(500, 'Keepa token is not configured.');
        }

        return $token;
    }




    protected function appendPaymentIdToCallback(string $callback, string $paymentId): string
    {
        if (strpos($callback, 'payment_id=') !== false) {
            return $callback;
        }

        $separator = strpos($callback, '?') === false ? '?' : '&';

        return $callback . $separator . 'payment_id=' . urlencode($paymentId);
    }





    public function setParamsCallback(Request $request)
    {
        $paymentId = $request->input('payment_id');

        $paymentLog = PaymentGatewayLog::get_log($paymentId);

        $state = $request->input('state');

        $receiptNumber = $request->input('reciept_number');

        $paymentToken = $request->input('payment_token');


        if ( $state == 100 ){
            $this->state = true;
        }
        else{
            $this->state = false;
        }


        $this->payment_id   = $paymentId;
        $this->token        = $paymentToken;
        $this->ref_num      = $receiptNumber;
        $this->trace_number = null;


        $this->amount = $paymentLog->amount;


        PaymentGatewayLog::callback_log($paymentLog, json_encode($request->all()), $this->state, $this->ref_num, null);

    }


    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {
        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409, 'Duplicate Payment ID!');
        }

        $callbackUrl = $this->appendPaymentIdToCallback($callback, $payment_id);

        PaymentGatewayLog::start_log($amount, $payment_id, $callbackUrl, 'keepa', $extra);

        $mobile = $extra['mobile'] ?? null;

        if (!$mobile) {
            abort(400, 'Mobile is required for Keepa payments.');
        }

        $invoiceItems = $extra['invoiceItems'] ?? null;

        if (!$invoiceItems || !is_array($invoiceItems) || empty($invoiceItems)) {
            abort(400, 'invoiceItems is required for Keepa payments.');
        }



        $items = $invoiceItems;

        $cartItems = [];

        $cost_good_total = 0;
        foreach ($items as $item){

            $good_price = $item['fee'];

            $cost_good_total += ($good_price * $item['count']);


            if ($item['unit'] == 0){
                $UnitName = 'عدد';
                $UnitID   = 1;
            }
            else{
                $UnitName = $item['unit_weight'];
                $UnitID   = $item['unit'] == 1 ? 3 : 2 ;
            }

            $cartItems [] = [

                'ItemName' => $item['name'],
                'ItemCode' => $item['product_id'],

                "UnitName" => $UnitName,
                "UnitID"   => $UnitID,

                "UnitPrice" => $good_price,

                'Quantity' => $item['count'],
                'Amount' => ($good_price * $item['count']),
                "Discount"=> ($item['fee'] * $item['count'] * $item['discount'])/(100-$item['discount']),
                'VAT'=> 0,
            ];


        }



        $data = [
            "amount"   => $amount,
            "callback_url" => $callbackUrl,
            "mobile" => $mobile,
            "merchant_order_id" => $payment_id,
            "details" => json_encode([
                "ReceiptFormatVersion" => "2",
                "Currency" => "IRR",
                "ReceiptDetails" => [
                    "ReceiptNumber"      => null,
                    "ReceiptBarcode"     => null,
                    "ReceiptDateAndTime" => null,
                    "MerchantName" => "فروشگاه آجیل و خشکبار حاجی بادومی",
                    "BranchName"   => "تهران-میدان امام حسین",
                    "BranchCode"   => null,
                    "CashierName"  => null,
                    "PosCode"      => "1617944813"
                ],
                "Items" => $cartItems,
                "PaymentDetails" => [
                    "SubtotalAmount" => $cost_good_total,
                    "Discount" => $extra['discount_price'] ?? 0,
                    "Costs" => [
                        "Shipping" => $extra['shipping_price'] ?? 0,
                        "Other"    => 0
                    ],
                    "TotalAmount" => $amount
                ],
            ], JSON_UNESCAPED_UNICODE)
        ];



        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_payment_token, $data);

        PaymentGatewayLog::pay_log($payment_id, $response->body());

        if ($response->failed()) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

        $result = $this->decodeJsonResponse($response);

        $token = $result['Content']['payment_token'] ?? null;
        $paymentUrl = $result['Content']['payment_url'] ?? null;

        if (!$token || !$paymentUrl) {
            abort(500, 'دریافت توکن پرداخت کیپا با خطا مواجه شد');
        }

        PaymentGatewayLog::set_token($payment_id, $token);

        $formInputs = [
            'payment_token' => $token,
        ];

        return $this->redirectWithForm($paymentUrl, $formInputs, 'POST');
    }








    public function verify()
    {
        $payload =  [
            'payment_token' => $this->token,
            'reciept_number' => $this->ref_num,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_verify, $payload);

        $body = $response->body();

        if ($response->failed()) {
            PaymentGatewayLog::verify_log($this->payment_id, $body, $this->ref_num, $this->trace_number);

            return [
                'status' => 0,
                'message' => 'Confirm is not ok',
            ];
        }

        $result = $response->json();


        PaymentGatewayLog::verify_log($this->payment_id, $body, $this->ref_num, $this->trace_number);

        if ((isset($result['Status']) && $result['Status'] == 200)) {

            if ($result['Content']['Amount'] == $this->amount){
                return [
                    'status' => 1,
                    'message' => 'Confirm is ok',
                ];
            }

        }

        return [
            'status' => 0,
            'message' => $result['Message'] ?? 'Confirm is not ok',
        ];
    }





    public function get_status(){

        $data = [
            'payment_token' => $this->token,
            'reciept_number'=> $this->ref_num
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . self::jwtToken(),
        ])->asForm()
            ->timeout(60)
            ->post($this->url_status, $data);


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
            'status' => $data['Status'] ?? -1,
            'amount' => $data['Content']['Amount'] ?? null,
            'transaction_id' => $data['Content']['ConfirmTransactionNumber'] ?? null,
            'error'=>0,
            'message'=> $data['Message'] ?? null,
        ];


        return $result;

    }








    public function settle()
    {

        $payload =  [
            'payment_token' => $this->token,
            'reciept_number' => $this->ref_num,
        ];


        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtToken(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->url_settle, $payload);

        $body = $response->body();



        if ($response->failed()) {
            $statusResult = $this->get_status();

            if ($statusResult['status'] == 200 && $statusResult['transaction_id']){

                PaymentGatewayLog::settle_log($this->payment_id, $body, 1);

                $this->trace_number = $statusResult['transaction_id'];

                return [
                    'status' => 1
                ];
            }
            else{

                PaymentGatewayLog::settle_log($this->payment_id, $body, 0);

                return [
                    'status' => 0
                ];
            }



        }



        $result = $response->json();

        if (isset($result['Status']) && $result['Status'] == 200){

            PaymentGatewayLog::settle_log($this->payment_id, $body, 1);

            $this->trace_number = ($settle_result['transaction_id'] ?? null);

            return [
                'status' => 1
            ];

        }
        else{

            PaymentGatewayLog::settle_log($this->payment_id, $body, 0);

            return [
                'status' => 0
            ];

        }




    }



}


