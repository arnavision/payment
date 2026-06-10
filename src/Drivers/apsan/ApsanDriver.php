<?php
namespace Arnavision\PaymentGateway\Drivers\Apsan;


use Arnavision\PaymentGateway\Abstracts\Driver;
use Arnavision\PaymentGateway\Models\PaymentGatewayLog;
use Illuminate\Support\Facades\Log;
use legacy\models\ErrorCodingLog;
use Psy\Readline\Hoa\Exception;
use yii\easyii\modules\paymentmethod\api\Payment;
use yii\easyii\modules\paymentmethod\library\nusoap_client;
use yii\easyii\modules\shopcart\api\Shopcart;
use yii\helpers\Url;
use Illuminate\Support\Facades\Http;
use \Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;

/**
*
*/
class ApsanDriver extends Driver
{

    protected $config;

    protected $username;
    protected $password;
    protected $terminal;
    const URL          = 'https://pay.cpg.ir';
    const URL_TOKEN    = self::URL.'/api/v1/Token';
    const URL_STATUS    = self::URL.'/api/v1/transaction/status';
    const URL_ACKNOWLEDGE    = self::URL.'/api/v1/payment/acknowledge';
    const URL_ROLLBACK       = self::URL.'/api/v1/payment/rollback';
    const URL_PAYMENT        = self::URL.'/v1/payment';

    private $payment_id;
    private $trace_number;
    private $state;
    private $ref_num;
    private $token;



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
        $this->config   = config('payment.gateways.apsan');
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
        $this->terminal = $this->config['terminal'];
    }




    public function setParamsCallback(Request $request)
    {

        $payment_id   = $request->post('uniqueIdentifier');
        $status       = $request->post('status');
        $ref_num      = $request->post('grantId');
        $rrn          = $request->post('switchResponseRrn');


        $this->payment_id   = $payment_id;

        $this->ref_num      = $ref_num;
        $this->trace_number = $rrn;


        $payment = PaymentGatewayLog::get_log($payment_id);

        $this->token = $payment->token;

        if($status === "Success"){
            $this->state = true;
        }
        else{
            $this->state = false;
        }

        PaymentGatewayLog::callback_log($payment_id, json_encode($request->all()), $this->state, $this->ref_num, $this->trace_number);
    }


    public function refund(){

        $data = [
            'token' => $this->token,
        ];

        try {

            $response = Http::withOptions([
                'timeout' => 30,
            ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                ])
                ->post(self::URL_ROLLBACK, $data);


            PaymentGatewayLog::refund_log($this->payment_id, $response->body());


            if ($response->failed()) {
                return false;
            }

            $result = $response->json();

            if (isset($result['result']['success']) && $result['result']['success']) {
                return true;
            }

        }
        catch (\Exception $e) {

        }

        return false;

    }




    public function verify(){

        $data = [
            'token' => $this->token,
        ];

        try {
            $response = Http::withOptions([
                'timeout' => 30,
            ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                ])
                ->post(self::URL_ACKNOWLEDGE, $data);

            if ($response->failed()) {
                return false;
            }

            $result = $response->json();

            if (isset($result['result']['acknowledged']) && $result['result']['acknowledged']) {
                return true;
            }

        }
        catch (\Exception $e) {

        }

        $this->refund();

        return false;
    }






    public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409,'Duplicate Payment ID!' );
        }

        PaymentGatewayLog::start_log($amount, $payment_id, $callback, 'apsan', $extra);

        $data = [
            'amount' => $amount,
            "redirectUri" => $callback,
            'terminalId' => $this->terminal,
            'uniqueIdentifier' => $payment_id,
        ];

        try {
            $response = Http::withOptions([
                'timeout' => 30,
            ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                ])
                ->post(self::URL_TOKEN, $data);


            PaymentGatewayLog::pay_log($payment_id, $response->body());

            if ($response->successful()) {

                $result = $response->json();

                if ($result->result){
                    PaymentGatewayLog::set_token($payment_id, $result->result );
                    return $this->redirectWithForm(self::URL_PAYMENT, [ 'token' => $result->result ]);
                }

            }

            abort(500, 'خطا در اتصال به درگاه پرداخت');

        } catch (\Exception $e) {
            abort(500, 'خطا در اتصال به درگاه پرداخت');
        }

    }






    public function settle()
    {
        PaymentGatewayLog::settle_log($this->payment_id, 'apsan has not settle',1);
        return [
            'status' => 1
        ];
    }





}

?>
