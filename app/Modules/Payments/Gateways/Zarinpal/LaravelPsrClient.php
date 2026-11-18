<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Zarinpal;

use App\Modules\System\Support\IntegrationHttp;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The HTTP client the Zarinpal SDK sends through (roadmap D7.6, S36): Laravel's Http, so every call is logged in
 * «گزارش سرویس‌های بیرونی» with the merchant id and card data hidden, and tests can fake Zarinpal with Http::fake().
 * The operation logged is the endpoint's name (request, verify, unVerified …). The last answer is kept, so the
 * client can read Zarinpal's error code where the SDK loses it.
 */
final class LaravelPsrClient implements ClientInterface
{
    private ?string $lastBody = null;

    public function __construct(private readonly int $timeoutSeconds) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastBody = null;
        $operation = basename($request->getUri()->getPath(), '.json');

        $response = IntegrationHttp::for('zarinpal', $operation)
            ->timeout($this->timeoutSeconds)
            ->withHeaders(['Accept' => 'application/json'])
            ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/json')
            ->send($request->getMethod(), (string) $request->getUri());
        $this->lastBody = $response->body();
        $psrResponse = $response->toPsrResponse();
        // Laravel has read the body already; the SDK reads it with getContents(), from the current position.
        $psrResponse->getBody()->rewind();

        return $psrResponse;
    }

    /**
     * Zarinpal's error code in the last answer (`errors.code`), or null when it had none or there was no answer.
     */
    public function lastErrorCode(): ?int
    {
        $body = json_decode((string) $this->lastBody, true);
        $code = is_array($body) && is_array($body['errors'] ?? null) ? ($body['errors']['code'] ?? null) : null;

        return is_numeric($code) ? (int) $code : null;
    }
}
