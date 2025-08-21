<?php


namespace Arnavision\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;
class PaymentGatewayLog extends Model
{

    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'payment_gateway_log';



    const STATUS_PAY = 1;
    const STATUS_NOT_PAY = 0;



    public static function start_log(int $amount, string $payment_id, string $callback, string $bank, array $data = [])
    {

        if (PaymentGatewayLog::where('payment_id', $payment_id)->exists()) {
            abort(409, 'Duplicate Payment ID!');
        }

        $log = new self();
        $log->amount      = $amount;
        $log->payment_id  = $payment_id;
        $log->callback    = $callback;
        $log->bank        = $bank;
        $log->data        = json_encode($data);
        if (Auth::check()) {
            $log->customer_id = Auth::id();
        }
        $log->ip = request()->ip();
        $log->status = 0;
        $log->save();
    }





    public static function pay_log(string $payment_id, string $response_pay)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->response_pay  = $response_pay;
        $log->save();
    }





    public static function callback_log(string $payment_id, string $response_callback, $state, $ref_num, $trace_num)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->response_callback  = $response_callback;

        $log->state        = $state;
        $log->ref_num      = $ref_num;
        $log->trace_number = $trace_num;
        $log->save();
    }





    public static function get_log(string $payment_id)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id, 'status' => 0])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        return $log;
    }







    public static function verify_log(string $payment_id, string $response_verify, $ref_num, $trace_number)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->response_verify  = $response_verify;
        $log->ref_num          = $ref_num;
        $log->trace_number     = $trace_number;
        $log->save();
    }




    public static function settle_log(string $payment_id, string $response_settle, int $status)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->response_settle  = $response_settle;
        $log->status  = $status;
        $log->save();
    }



    public static function refund_log(string $payment_id, string $response_refund)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id'=>$payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->response_refund  = $response_refund;
        $log->save();
    }





    public static function set_token(string $payment_id, string $token)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id' => $payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->token  = $token;
        $log->save();
    }





    public static function set_transaction_id(string $payment_id, string $transaction_id)
    {
        $log = PaymentGatewayLog::query()->where(['payment_id' => $payment_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        $log->transaction_id  = $transaction_id;
        $log->save();

    }





    public static function get_transaction_id(string $transaction_id)
    {
        $log = PaymentGatewayLog::query()->where(['transaction_id' => $transaction_id])->first();

        if (!$log){
            abort(500, 'payment ID dont exit');
        }

        return $log;

    }






}

