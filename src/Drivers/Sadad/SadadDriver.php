<?php

namespace Arnavision\PaymentGateway\Drivers\Sadad;


use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use yii\easyii\modules\paymentmethod\api\Payment;
use yii\easyii\modules\shopcart\api\Shopcart;





class SadadDriver extends Driver{

    const URL_GENERATE_KEY      = 'https://Bnpl.sadadpsp.ir/Bnpl/GenerateKey';
    Const URL_Verify            = 'https://sadad.shaparak.ir/api/v0/BnplAdvice/Verify';

    private $terminal_id;
    private $merchant_id;
    private $merchant_config_id;
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
        $this->config             = config('payment.gateways.sadad');
        $this->terminal_id        = $this->config['terminal_id'];
        $this->merchant_id        = $this->config['merchant_id'];
        $this->merchant_config_id = $this->config['merchant_config_id'];
    }



    public function setParamsCallback(Request $request)
    {

        $token            = $request->input('Token');
        $payment_id       = $request->input('OrderId');

        $this->payment_id   = $payment_id;
        $this->token        = $token;

        $payment = PaymentGatewayLog::get_log($payment_id);
        $this->callback_url = $payment->callback;

        if($payment_id && $token){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, null, null);
    }





    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'sadad', $extra);

        try {
            $callback .= strpos($callback, '?') === false? '?':'&';
            $response = $this->callAPI('POST',self::URL_GENERATE_KEY,[
                'MerchantId' => $this->merchant_id,
                'TerminalId' => $this->terminal_id,
                'Amount'     => $amount,
                'OrderId'    => $payment_id,
                'ReturnUrl'  => $callback,
                'ApplicationName'       => 'Bnpl',
                'PanAuthenticationType' => 0,
            ]);


            PaymentGatewayLog::pay_log($payment_id, json_encode($response));

            $result = json_decode($response['content'],true);

            if($result['ResponseCode'] === 0 || $result['ResponseCode'] === '0' ){
                $token = $result['BnplKey'] ?? '';
                PaymentGatewayLog::set_token($payment_id, $token);
                return $this->redirectWithForm('https://bnpl.sadadpsp.ir/Home?key=' . $token, []);
            }

            abort(500, 'خطا در اتصال به درگاه پرداخت');

        }
        catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

    }





    public function verify()
    {
        $key = $this->merchant_config_id;
        $token = $this->token;
        $callback_url = $this->callback_url;

        $key = base64_decode($key);
        $ciphertext = openssl_encrypt($token, 'des-ede3', $key, OPENSSL_RAW_DATA);
        $ciphertext = base64_encode($ciphertext);

        $response = $this->callAPI('POST',self::URL_Verify,[
            'SignData'  => $ciphertext,
            'Token'     => $token,
            'ReturnUrl' => $callback_url
        ]);

        $result = json_decode($response['content'],true);
        if(isset($result['ResCode']) && $result['ResCode'] == 0){
            return [
                'status'  => 1,
                'message' => 'Confirm is ok',
            ];
        }
        else
        {
            return [
                'status'  => 0,
                'message' => 'Confirm is not ok',
            ];
        }

    }




    public function settle()
    {
        PaymentGatewayLog::settle_log($this->payment_id, 'sadad has not settle',1);
        return [
            'status' => 1
        ];
    }




    protected function callAPI($method, $url, $data = false)
    {
        $curl = curl_init();
        $url = $url;
        switch ($method)
        {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            default:
                if ($data){
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        $result = curl_exec($curl);
        if (curl_errno($curl))
            return ['content' => curl_error($curl), 'code' => curl_errno($curl)];

        $httpcode = curl_getinfo($curl);
        curl_close($curl);

        return ['content' => $result,'code' => $httpcode['http_code']];
    }





}


