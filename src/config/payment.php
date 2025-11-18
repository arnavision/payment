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
            'base_url' => env('TARA_URL'),
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
        'apsan' => [
            'username' => env('APSAN_USERNAME'),
            'password' => env('APSAN_PASSWORD'),
            'terminal' => env('APSAN_TERMINAL'),
        ],
        'sadad' => [
            'merchant_config_id' => env('SADAD_MERCHANT_CONFIG_ID'),
            'merchant_id'        => env('SADAD_MERCHANT_ID'),
            'terminal_id'        => env('SADAD_TERMINAL_ID'),
        ],

        'top' => [
            'username' => env('TOP_USERNAME'),
            'password' => env('TOP_PASSWORD'),
        ],



        'saipay' => [
            'username' => env('SAIPAY_USERNAME'),
            'password' => env('SAIPAY_PASSWORD'),
        ],



        'snapp' => [
            'url_base' => env('SNAPP_URL'),
            'username' => env('SNAPP_USERNAME'),
            'password' => env('SNAPP_PASSWORD'),
            'client_id' => env('SNAPP_CLIENT_ID'),
            'client_secret' => env('SNAPP_CLIENT_SECRET'),

        ],

        'keepa' => [
            'base_url' => env('KEEPA_BASE_URL', 'https://api.kipaa.ir'),
            'token' => env('KEEPA_TOKEN'),
        ],




    ],
];
