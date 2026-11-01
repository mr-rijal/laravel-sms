<?php

use MrRijal\LaravelSms\Drivers;

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | The driver used when no provider is selected explicitly. Use "random"
    | to pick one of the "random_drivers" for each message, or "failover" to
    | try the "failover.drivers" in order.
    |
    */

    'default' => env('SMS_PROVIDER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Queueing
    |--------------------------------------------------------------------------
    |
    | When "queue" is true, send() dispatches a SendSmsJob instead of sending
    | immediately. Queued payloads are encrypted with your application key.
    |
    */

    'queue' => (bool) env('SMS_QUEUE', false),

    'queue_connection' => env('SMS_QUEUE_CONNECTION'),

    'queue_name' => env('SMS_QUEUE_NAME'),

    'retry' => [
        'tries' => (int) env('SMS_QUEUE_TRIES', 3),
        'backoff' => [10, 30, 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Timeouts (seconds) for provider API calls, and how many per-recipient
    | requests run concurrently for providers without a bulk endpoint.
    |
    */

    'http' => [
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('SMS_HTTP_CONNECT_TIMEOUT', 10),
        'concurrency' => (int) env('SMS_HTTP_CONCURRENCY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Registry (Extensible)
    |--------------------------------------------------------------------------
    */

    'drivers' => [
        // Global
        'twilio' => Drivers\TwilioDriver::class,
        'vonage' => Drivers\VonageDriver::class,
        'sinch' => Drivers\SinchDriver::class,
        'bird' => Drivers\BirdDriver::class,
        'plivo' => Drivers\PlivoDriver::class,
        'telnyx' => Drivers\TelnyxDriver::class,
        'infobip' => Drivers\InfobipDriver::class,
        'clicksend' => Drivers\ClickSendDriver::class,
        'clickatell' => Drivers\ClickatellDriver::class,
        'brevo' => Drivers\BrevoDriver::class,
        'bandwidth' => Drivers\BandwidthDriver::class,
        'sendpulse' => Drivers\SendPulseDriver::class,
        'aws_sns' => Drivers\AwsSnsDriver::class,
        'whatsapp' => Drivers\WhatsAppDriver::class,

        // Regional
        'msg91' => Drivers\Msg91Driver::class,
        'sparrow' => Drivers\SparrowDriver::class,
        'termii' => Drivers\TermiiDriver::class,
        'sendchamp' => Drivers\SendchampDriver::class,
        'africastalking' => Drivers\AfricasTalkingDriver::class,
        'bulksms' => Drivers\BulkSmsDriver::class,

        // Utility
        'failover' => Drivers\FailoverDriver::class,
        'log' => Drivers\LogDriver::class,
        'fake' => Drivers\FakeDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    |
    | Keep every credential in your environment or a secrets manager.
    |
    */

    'providers' => [
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
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

        // Requires aws/aws-sdk-php. Leave key/secret empty to use the default AWS credential chain (IAM roles).
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

        'sendchamp' => [
            'api_key' => env('SENDCHAMP_API_KEY'),
            'sender_name' => env('SENDCHAMP_SENDER_NAME', 'Sendchamp'),
            'route' => env('SENDCHAMP_ROUTE', 'dnd'), // dnd, non_dnd, or international
        ],

        'termii' => [
            'api_key' => env('TERMII_API_KEY'),
            'sender_id' => env('TERMII_SENDER_ID'),
            'channel' => env('TERMII_CHANNEL', 'generic'), // generic, dnd, or whatsapp
        ],

        'africastalking' => [
            'api_key' => env('AFRICASTALKING_API_KEY'),
            'username' => env('AFRICASTALKING_USERNAME'),
            'from' => env('AFRICASTALKING_FROM', ''),
            'sandbox' => env('AFRICASTALKING_SANDBOX', false),
        ],

        'bulksms' => [
            'token_id' => env('BULKSMS_TOKEN_ID'),
            'token_secret' => env('BULKSMS_TOKEN_SECRET'),
            'from' => env('BULKSMS_FROM', ''),
        ],

        // Development only: writes message content to your logs.
        'log' => [
            'channel' => env('SMS_LOG_CHANNEL'),
        ],

        'sinch' => [
            'service_plan_id' => env('SINCH_SERVICE_PLAN_ID'),
            'api_token' => env('SINCH_API_TOKEN'),
            'from' => env('SINCH_FROM'),
            'region' => env('SINCH_REGION', 'us'), // us, eu, au, br, ca
        ],

        'bird' => [
            'api_key' => env('BIRD_API_KEY'),
            'from' => env('BIRD_FROM'),
            'region' => env('BIRD_REGION', 'us1'), // us1, eu1
            'category' => env('BIRD_CATEGORY', 'transactional'), // transactional, marketing, authentication, service
        ],

        'clicksend' => [
            'username' => env('CLICKSEND_USERNAME'),
            'api_key' => env('CLICKSEND_API_KEY'),
            'from' => env('CLICKSEND_FROM'),
        ],

        'clickatell' => [
            'api_key' => env('CLICKATELL_API_KEY'),
            'from' => env('CLICKATELL_FROM'),
        ],

        'brevo' => [
            'api_key' => env('BREVO_API_KEY'),
            'sender' => env('BREVO_SMS_SENDER'),
            'type' => env('BREVO_SMS_TYPE', 'transactional'), // transactional, marketing
        ],

        'bandwidth' => [
            'account_id' => env('BANDWIDTH_ACCOUNT_ID'),
            'application_id' => env('BANDWIDTH_APPLICATION_ID'),
            'client_id' => env('BANDWIDTH_CLIENT_ID'),
            'client_secret' => env('BANDWIDTH_CLIENT_SECRET'),
            'from' => env('BANDWIDTH_FROM'),
        ],

        'sendpulse' => [
            'client_id' => env('SENDPULSE_CLIENT_ID'),
            'client_secret' => env('SENDPULSE_CLIENT_SECRET'),
            'sender' => env('SENDPULSE_SENDER'),
        ],

        // Tries each provider in order; recipients that already succeeded are never resent.
        'failover' => [
            'drivers' => array_filter(explode(',', (string) env('SMS_FAILOVER_DRIVERS', 'twilio,vonage'))),
        ],

        'fake' => [],
    ],

    'random_drivers' => ['twilio', 'msg91'],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Receive delivery reports and inbound messages. Routes are registered at
    | "{prefix}/{provider}" only when enabled. Webhooks are stateless, so they
    | use the "api" middleware group (no sessions or CSRF) plus rate limiting.
    |
    | Every webhook fails closed: requests are rejected with 401 unless the
    | provider's secret below is configured and the request proves it.
    |
    */

    'webhooks' => [
        'enabled' => (bool) env('SMS_WEBHOOKS_ENABLED', false),
        'prefix' => env('SMS_WEBHOOKS_PREFIX', 'laravel-sms/webhook'),
        'middleware' => ['api', 'throttle:120,1'],

        // App secret (signs X-Hub-Signature-256) and the subscription verify token.
        'whatsapp' => [
            'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        ],

        // HMAC-SHA256 of the raw body in the X-Sendchamp-Signature header.
        'sendchamp' => [
            'secret' => env('SENDCHAMP_WEBHOOK_SECRET'),
        ],

        // HMAC-SHA512 of the raw body in the X-Termii-Signature header.
        'termii' => [
            'secret' => env('TERMII_WEBHOOK_SECRET'),
        ],

        // Africa's Talking does not sign callbacks: append ?token=<secret> to the callback URL.
        'africastalking' => [
            'secret' => env('AFRICASTALKING_WEBHOOK_SECRET'),
        ],

        // HMAC-SHA256 of the raw body in the X-BulkSMS-Signature header.
        'bulksms' => [
            'secret' => env('BULKSMS_WEBHOOK_SECRET'),
        ],
    ],
];
