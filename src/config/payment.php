<?php

return [
    'default' => env('PAYMENT_DRIVER', 'mellat'),

    'gateways' => [
        'mellat' => [
            'terminalId' => env('MELLAT_TERMINAL_ID'),
            'username' => env('MELLAT_USERNAME'),
            'password' => env('MELLAT_PASSWORD'),
            'wsdl' => 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl',
        ],
        'melli' => [
            'api_url' => env('MELLI_URL'),
            'merchant_id' => env('MELLI_MERCHANT_ID'),
            'api_key' => env('MELLI_API_KEY'),
            'callback_url' => env('MELLI_CALLBACK_URL'),
        ],
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID', '00000000-0000-0000-0000-000000000000'),

            // IRR for Rial or IRT for Toman
            'currency' => env('ZARINPAL_CURRENCY', 'IRT'),
            'testing' => false,
        ],
        'tara' => [
            'api_url' => env('TARA_URL'),
            'username' => env('TARA_USERNAME'),
            'password' => env('TARA_PASSWORD'),
            'service_id' => env('TARA_SERVICE_ID')
        ],
        'parsian' => [
            'login_account' => env('PARSIAN_LOGIN_ACCOUNT'),

            'sale_url' => env(
                'PARSIAN_SALE_URL',
                'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx'
            ),

            'confirm_url' => env(
                'PARSIAN_CONFIRM_URL',
                'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx'
            ),

            'reverse_url' => env(
                'PARSIAN_REVERSE_URL',
                'https://pec.shaparak.ir/NewIPGServices/Reverse/ReversalService.asmx'
            ),

            'payment_url' => env(
                'PARSIAN_PAYMENT_URL',
                'https://pec.shaparak.ir/NewIPG'
            ),

            'timeout' => env('PARSIAN_TIMEOUT', 10),

            /*
             * کد رسمی پارسیان SSL_VERIFYPEER را 0 گذاشته.
             * برای production بهتر است true تست شود، اما برای سازگاری با مستندات فعلاً false.
             */
            'ssl_verify' => env('PARSIAN_SSL_VERIFY', false),
        ],
    ],
];
