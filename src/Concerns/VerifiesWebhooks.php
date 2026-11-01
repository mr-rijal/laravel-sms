<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shared, fail-closed webhook verification helpers.
 *
 * A webhook is only accepted when a non-empty secret is configured under
 * `sms.webhooks.{provider}.secret` and the request proves knowledge of it.
 */
trait VerifiesWebhooks
{
    protected function webhookSecret(string $provider): string
    {
        $secret = config("sms.webhooks.{$provider}.secret");

        return is_string($secret) ? trim($secret) : '';
    }

    /**
     * Verify an HMAC signature header computed over the raw request body.
     */
    protected function hasValidHmacSignature(
        Request $request,
        string $provider,
        string $header,
        string $algorithm = 'sha256',
        string $prefix = '',
    ): bool {
        $secret = $this->webhookSecret($provider);
        $signature = $request->header($header);

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = $prefix.hash_hmac($algorithm, $request->getContent(), $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    /**
     * Verify a shared secret token sent as a query parameter (for providers that do not sign callbacks).
     */
    protected function hasValidSecretToken(Request $request, string $provider, string $parameter = 'token'): bool
    {
        $secret = $this->webhookSecret($provider);
        $token = $request->query($parameter);

        return $secret !== '' && is_string($token) && hash_equals($secret, $token);
    }

    protected function logRejectedWebhook(Request $request, string $provider): void
    {
        Log::warning('SMS webhook verification failed', [
            'provider' => $provider,
            'ip' => $request->ip(),
        ]);
    }
}
