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
        'twilio' => Drivers\TwilioDriver::class,
        'sparrow' => Drivers\SparrowDriver::class,
        'msg91' => Drivers\Msg91Driver::class,
        'vonage' => Drivers\VonageDriver::class,
        'whatsapp' => Drivers\WhatsAppDriver::class,
        'aws_sns' => Drivers\AwsSnsDriver::class,
        'fake' => Drivers\FakeDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'sparrow' => [
            'token' => env('SPARROW_TOKEN'),
            'from' => env('SPARROW_FROM'),
        ],

        'msg91' => [
            'authkey' => env('MSG91_AUTHKEY'),
            'sender' => env('MSG91_SENDER'),
        ],

        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM'),
        ],

        'whatsapp' => [
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
            'preview_url' => env('WHATSAPP_PREVIEW_URL', false),
            'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', ''),
        ],

        'aws_sns' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'sender_id' => env('AWS_SNS_SENDER_ID', ''),
            'sms_type' => env('AWS_SNS_SMS_TYPE', 'Transactional'), // Transactional or Promotional
        ],

        'fake' => [],
    ],

    'random_drivers' => ['twilio', 'msg91'],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhooks to receive status updates and incoming messages
    | from SMS/WhatsApp providers.
    |
    */
    'webhooks' => [
        'enabled' => env('SMS_WEBHOOKS_ENABLED', false),
        'middleware' => ['web'],

        /*
        |--------------------------------------------------------------------------
        | Provider Webhook Secrets
        |--------------------------------------------------------------------------
        |
        | Configure webhook secrets for each provider to verify incoming
        | webhook requests. Leave empty to disable verification.
        |
        */
        'twilio' => [
            'secret' => env('TWILIO_WEBHOOK_SECRET'),
        ],

        'whatsapp' => [
            'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        ],

        'vonage' => [
            'secret' => env('VONAGE_WEBHOOK_SECRET'),
        ],

        'msg91' => [
            'secret' => env('MSG91_WEBHOOK_SECRET'),
        ],

        'sparrow' => [
            'secret' => env('SPARROW_WEBHOOK_SECRET'),
        ],
    ],
];
