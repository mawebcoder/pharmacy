<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use Carbon\CarbonImmutable;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

/**
 * A Telescope entry rebuilt from the plain payload a queued WriteTelescopeEntries job carries (the original may hold a
 * live exception or user, which cannot be queued). It answers isException() and familyHash() as the original did, so
 * Telescope's database repository stores it the same way.
 */
final class QueuedTelescopeEntry extends IncomingEntry
{
    private bool $exception = false;

    /**
     * @return array{uuid: string, type: string, family_hash: string|null, content: array<mixed>, tags: array<mixed>, created_at: string, exception: bool}
     */
    public static function payload(IncomingEntry $entry): array
    {
        return [
            'uuid' => (string) $entry->uuid,
            'type' => (string) $entry->type,
            'family_hash' => $entry->familyHash(),
            'content' => (array) $entry->content,
            'tags' => (array) $entry->tags,
            'created_at' => $entry->recordedAt->format('Y-m-d H:i:s'),
            'exception' => $entry->isException(),
        ];
    }

    /**
     * @param  array{uuid: string, type: string, family_hash: string|null, content: array<mixed>, tags: array<mixed>, created_at: string, exception: bool}  $payload
     */
    public static function fromPayload(array $payload, string $batchId): self
    {
        $entry = new self($payload['content'], $payload['uuid']);
        $entry->batchId = $batchId;
        $entry->type = $payload['type'];
        $entry->familyHash = $payload['family_hash'];
        $entry->tags = $payload['tags'];
        $entry->recordedAt = CarbonImmutable::parse($payload['created_at']);
        $entry->exception = $payload['exception'] && $payload['type'] === EntryType::EXCEPTION;

        return $entry;
    }

    public function isException(): bool
    {
        return $this->exception;
    }
}
