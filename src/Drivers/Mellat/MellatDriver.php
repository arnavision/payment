<?php
namespace Arnavision\PaymentGateway\Drivers\Mellat;


use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Log;
use SoapClient;
use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;

class MellatDriver extends Driver
{
    protected $config;
    protected $client;

    protected $payment_id;
    protected $ref_num;
    protected $state;
    protected $trace_number;

    public function __construct()
    {
        $this->config = config('payment.gateways.mellat');
        $this->client = new SoapClient($this->config['wsdl']);
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


        $payment_id      = $request->post('SaleOrderId');
        $saleReferenceId = $request->post('SaleReferenceId');
        $RefNum          = $request->post('RefId');
        $statusflag      = $request->post('statusflag');
        $FinalAmount     = $request->post('FinalAmount');
        $ResCode         = $request->post('ResCode');




        $this->ref_num      = $RefNum;
        $this->trace_number = $saleReferenceId;
        $this->payment_id   = $payment_id;

        $log = PaymentGatewayLog::get_log($payment_id);


        if ($ResCode == '0'){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        if($log->amount !=  $FinalAmount ){
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, $this->ref_num, $this->trace_number);

    }



    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'mellat', $extra);

        $params = [
            'terminalId' => $this->config['terminalId'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $payment_id,
            'amount' => $amount,
            'localDate' => now()->format('Ymd'),
            'localTime' => now()->format('His'),
            'callBackUrl' => $callback,
            'additionalData' => $extra['description'] ?? '',
            'payerId' => 0,
        ];

        $result = $this->client->bpPayRequest($params);
        $res = explode(',', $result->return);

        PaymentGatewayLog::pay_log($payment_id, json_encode($res) );

        if ($res[0] == '0') {
            return $this->redirectWithForm("https://bpm.shaparak.ir/pgwchannel/startpay.mellat", ['RefId'=>$res[1]], 'GET');
        }
        else{
            abort(500, json_encode($res));
        }

    }




    public function verify()
    {
        $params = [
            'terminalId' => $this->config['terminalId'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $this->payment_id,
            'saleOrderId' => $this->payment_id,
            'saleReferenceId' => $this->trace_number,
        ];

        $result = $this->client->bpVerifyRequest($params);

        PaymentGatewayLog::verify_log($this->payment_id, json_encode($result), $this->ref_num, $this->trace_number);


        if($result->return == '0'){
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
        $params = [
            'terminalId' => $this->config['terminalId'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $this->payment_id,
            'saleOrderId' => $this->payment_id,
            'saleReferenceId' => $this->trace_number,
        ];

        $result = $this->client->bpSettleRequest($params);


        if ($result->return == '0'){

            PaymentGatewayLog::settle_log($this->payment_id, json_encode($result), 1);

            return [
                'status' => 1,
            ];
        }

        else{
            PaymentGatewayLog::settle_log($this->payment_id, json_encode($result), 0);
            return [
                'status' => 0,
            ];
        }

    }



}
