<?php

return [
    'base' => env('MARKETPLACE_BASE_CURRENCY', 'HNL'),

    'supported' => [
        'HNL' => [
            'name' => 'Honduran Lempira',
            'rate_to_base' => '1',
        ],
        'USD' => [
            'name' => 'US Dollar',
            'rate_to_base' => env('USD_TO_BASE_CURRENCY_RATE', '24.70'),
        ],
    ],
];
