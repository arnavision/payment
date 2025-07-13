<?php

namespace Arnavision\PaymentGateway\Drivers\Zarinpal;

use App\Services\bank\zarinpal\Zarinpal;
use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use SoapClient;
use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Illuminate\Http\Request;
use \Arnavision\PaymentGateway\RedirectionForm;


class ZarinpalDriver extends Driver
{
    public $merchant_id;
    public $callback_url;
    public $testing;
    private $payment_id;
    private $ref_num;
    private $trace_number;
    private $_authority;
    private $_amount;
    private $state;
    private $_status;


    public function __construct()
    {
        $this->merchant_id = config('payment.gateways.zarinpal.merchant_id');
        $this->testing = config('payment.gateways.zarinpal.testing');
    }


    public function request($amount, $description, $email = null, $mobile = null, $setParamsCallback = [])
    {
        if (count($setParamsCallback) > 0) {
            $i = 0;
            foreach ($setParamsCallback as $name => $value) {
                if ($i == 0) {
                    $this->callback_url .= '?';
                } else {
                    $this->callback_url .= '&';
                }
                $this->callback_url .= $name . '=' . $value;
                $i++;
            }
        }

        if ($this->testing) {
            $client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        } else {
            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        }
        $result = $client->PaymentRequest(
            [
                'MerchantID' => $this->merchant_id,
                'Amount' => $amount / 10,
                'Description' => $description,
                'Email' => $email,
                'Mobile' => $mobile,
                'CallbackURL' => $this->callback_url,
            ]
        );

        $this->_authority = $result->Authority;
        $this->_status    = $result->Status;

        return $this;
    }



    public function getPaymentId()
    {
        return $this->payment_id;
    }


    public function getRefNum()
    {
        return $this->ref_num;
    }


    public function getTraceNum()
    {
        return $this->trace_number;
    }


    public function getState()
    {
        return $this->state;
    }




    public function setParamsCallback(Request $request)
    {

        $Status    = $request->get('Status', null);
        $order_id  = $request->get('order_id', null);
        $Authority = $request->get('Authority', null);



        $this->trace_number = $Authority;
        $this->payment_id   = $order_id;

        $this->_authority = $Authority;


        $log = PaymentGatewayLog::get_log($this->payment_id);

        $this->_amount = $log->amount;


        if($Status == "OK"){
            $this->state = true;
        }
        else{
            $this->state = false;
        }


        PaymentGatewayLog::callback_log($order_id, json_encode($request->all()), $this->state, '', $this->trace_number);

    }




    public function verify()
    {
        if ($this->testing) {
            $client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        }
        else {
            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        }

        $result = $client->PaymentVerification(
            [
                'MerchantID' => $this->merchant_id,
                'Authority'  => $this->_authority,
                'Amount'     => $this->_amount / 10,
            ]
        );

        $this->ref_num = $result->RefID;

        PaymentGatewayLog::verify_log($this->payment_id, json_encode(['status' => $result->Status, 'refId' => $result->RefID ]), $this->ref_num, $this->trace_number);

        if( $result->Status == '100'){
            return [
                'status'    => 1,
                'message'   => 'payment done',
            ];
        }
        elseif($result->Status == '101') {
            return [
                'status'    => 2,
                'message'   => 'payment done',
            ];
        }
        else{
            return [
                'status'    => 0,
                'message'   => 'Confirm is not ok',
            ];
        }

    }




    public function getRedirectUrl($zaringate = true)
    {
        if ($this->testing) {
            $url = 'https://sandbox.zarinpal.com/pg/StartPay/' . $this->_authority;
        } else {
            $url = 'https://www.zarinpal.com/pg/StartPay/' . $this->_authority;
        }
        $url .= ($zaringate) ? '/ZarinGate' : '';

        return $url;
    }




    public function pay($amount,  string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'zarinpal', $extra);


        $this->callback_url = $callback;

        /*
        * if you whant, you can pass $callbackParams as array to request method for additional params send to your callback url
        */

        $this->request($amount, 'order id : ' . $payment_id, $extra['email'] ?? '', $extra['mobile'] ?? '', ['order_id' => $payment_id]);

        PaymentGatewayLog::pay_log( $payment_id, $this->_status , $this->_authority);

        if ($this->_status == '100') {
            /*
            * You can save your payment request data to the database in here before rediract user
            * to get authority code you can use $zarinpal->getAuthority()
            */;
            return $this->redirectWithForm($this->getRedirectUrl(), [], 'GET');
        }
        else{
            abort(500);
        }

    }




    public function settle()
    {
        PaymentGatewayLog::settle_log($this->payment_id, 'zarinpal has not settle',1);
        return [
            'status'=>1
        ];
    }


}
