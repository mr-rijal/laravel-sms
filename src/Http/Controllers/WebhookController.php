<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MrRijal\LaravelSms\Contracts\SmsProvider;
use MrRijal\LaravelSms\SmsManager;
use Throwable;

class WebhookController
{
    public function __construct(
        protected SmsManager $smsManager
    ) {}

    public function handle(Request $request, string $provider): Response
    {
        try {
            $driver = $this->resolveDriver($provider);

            if (method_exists($driver, 'handleWebhook')) {
                return $driver->handleWebhook($request);
            }

            Log::warning('Webhook attempted for provider without webhook support', [
                'provider' => $provider,
            ]);

            return response('Webhook not supported for this provider', 404);
        } catch (InvalidArgumentException $e) {
            Log::error('Webhook driver not found', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response('Provider not found', 404);
        } catch (Throwable $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    protected function resolveDriver(string $provider): SmsProvider
    {
        $drivers = config('sms.drivers', []);
        if (! is_array($drivers)) {
            throw new InvalidArgumentException('sms.drivers must be an array.');
        }

        if (! isset($drivers[$provider]) || (! is_string($drivers[$provider]) && ! is_array($drivers[$provider]))) {
            throw new InvalidArgumentException("SMS driver [{$provider}] not configured.");
        }

        $driverConfig = $drivers[$provider];
        $providerConfig = config("sms.providers.{$provider}", []);
        if (! is_array($providerConfig)) {
            $providerConfig = [];
        }

        if (is_string($driverConfig)) {
            if (! is_subclass_of($driverConfig, SmsProvider::class)) {
                throw new InvalidArgumentException("Driver class [{$driverConfig}] must implement ".SmsProvider::class.'.');
            }

            return new $driverConfig($providerConfig);
        }

        if (isset($driverConfig['class']) && is_string($driverConfig['class'])) {
            $class = $driverConfig['class'];
            if (! is_subclass_of($class, SmsProvider::class)) {
                throw new InvalidArgumentException("Driver class [{$class}] must implement ".SmsProvider::class.'.');
            }

            return new $class($driverConfig);
        }

        throw new InvalidArgumentException("Driver {$provider} not implemented");
    }
}
