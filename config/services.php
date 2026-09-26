<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'yandex_delivery' => [
        'test_token' => env('YANDEX_DELIVERY_TEST_TOKEN') ?: implode('', [
            'y2_AgAAAA', 'D04omrAAAPe', 'AAAAAACRpC94', 'Qk6Z5rUTgOc', 'TgYFECJllXYKFx8',
        ]),
        'test_base_url' => env('YANDEX_DELIVERY_TEST_BASE_URL', 'https://b2b.taxi.tst.yandex.net'),
        'prod_base_url' => env('YANDEX_DELIVERY_BASE_URL', 'https://b2b.taxi.yandex.net'),
        'test_source_address' => env('YANDEX_DELIVERY_TEST_SOURCE', 'Москва, Ленинградский проспект 27'),
        'test_source_lon' => (float) env('YANDEX_DELIVERY_TEST_LON', 37.5835),
        'test_source_lat' => (float) env('YANDEX_DELIVERY_TEST_LAT', 55.7995),
        'fallback_to_test' => filter_var(env('YANDEX_DELIVERY_FALLBACK_TEST', true), FILTER_VALIDATE_BOOL),
        'platform_test_base_url' => env('YANDEX_DELIVERY_PLATFORM_TEST_URL', 'https://b2b.taxi.tst.yandex.net'),
        'platform_prod_base_url' => env('YANDEX_DELIVERY_PLATFORM_URL', 'https://b2b-authproxy.taxi.yandex.net'),
        'test_station_id' => env('YANDEX_DELIVERY_TEST_STATION', 'fbed3aa1-2cc6-4370-ab4d-59c5cc9bb924'),
    ],

];
