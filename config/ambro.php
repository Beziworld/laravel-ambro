<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | Connection used by the facade and by the injected client. Define more
    | accounts under "connections"; their options override the shared ones
    | below.
    |
    */

    'default' => env('AMBRO_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Credentials and environment
    |--------------------------------------------------------------------------
    |
    | API login and password issued by Ambro Express. The sandbox uses the
    | test web service (dev.api.ambro.opennet.pl), which has its own
    | accounts; the documentation publishes the test login "demo" / "demo".
    | Read operations are retried "read_retries" times on connection errors
    | and HTTP 502/503/504; shipments and pickups are never retried.
    |
    */

    'username' => env('AMBRO_USERNAME'),

    'password' => env('AMBRO_PASSWORD'),

    'sandbox' => (bool) env('AMBRO_SANDBOX', false),

    'timeout' => (int) env('AMBRO_TIMEOUT', 30),

    'read_retries' => (int) env('AMBRO_READ_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Shipment defaults
    |--------------------------------------------------------------------------
    |
    | Service id (38 Standard, 200 MEBEL; list the enabled ones with
    | `php artisan ambro:services`), label format (PDF for a label printer,
    | PDFA4 for an A4 sheet) and the currency of COD and insurance.
    |
    */

    'service' => (int) env('AMBRO_SERVICE', 38),

    'label_format' => env('AMBRO_LABEL_FORMAT', 'PDF'),

    'currency' => env('AMBRO_CURRENCY'),

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | Default sender and pickup location. Every field except point_id is
    | required by the API.
    |
    */

    'sender' => [
        'name' => env('AMBRO_SENDER_NAME'),
        'address' => env('AMBRO_SENDER_ADDRESS'),
        'city' => env('AMBRO_SENDER_CITY'),
        'post_code' => env('AMBRO_SENDER_POST_CODE'),
        'country_code' => env('AMBRO_SENDER_COUNTRY', 'PL'),
        'person' => env('AMBRO_SENDER_PERSON'),
        'phone' => env('AMBRO_SENDER_PHONE'),
        'email' => env('AMBRO_SENDER_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Additional accounts, for example one per company or warehouse:
    |
    | 'warehouse' => [
    |     'username' => env('AMBRO_WAREHOUSE_USERNAME'),
    |     'password' => env('AMBRO_WAREHOUSE_PASSWORD'),
    |     'sender' => ['name' => 'Warehouse', 'city' => 'Poznań', ...],
    | ],
    |
    */

    'connections' => [
        'default' => [],
    ],

];
