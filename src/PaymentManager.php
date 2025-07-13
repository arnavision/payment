<?php

namespace Arnavision\PaymentGateway;

use Arnavision\PaymentGateway\Contracts\GatewayInterface;
use Arnavision\PaymentGateway\Drivers\Mellat\MellatDriver;
use Arnavision\PaymentGateway\Drivers\Zarinpal\ZarinpalDriver;
use Arnavision\PaymentGateway\Drivers\Melli\MelliDriver;
use Illuminate\Http\Response;
class PaymentManager
{
    protected $gateway;

    public function __construct($driver = null)
    {
        $driver = $driver ?: config('payment.default');
        $this->gateway = $this->resolve($driver);
    }

    public function resolve($driver): GatewayInterface
    {
        return match ($driver) {
            'mellat' => new MellatDriver(),
            'melli' => new MelliDriver(),
            'zarinpal'=> new ZarinpalDriver(),
            default => throw new \InvalidArgumentException("درگاه {$driver} پشتیبانی نمی‌شود."),
        };
    }

    public function gateway(): GatewayInterface
    {
        return $this->gateway;
    }
}
