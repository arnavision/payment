<?php

namespace Arnavision\PaymentGateway;

use Arnavision\PaymentGateway\Facades\PaymentGateway;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{

    public function boot()
    {
        // فقط در صورت وجود فایل config منتشرش کن
        if (file_exists(__DIR__ . '/config/payment.php')) {
            $this->publishes([
                __DIR__ . '/config/payment.php' => config_path('payment.php'),
            ], 'payment-config');
        }



        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // یا برای publish کردن migrations:
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'payment-migrations');


    }
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/payment.php',
            'payment'
        );

        $this->app->singleton('payment-gateway', function () {
            return new \Arnavision\PaymentGateway\PaymentManager();
        });
    }
}
