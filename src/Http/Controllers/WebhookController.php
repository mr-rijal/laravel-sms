<?php

namespace MrRijal\LaravelSms\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MrRijal\LaravelSms\SmsManager;

class WebhookController
{
    public function __construct(
        protected SmsManager $smsManager
    ) {}

    /**
     * Handle incoming webhook from provider
     */
    public function handle(Request $request, string $provider): Response
    {
        try {
            $driver = $this->resolveDriver($provider);

            // Check if driver has handleWebhook method
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
        } catch (Exception $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Resolve driver instance for webhook handling
     */
    protected function resolveDriver(string $provider)
    {
        $drivers = config('sms.drivers', []);

        if (! isset($drivers[$provider])) {
            throw new InvalidArgumentException("SMS driver [{$provider}] not configured.");
        }

        $driverConfig = $drivers[$provider];

        // Get provider config
        $providerConfig = config("sms.providers.{$provider}", []);

        // if driverConfig is a class name string
        if (is_string($driverConfig)) {
            return new $driverConfig($providerConfig);
        }
        // if driverConfig is array with 'class' key
        if (is_array($driverConfig) && isset($driverConfig['class'])) {
            return new $driverConfig['class']($driverConfig);
        }

        throw new InvalidArgumentException("Driver {$provider} not implemented");
    }
}
