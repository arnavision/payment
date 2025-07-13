<?php
namespace Arnavision\PaymentGateway\Contracts;

use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;

interface GatewayInterface
{
    public function pay(int $amount, string $payment_id, string $callback, array $extra = []):RedirectionForm;
    public function setParamsCallback(Request $request);
    public function getPaymentId();
    public function getRefNum();
    public function getTraceNum();
    public function getState();
    public function verify();
    public function settle();
}
