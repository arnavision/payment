<?php

namespace Arnavision\PaymentGateway\Abstracts;

use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\RedirectionForm;
use Illuminate\Http\Request;

abstract class Driver implements GatewayInterface
{

    public function redirectWithForm($action, array $inputs = [], $method = 'POST') : RedirectionForm
    {
        return new RedirectionForm($action, $inputs, $method);
    }
    /**
     * Purchase the invoice
     *
     * @return string
     */

    abstract public function setParamsCallback(Request $request);

    abstract public function getPaymentId();
    abstract public function getRefNum();
    abstract public function getState();
    abstract public function getTraceNum();

    /**
     * Pay the invoice
     */
    abstract public function pay(int $amount, string $payment_id, string $callback, array $extra = []): RedirectionForm;

    /**
     * Verify the payment
     */
    abstract public function verify();

    abstract public function settle();
}
