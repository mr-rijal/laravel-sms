<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\HandlesWebhooks;
use MrRijal\LaravelSms\SmsManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WebhookController
{
    public function __construct(
        protected SmsManager $smsManager
    ) {}

    public function handle(Request $request, string $provider): Response
    {
        try {
            $driver = $this->smsManager->driver($provider);
        } catch (InvalidArgumentException $e) {
            Log::warning('SMS webhook received for an unknown or misconfigured provider', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response('Provider not found', 404);
        }

        if (! $driver instanceof HandlesWebhooks && ! method_exists($driver, 'handleWebhook')) {
            Log::warning('Webhook attempted for provider without webhook support', [
                'provider' => $provider,
            ]);

            return response('Webhook not supported for this provider', 404);
        }

        try {
            /** @var Response $response */
            $response = $driver->handleWebhook($request);

            return $response;
        } catch (Throwable $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }
}
