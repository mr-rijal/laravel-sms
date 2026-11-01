<?php

use Illuminate\Support\Facades\Route;
use MrRijal\LaravelSms\Http\Controllers\WebhookController;

$prefix = config('sms.webhooks.prefix', 'laravel-sms/webhook');
$middleware = config('sms.webhooks.middleware', ['api', 'throttle:120,1']);

Route::match(['get', 'post'], trim(is_string($prefix) ? $prefix : 'laravel-sms/webhook', '/').'/{provider}', [WebhookController::class, 'handle'])
    ->where('provider', '[A-Za-z0-9_\-]+')
    ->middleware(is_array($middleware) ? $middleware : [])
    ->name('sms.webhook');
