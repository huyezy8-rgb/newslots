<?php
return [
    'open' =>  env('FB_OPEN', true),
    'pixel_id' => env('FB_PIXEL_ID', '922057443063417'),
    'access_token' => env('FB_ACCESS_TOKEN', 'EAAMAsrj2cIcBOz3yLpqm7GeJYzj2m68zWvutCJjnf8LAfo2Cqi4FNZC8WNj8tv3nZBSRDSx1BRzFWdwFg2pG7ZCcvLnaEzEL3bnIu94HmZA1AMKZBJZBhkdmKSiRhWnYvVswtTSADn54AV38sGXzUZB4xeWdRiSSGIOllAPSZCZCm4b7xoJKNieQAiZA1KpjplCL3QxAZDZD'),
    'api_version' => env('FB_API_VERSION', 'v18.0'),
    'test_mode' => env('APP_DEBUG', false),
    'test_code' => env('FB_TEST_CODE', 'TEST1234'),

    // 标准事件映射
    'event_mapping' => [
        'page_view' => 'PageView',
        'product_view' => 'ViewContent',
        'add_to_cart' => 'AddToCart',
        'checkout' => 'InitiateCheckout',
        'purchase' => 'Purchase'
    ]
];