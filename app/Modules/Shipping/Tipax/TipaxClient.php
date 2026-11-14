<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Tipax;

use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\System\Support\IntegrationHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tipax OM API v4, signing in through v3's Account/token, which v4 lacks (roadmap D6, S22; city calls brought forward in S18, decided 2026-11-14). Signs in with the account's
 * username, password and API key, keeps the access token in the cache until shortly before it expires, and retries
 * timeouts and server errors. Every call is logged in «گزارش سرویس‌های بیرونی» with secrets hidden.
 */
final readonly class TipaxClient
{
    private const string TOKEN_CACHE_KEY = 'shipping:tipax:access_token';

    /**
     * @param  array{token: string, cities: string, branch_cities: string}  $paths
     */
    public function __construct(
        private string $baseUrl,
        private ?string $username,
        private ?string $password,
        private ?string $apiKey,
        private array $paths,
        private int $connectTimeoutSeconds,
        private int $timeoutSeconds,
        private int $retries,
        private int $retrySleepMs,
    ) {}

    /**
     * Every Tipax city with its province (`GET Cities/plusstate`, OmCityListWithStateDto).
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function cities(): array
    {
        return $this->list('cities', $this->paths['cities']);
    }

    /**
     * Cities with a Tipax branch (`GET Cities/BranchCities`, OmCityListDto).
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function branchCities(): array
    {
        return $this->list('branch_cities', $this->paths['branch_cities']);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    private function list(string $operation, string $path): array
    {
        $response = $this->send($operation, fn (PendingRequest $request): Response => $request->withToken($this->token())->get($this->url($path)));
        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            throw TipaxRequestFailed::unexpected($operation);
        }

        return array_values(array_filter($body, is_array(...)));
    }

    /**
     * @throws TipaxRequestFailed
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (blank($this->username) || blank($this->password) || blank($this->apiKey)) {
            throw TipaxRequestFailed::notConfigured();
        }

        $response = $this->send('token', fn (PendingRequest $request): Response => $request->post($this->url($this->paths['token']), [
            'username' => $this->username,
            'password' => $this->password,
            'apiKey' => $this->apiKey,
        ]), redact: ['accessToken', 'refreshToken']);
        $token = $response->json('accessToken');

        if (! is_string($token) || $token === '') {
            throw TipaxRequestFailed::unexpected('token');
        }

        // Renew a minute early so a call never starts with an expiring token.
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, (int) $response->json('expiresIn', 3600) - 60));

        return $token;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @param  list<string>  $redact
     *
     * @throws TipaxRequestFailed
     */
    private function send(string $operation, callable $call, array $redact = []): Response
    {
        $request = IntegrationHttp::for('tipax', $operation, redact: $redact)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeoutSeconds)
            ->timeout($this->timeoutSeconds)
            ->retry(
                max(1, $this->retries + 1),
                $this->retrySleepMs,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
            );

        try {
            return $call($request)->throw();
        } catch (ConnectionException) {
            throw TipaxRequestFailed::unreachable($operation);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
            }

            throw TipaxRequestFailed::failed($operation, $exception->response->status());
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
