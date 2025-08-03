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

        'fadax' => [
            'api_url' => env('FADAX_URL'),
            'username' => env('FADAX_USERNAME'),
            'password' => env('FADAX_PASSWORD'),
            'token_bearer' => env('FADAX_TOKEN'),
        ],


    ],
];
