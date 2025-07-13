<?php

namespace Arnavision\PaymentGateway\Drivers\Melli;


use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;
use \Arnavision\PaymentGateway\Abstracts\Driver;


class MelliDriver extends Driver
{
    protected $config;
    protected $ref_num;

    private $payment_id;
    private $trace_number;
    private $state;
    private $token;


    public function __construct()
    {
        $this->config = config('payment.gateways.melli');
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



    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'melli', $extra);

        $data = [
            'TerminalId' => $this->config['terminalId'],
            'MerchantId' => $this->config['merchantId'],
            'Amount' => $amount,
            'SignData' => $this->encryptPkcs7("{$this->config['terminalId']};{$payment_id};{$amount}", $this->config['key']),
            'ReturnUrl' => $callback,
            'LocalDateTime' => now()->format('m/d/Y h:i:s a'),
            'OrderId' => $payment_id,
        ];

        $response = Http::post('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest', $data);

        PaymentGatewayLog::pay_log($payment_id, json_encode($response) );


        if ($response->successful() && $response['ResCode'] == 0) {
            return $this->redirectWithForm("https://sadad.shaparak.ir/VPG/Purchase?Token={$response['Token']}", ['Token'=>($response['Token'] ?? '')], 'GET');
        }
        else{
            abort(500);
        }

    }




    protected function encryptPkcs7($str, $key): string

    {
        $key = base64_decode($key);
        $ciphertext = OpenSSL_encrypt($str, "DES-EDE3", $key, OPENSSL_RAW_DATA);
        return base64_encode($ciphertext);
    }




    public function verify()
    {
        $Token = $this->token;
        $key   = $this->config['key'];

        $verifyData = array( 'Token' => $Token, 'SignData' => $this->encryptPkcs7($Token,$key) );

        $response = Http::post('https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify', $verifyData);

        if (!$response->successful()){
            return [
                'status'  => false,
                'message' => 'Confirm is not ok',
            ];
        }

        $result             = $response->json();
        $this->ref_num      = $result['RetrivalRefNo'];
        $this->trace_number = $result['SystemTraceNo'];


        PaymentGatewayLog::verify_log($this->payment_id, $response->body(), $this->ref_num, $this->trace_number);


        if ($result['ResCode'] != -1) {
            return   [
                'status' => true,
                'message' => 'Confirm is ok',
            ];
        }
        else {
            return [
                'status'  => false,
                'message' => 'Confirm is not ok',
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



}
