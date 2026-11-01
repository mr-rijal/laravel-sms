<?php

use MrRijal\LaravelSms\Drivers;

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | The driver used when no provider is selected explicitly. Use "random"
    | to pick one of the "random_drivers" for each message.
    |
    */

    'default' => env('SMS_PROVIDER', 'fake'),

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
        'sendchamp' => Drivers\SendchampDriver::class,
        'termii' => Drivers\TermiiDriver::class,
        'africastalking' => Drivers\AfricasTalkingDriver::class,
        'bulksms' => Drivers\BulkSmsDriver::class,
        'log' => Drivers\LogDriver::class,
        'telegram_mirror' => Drivers\TelegramMirrorDriver::class,
        'discord_webhook' => Drivers\DiscordWebhookDriver::class,
        'slack_webhook' => Drivers\SlackWebhookDriver::class,
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

        // Mirror drivers send a redacted copy to an internal channel; they never reach the recipient.
        'telegram_mirror' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],

        'discord_webhook' => [
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        ],

        'slack_webhook' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
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
