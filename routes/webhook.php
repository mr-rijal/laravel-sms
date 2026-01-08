<?php

use Illuminate\Support\Facades\Route;
use MrRijal\LaravelSms\Http\Controllers\WebhookController;

$middleware = config('sms.webhooks.middleware', ['web']);

Route::match(['get', 'post'], 'laravel-sms/webhook/{provider}', [WebhookController::class, 'handle'])
    ->middleware($middleware);
