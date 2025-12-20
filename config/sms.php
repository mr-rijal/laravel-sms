<?php

use MrRijal\LaravelSms\Drivers;

return [

    'default' => env('SMS_PROVIDER', 'fake'),

    'queue' => false,

    /*
    |--------------------------------------------------------------------------
    | Driver Registry (Extensible)
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'twilio'  => Drivers\TwilioDriver::class,
        'sparrow' => Drivers\SparrowDriver::class,
        'msg91'   => Drivers\Msg91Driver::class,
        'vonage'  => Drivers\VonageDriver::class,
        'fake'    => Drivers\FakeDriver::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'twilio' => [
            'sid'   => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from'  => env('TWILIO_FROM'),
        ],

        'sparrow' => [
            'token' => env('SPARROW_TOKEN'),
            'from'  => env('SPARROW_FROM'),
        ],

        'msg91' => [
            'authkey' => env('MSG91_AUTHKEY'),
            'sender'  => env('MSG91_SENDER'),
        ],

        'vonage' => [
            'key'    => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from'   => env('VONAGE_FROM'),
        ],

        'fake' => []
    ],

    'random_drivers' => ['twilio', 'msg91'],
];
