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
        'telnyx' => Drivers\TelnyxDriver::class,
        'plivo' => Drivers\PlivoDriver::class,
        'infobip' => Drivers\InfobipDriver::class,
        'log' => Drivers\LogDriver::class,
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

        'telnyx' => [
            'api_key' => env('TELNYX_API_KEY'),
            'from' => env('TELNYX_FROM'),
            'messaging_profile_id' => env('TELNYX_MESSAGING_PROFILE_ID'),
        ],

        'plivo' => [
            'auth_id' => env('PLIVO_AUTH_ID'),
            'auth_token' => env('PLIVO_AUTH_TOKEN'),
            'from' => env('PLIVO_FROM'),
            'log' => env('PLIVO_LOG_MESSAGES', false),
        ],

        'infobip' => [
            'api_key' => env('INFOBIP_API_KEY'),
            'sender' => env('INFOBIP_SENDER'),
            'base_url' => env('INFOBIP_BASE_URL', 'https://api.infobip.com/'),
        ],

        'log' => [],

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
        | webhook requests. A configured webhook rejects requests when its
        | secret is empty or invalid.
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
