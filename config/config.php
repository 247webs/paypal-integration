<?php
/**
 * PayPal Setting & API Credentials.
 */

return [
    'mode'    => env('PAYPAL_ENV', 'sandbox'), // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
    'sandbox' => [
        'username'    => env('PAYPAL_API_USERNAME', ''),
        'password'    => env('PAYPAL_API_PASSWORD', ''),
        'secret'      => env('PAYPAL_API_SECRET', ''),
        'certificate' => env('PAYPAL_API_CERTIFICATE', ''),
        'app_id'      => '', // Used for testing Adaptive Payments API in sandbox mode
    ],
    'live' => [
        'username'    => env('PAYPAL_API_USERNAME', ''),
        'password'    => env('PAYPAL_API_PASSWORD', ''),
        'secret'      => env('PAYPAL_API_SECRET', ''),
        'certificate' => env('PAYPAL_API_CERTIFICATE', ''),
        'app_id'      => '', // Used for Adaptive Payments API
    ],

    'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
    'currency'       => 'USD',
    'notify_url'     => '', // Change this accordingly for your application.
    'locale'         => '', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
];
