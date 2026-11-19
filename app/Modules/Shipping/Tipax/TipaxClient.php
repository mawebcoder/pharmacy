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
 * Tipax OM API v4, signing in through v3's Account/token, which v4 lacks (roadmap D6, S33; city calls brought forward in S20, decided 2026-11-14). Signs in with the account's
 * username, password and API key, keeps the access token in the cache until shortly before it expires, and retries
 * timeouts and server errors. Every call is logged in «گزارش سرویس‌های بیرونی» with secrets hidden.
 */
final readonly class TipaxClient
{
    private const string TOKEN_CACHE_KEY = 'shipping:tipax:access_token';

    /**
     * @param  array{token: string, cities: string, branch_cities: string, pricing: string, services_between_cities: string, customer: string, wallet: string, services: string, pack_types: string, packing_types: string, packaging_contents: string, brief_tracking: string, register_order: string, cancel_parcel: string}  $paths
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
        private int $pricingTimeoutSeconds,
    ) {}

    /**
     * Whether sign-in can be tried at all (the username, password and API key are set).
     */
    public function isConfigured(): bool
    {
        return filled($this->username) && filled($this->password) && filled($this->apiKey);
    }

    /**
     * Prices packages (`POST Pricing`, OmPriceDto): one entry per package (ETipaxPriceMinListDto) with a rate per
     * service family. Checkout waits on it, so it has the short pricing timeout and no retry.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function price(array $body): array
    {
        $response = $this->send(
            'pricing',
            fn (PendingRequest $request): Response => $request->withToken($this->token())->post($this->url($this->paths['pricing']), $body),
            timeoutSeconds: $this->pricingTimeoutSeconds,
            retries: 0,
        );
        $entries = $response->json();

        // The spec answers with a bare array; tolerate the usual envelope too.
        if (is_array($entries) && ! array_is_list($entries)) {
            if (($entries['isSuccess'] ?? true) === false) {
                throw TipaxRequestFailed::refused('pricing', (string) ($entries['statusCode'] ?? ''));
            }

            $entries = $entries['data'] ?? null;
        }

        if (! is_array($entries) || ! array_is_list($entries)) {
            throw TipaxRequestFailed::unexpected('pricing');
        }

        return array_values(array_filter($entries, is_array(...)));
    }

    /**
     * The services between two cities with their delivery days (`POST Pricing/GetServicesBetweenCities`):
     * serviceList [{ serviceId, serviceKindId, serviceKind, deliveryDay }].
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function servicesBetweenCities(int $originCityId, int $destinationCityId): array
    {
        $response = $this->send(
            'services_between_cities',
            fn (PendingRequest $request): Response => $request->withToken($this->token())->post($this->url($this->paths['services_between_cities']), [
                'sourceCityId' => $originCityId,
                'destinationCityId' => $destinationCityId,
            ]),
            timeoutSeconds: $this->pricingTimeoutSeconds,
            retries: 0,
        );

        if ($response->json('isSuccess') === false) {
            throw TipaxRequestFailed::refused('services_between_cities', (string) $response->json('statusCode'));
        }

        $services = $response->json('data.serviceList');

        if (! is_array($services)) {
            throw TipaxRequestFailed::unexpected('services_between_cities');
        }

        return array_values(array_filter($services, is_array(...)));
    }

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
     * The signed-in account (`GET Customers`): customerId, fullName … (its apiKey is never passed on).
     *
     * @return array{customer_id: int|null, full_name: string|null}
     *
     * @throws TipaxRequestFailed
     */
    public function account(): array
    {
        $body = $this->get('customer', $this->paths['customer'])->json();

        if (! is_array($body)) {
            throw TipaxRequestFailed::unexpected('customer');
        }

        return [
            'customer_id' => is_numeric($body['customerId'] ?? null) ? (int) $body['customerId'] : null,
            'full_name' => is_string($body['fullName'] ?? null) ? $body['fullName'] : null,
        ];
    }

    /**
     * The account's wallet balance as Tipax reports it (`GET Customers/Wallet`, a bare number).
     *
     * @throws TipaxRequestFailed
     */
    public function walletBalance(): float
    {
        $balance = $this->get('wallet', $this->paths['wallet'])->json();

        if (! is_numeric($balance)) {
            throw TipaxRequestFailed::unexpected('wallet');
        }

        return (float) $balance;
    }

    /**
     * Every Tipax service (`POST Pricing/GetServicesList`): { id, title, isActive … }.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function services(): array
    {
        $response = $this->send('services', fn (PendingRequest $request): Response => $request->withToken($this->token())->post($this->url($this->paths['services']), []));

        return $this->rows('services', $response->json('data'));
    }

    /**
     * Pack types (`GET PackingType/GetPackTypes`): { id, title, latinTitle }.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function packTypes(): array
    {
        return $this->rows('pack_types', $this->get('pack_types', $this->paths['pack_types'])->json('data'));
    }

    /**
     * Packings (`GET PackingType/GetPackingTypes`): { id, title, packType, sizes, min/max weight … }.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function packingTypes(): array
    {
        return $this->rows('packing_types', $this->get('packing_types', $this->paths['packing_types'])->json('result'));
    }

    /**
     * Package contents (`GET PackingType/GetPackagingContents`): { id, title, packType }.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    public function packagingContents(): array
    {
        return $this->rows('packaging_contents', $this->get('packaging_contents', $this->paths['packaging_contents'])->json('result'));
    }

    /**
     * Registers an order (`POST OmOrder`, OmV4OrderRegisterCuDto): Tipax answers at once with a tracking code per
     * package and a correlation id; the final result comes later on the callback addresses. Not retried, so a timeout
     * never registers twice.
     *
     * @param  array<string, mixed>  $body
     * @return array{correlation_id: string|null, tracking_codes: list<string>, raw: array<string, mixed>}
     *
     * @throws TipaxRequestFailed
     */
    public function registerOrder(array $body): array
    {
        $response = $this->send(
            'register_order',
            fn (PendingRequest $request): Response => $request->withToken($this->token())->post($this->url($this->paths['register_order']), $body),
            retries: 0,
        );
        $answer = $response->json();

        if (is_array($answer) && ($answer['isSuccess'] ?? true) === false) {
            throw TipaxRequestFailed::refused('register_order', (string) ($answer['message'] ?? $answer['statusCode'] ?? ''));
        }

        $data = is_array($answer) && is_array($answer['data'] ?? null) ? $answer['data'] : $answer;
        $codes = [];

        foreach ((array) (is_array($data) ? ($data['trackingCodesWithTitles'] ?? []) : []) as $row) {
            if (is_array($row) && is_string($row['trackingCode'] ?? null) && $row['trackingCode'] !== '') {
                $codes[] = $row['trackingCode'];
            }
        }

        if (! is_array($data) || $codes === []) {
            throw TipaxRequestFailed::unexpected('register_order');
        }

        return [
            'correlation_id' => is_string($data['correlationId'] ?? null) ? $data['correlationId'] : null,
            'tracking_codes' => $codes,
            'raw' => $data,
        ];
    }

    /**
     * Cancels a registered parcel (`PUT Parcels/Cancel/{trackingCode}`, roadmap S27): Tipax refuses once the parcel
     * has been picked up.
     *
     * @throws TipaxRequestFailed with Tipax's reason
     */
    public function cancelParcel(string $trackingCode): void
    {
        $response = $this->send(
            'cancel_parcel',
            fn (PendingRequest $request): Response => $request->withToken($this->token())->put($this->url(rtrim($this->paths['cancel_parcel'], '/').'/'.rawurlencode($trackingCode))),
            retries: 0,
        );
        $answer = $response->json();

        if (is_array($answer) && ($answer['isSuccess'] ?? true) === false) {
            throw TipaxRequestFailed::refused('cancel_parcel', (string) ($answer['message'] ?? $answer['statusCode'] ?? ''));
        }
    }

    /**
     * A parcel's status (`GET Tracking/BriefTracking/{trackingCode}`, JetTrackingBriefListDto): contractStatusName,
     * receiverCityName, terminateTime, terminateBranch, distanceDay … The status page waits on it, so the short timeout
     * and no retry.
     *
     * @return array<string, mixed>
     *
     * @throws TipaxRequestFailed
     */
    public function briefTracking(string $trackingCode): array
    {
        $response = $this->send(
            'brief_tracking',
            fn (PendingRequest $request): Response => $request->withToken($this->token())->get($this->url(rtrim($this->paths['brief_tracking'], '/').'/'.rawurlencode($trackingCode))),
            timeoutSeconds: $this->pricingTimeoutSeconds,
            retries: 0,
        );
        $body = $response->json();

        // A parcel registered online but not yet received at a branch has no events: Tipax answers 200 with `null`.
        if ($body === null || $body === []) {
            return [];
        }

        if (is_array($body) && ($body['isSuccess'] ?? true) === false) {
            throw TipaxRequestFailed::refused('brief_tracking', (string) ($body['message'] ?? $body['statusCode'] ?? ''));
        }

        $tracking = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : $body;
        $tracking = is_array($tracking) && array_is_list($tracking) ? ($tracking[0] ?? null) : $tracking;

        if (! is_array($tracking)) {
            throw TipaxRequestFailed::unexpected('brief_tracking');
        }

        return $tracking;
    }

    /**
     * @throws TipaxRequestFailed
     */
    private function get(string $operation, string $path): Response
    {
        return $this->send($operation, fn (PendingRequest $request): Response => $request->withToken($this->token())->get($this->url($path)));
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws TipaxRequestFailed
     */
    private function rows(string $operation, mixed $rows): array
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw TipaxRequestFailed::unexpected($operation);
        }

        return array_values(array_filter($rows, is_array(...)));
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
    private function send(string $operation, callable $call, array $redact = [], ?int $timeoutSeconds = null, ?int $retries = null): Response
    {
        $request = IntegrationHttp::for('tipax', $operation, redact: $redact)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(min($this->connectTimeoutSeconds, $timeoutSeconds ?? $this->timeoutSeconds))
            ->timeout($timeoutSeconds ?? $this->timeoutSeconds)
            ->retry(
                max(1, ($retries ?? $this->retries) + 1),
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

            // Tipax's own reason when it gives one, e.g. «فرمت اشتباه است» or «سرویس در این مسیر فعال نیست».
            $message = $exception->response->json('message');

            throw is_string($message) && $message !== ''
                ? TipaxRequestFailed::refused($operation, $message)
                : TipaxRequestFailed::failed($operation, $exception->response->status());
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
