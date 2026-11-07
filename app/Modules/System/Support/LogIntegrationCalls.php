<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use App\Modules\System\Jobs\WriteIntegrationLog;
use App\Modules\System\Models\IntegrationLog;
use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Str;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Guzzle middleware: captures each request and its outcome (redacted) and queues one integration_logs row.
 * The job is dispatched straight away, not after commit, so a failed call stays logged even when the
 * surrounding database transaction rolls back.
 */
final readonly class LogIntegrationCalls
{
    /**
     * @param  list<string>  $redact  Extra fields to hide for this call only.
     */
    public function __construct(
        private string $service,
        private string $operation,
        private ?int $orderId = null,
        private array $redact = [],
    ) {}

    public function __invoke(callable $handler): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $startedAt = hrtime(true);
            $correlationId = (string) Str::uuid();

            try {
                $promise = $handler($request, $options);
            } catch (Throwable $exception) {
                // Some transports (and Laravel's fakes) fail before a promise exists.
                $this->record($request, $startedAt, $correlationId, null, null, $exception::class.': '.$exception->getMessage());

                throw $exception;
            }

            return $promise->then(
                function (ResponseInterface $response) use ($request, $startedAt, $correlationId): ResponseInterface {
                    $this->record($request, $startedAt, $correlationId, $response->getStatusCode(), $this->body($response), null);

                    return $response;
                },
                function (mixed $reason) use ($request, $startedAt, $correlationId): PromiseInterface {
                    $message = $reason instanceof Throwable ? $reason::class.': '.$reason->getMessage() : 'Request failed';
                    $this->record($request, $startedAt, $correlationId, null, null, $message);

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    private function record(RequestInterface $request, int $startedAt, string $correlationId, ?int $status, ?string $responseBody, ?string $error): void
    {
        $redactor = new IntegrationRedactor($this->redact);
        $requestBody = $this->body($request);

        $payload = [
            IntegrationLog::COLUMN_SERVICE => $this->service,
            IntegrationLog::COLUMN_OPERATION => $this->operation,
            IntegrationLog::COLUMN_METHOD => $request->getMethod(),
            IntegrationLog::COLUMN_URL => Str::limit($redactor->redactUrl((string) $request->getUri()), 2_040, ''),
            IntegrationLog::COLUMN_REQUEST => (string) json_encode([
                'headers' => $redactor->redactHeaders($request->getHeaders()),
                'body' => $this->structured($redactor->redactBody((string) $requestBody, $request->getHeaderLine('Content-Type'))),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            IntegrationLog::COLUMN_RESPONSE => $responseBody === null ? null : $redactor->redactBody($responseBody),
            IntegrationLog::COLUMN_HTTP_STATUS => $status,
            IntegrationLog::COLUMN_DURATION_MS => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            IntegrationLog::COLUMN_CORRELATION_ID => $correlationId,
            IntegrationLog::COLUMN_ORDER_ID => $this->orderId,
            IntegrationLog::COLUMN_ERROR => $error,
            IntegrationLog::COLUMN_CREATED_AT => now()->toDateTimeString(),
        ];

        WriteIntegrationLog::dispatch($payload)->onQueue((string) config('integrations.log.queue'));
    }

    /**
     * JSON bodies are kept as structured data so the log reads naturally; other bodies stay text.
     */
    private function structured(string $body): mixed
    {
        return json_validate($body) ? json_decode($body, true) : $body;
    }

    /**
     * The message body, cut to the configured size; streams are rewound so the caller can still read them.
     */
    private function body(MessageInterface $message): ?string
    {
        $stream = $message->getBody();

        if (! $stream->isSeekable()) {
            return null;
        }

        $stream->rewind();
        $body = $stream->read((int) config('integrations.log.max_body_bytes'));
        $stream->rewind();

        return $body;
    }
}
