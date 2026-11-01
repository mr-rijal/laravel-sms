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
     * @param  Closure(PendingRequest, string): mixed  $send  returns the response (or promise inside a pool)
     * @param  Closure(Response, string): SentMessage  $parse  throws DeliveryException on rejection
     */
    protected function sendEach(SmsMessage $message, Closure $send, Closure $parse): SmsResult
    {
        $sent = [];
        $failed = [];
        $firstError = null;

        foreach (array_chunk($message->getTo(), max(1, self::intConfig('sms.http.concurrency', 10))) as $chunk) {
            foreach ($this->dispatch($chunk, $send) as $to => $response) {
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
                }
            }
        }

        if ($firstError !== null) {
            $partial = new SmsResult($sent, $this->label);

            throw new DeliveryException(
                count($failed) === count($message->getTo())
                    ? $firstError->getMessage()
                    : $firstError->getMessage().' ('.count($failed).' of '.count($message->getTo()).' recipients failed)',
                $this->label,
                $failed,
                $partial,
                $firstError->getCode(),
                $firstError,
            );
        }

        return new SmsResult($sent);
    }

    /**
     * @param  list<string>  $recipients
     * @param  Closure(PendingRequest, string): mixed  $send
     * @return array<string, Response|Throwable>
     */
    private function dispatch(array $recipients, Closure $send): array
    {
        if (count($recipients) === 1) {
            try {
                $response = $send($this->request(), $recipients[0]);

                return [$recipients[0] => $response instanceof Response ? $response : new ConnectionException('Unexpected response.')];
            } catch (ConnectionException $e) {
                return [$recipients[0] => $e];
            }
        }

        /** @var array<string, Response|Throwable> $responses */
        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $to): mixed => $send($this->configure($pool->as($to)), $to),
            $recipients,
        ));

        return $responses;
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
        Cache::put($key, $token, max(60, (is_numeric($expiresIn) ? (int) $expiresIn : 3600) - 60));

        return $token;
    }

    private static function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
