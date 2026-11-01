<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Drivers;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MrRijal\LaravelSms\Exceptions\DeliveryException;
use MrRijal\LaravelSms\SentMessage;
use MrRijal\LaravelSms\SmsMessage;
use MrRijal\LaravelSms\SmsResult;
use Throwable;

/**
 * Base for providers reached over HTTP through Laravel's HTTP client (fakeable with Http::fake()).
 */
abstract class HttpDriver extends Driver
{
    /**
     * A configured request: timeouts, JSON accept header and package user agent.
     */
    protected function request(): PendingRequest
    {
        return $this->configure(Http::createPendingRequest());
    }

    protected function configure(PendingRequest $request): PendingRequest
    {
        return $request
            ->timeout(self::intConfig('sms.http.timeout', 30))
            ->connectTimeout(self::intConfig('sms.http.connect_timeout', 10))
            ->acceptJson()
            ->withUserAgent('mr-rijal/laravel-sms');
    }

    /**
     * Send one request covering every recipient.
     *
     * @param  Closure(PendingRequest): Response  $send
     * @param  Closure(Response): SmsResult  $parse  throws DeliveryException on rejection
     */
    protected function sendOnce(SmsMessage $message, Closure $send, Closure $parse): SmsResult
    {
        try {
            return $parse($send($this->request()));
        } catch (ConnectionException $e) {
            throw $this->transportError($message->getTo(), $e);
        } catch (DeliveryException $e) {
            // Keep per-recipient detail from the parser; otherwise every recipient failed.
            if ($e->failedRecipients !== []) {
                throw $e;
            }

            throw new DeliveryException($e->getMessage(), $this->label, $message->getTo(), $e->partial, $e->getCode(), $e);
        }
    }

    /**
     * Send one request per recipient, concurrently, collecting partial success.
     *
     * Any failure is reported as a DeliveryException whose failedRecipients are exactly the
     * recipients the provider did not accept, so retries and failover never double-send.
     *
     * @param  Closure(PendingRequest, string): mixed  $send  returns the response (or promise inside a pool)
     * @param  Closure(Response, string): SentMessage  $parse  throws DeliveryException on rejection
     */
    protected function sendEach(SmsMessage $message, Closure $send, Closure $parse): SmsResult
    {
        $sent = [];
        $failed = [];
        $firstError = null;
        $stopped = false;

        foreach (array_chunk($message->getTo(), max(1, self::intConfig('sms.http.concurrency', 10))) as $chunk) {
            if ($stopped) {
                // An unrecoverable error (e.g. authentication) stopped the batch: these were never sent.
                array_push($failed, ...$chunk);

                continue;
            }

            try {
                $responses = $this->dispatch($chunk, $send);
            } catch (Throwable $e) {
                array_push($failed, ...$chunk);
                $firstError ??= $this->unexpectedError($e, $chunk);
                $stopped = true;

                continue;
            }

            foreach ($responses as [$to, $response]) {
                try {
                    if ($response instanceof Throwable) {
                        throw $response;
                    }

                    $sent[] = $parse($response, $to);
                } catch (DeliveryException $e) {
                    $failed[] = $to;
                    $firstError ??= $e;
                } catch (ConnectionException $e) {
                    $failed[] = $to;
                    $firstError ??= $this->transportError([$to], $e);
                } catch (Throwable $e) {
                    $failed[] = $to;
                    $firstError ??= $this->unexpectedError($e, [$to]);
                }
            }
        }

        if ($firstError !== null) {
            throw new DeliveryException(
                count($failed) === count($message->getTo())
                    ? $firstError->getMessage()
                    : $firstError->getMessage().' ('.count($failed).' of '.count($message->getTo()).' recipients failed)',
                $this->label,
                $failed,
                new SmsResult($sent, $this->label),
                $firstError->getCode(),
                $firstError,
            );
        }

        return new SmsResult($sent);
    }

    /**
     * Errors raised outside a provider response (token refresh, stray requests, bugs).
     *
     * @param  list<string>  $recipients
     */
    private function unexpectedError(Throwable $e, array $recipients): DeliveryException
    {
        return $e instanceof DeliveryException
            ? $e
            : new DeliveryException("Failed to send SMS via {$this->label} (unexpected error).", $this->label, $recipients, null, 0, $e);
    }

    /**
     * @param  list<string>  $recipients
     * @param  Closure(PendingRequest, string): mixed  $send
     * @return list<array{string, Response|Throwable}>
     */
    private function dispatch(array $recipients, Closure $send): array
    {
        if (count($recipients) === 1) {
            try {
                $response = $send($this->request(), $recipients[0]);

                return [[$recipients[0], $response instanceof Response ? $response : new ConnectionException('Unexpected response.')]];
            } catch (ConnectionException $e) {
                return [[$recipients[0], $e]];
            }
        }

        // Pool keys must not be numeric strings: PHP would turn phone numbers into int keys.
        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (int $i): mixed => $send($this->configure($pool->as("r{$i}")), $recipients[$i]),
            array_keys($recipients),
        ));

        return array_map(function (int $i) use ($recipients, $responses): array {
            $response = $responses["r{$i}"] ?? null;

            return [$recipients[$i], $response instanceof Response || $response instanceof Throwable ? $response : new ConnectionException('No response.')];
        }, array_keys($recipients));
    }

    /**
     * Throw a DeliveryException for an unsuccessful response, with bounded provider error text.
     *
     * @param  string|list<string>  $detailPaths  dot-notation JSON paths to the provider's error message
     *
     * @throws DeliveryException
     */
    protected function fail(Response $response, string|array $detailPaths = []): never
    {
        $detail = null;

        foreach ((array) $detailPaths as $path) {
            $value = $response->json($path);

            if (is_string($value) && trim($value) !== '') {
                $detail = ': '.Str::limit(trim($value), 200);
                break;
            }
        }

        throw new DeliveryException(
            "Failed to send SMS via {$this->label} (HTTP {$response->status()}){$detail}",
            $this->label,
            code: $response->status(),
        );
    }

    /**
     * A string from the JSON body, if present.
     */
    protected function jsonString(Response $response, string $path): ?string
    {
        $value = $response->json($path);

        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $recipients
     */
    protected function transportError(array $recipients, Throwable $previous): DeliveryException
    {
        return new DeliveryException("Failed to send SMS via {$this->label} (transport error).", $this->label, $recipients, null, 0, $previous);
    }

    /**
     * Fetch an OAuth access token and cache it until shortly before it expires.
     * The cache key is a hash, so credentials never appear in cache keys.
     *
     * @param  Closure(): Response  $request  the token request
     */
    protected function cachedToken(string $clientId, Closure $request): string
    {
        $key = 'laravel-sms:token:'.hash('sha256', static::class.'|'.$clientId);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new DeliveryException("Failed to authenticate with {$this->label} (transport error).", $this->label, previous: $e);
        }

        $token = $response->successful() ? $this->jsonString($response, 'access_token') : null;
        if ($token === null) {
            throw new DeliveryException("Failed to authenticate with {$this->label} (HTTP {$response->status()}).", $this->label, code: $response->status());
        }

        $expiresIn = $response->json('expires_in');
        $lifetime = is_numeric($expiresIn) ? (int) $expiresIn : 3600;
        Cache::put($key, $token, max(1, $lifetime > 120 ? $lifetime - 60 : intdiv($lifetime, 2)));

        return $token;
    }

    /**
     * Drop a cached token (e.g. after the provider answers 401), so the next send re-authenticates.
     */
    protected function forgetToken(string $clientId): void
    {
        Cache::forget('laravel-sms:token:'.hash('sha256', static::class.'|'.$clientId));
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
