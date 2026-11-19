<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

use Illuminate\Support\Carbon;

/**
 * What the customer's order page shows about a parcel (roadmap S37): Tipax's status, the destination city, the branch
 * it is at or was delivered by, when it was delivered, and when Tipax last answered.
 */
final readonly class ParcelStatusData
{
    /**
     * Words in a finished parcel's status that mean it did not reach the customer.
     */
    private const array NOT_DELIVERED = ['برگشت', 'مرجوع', 'عودت', 'ابطال', 'لغو'];

    public function __construct(
        public ?string $status,
        public ?string $destinationCity,
        public ?string $branch,
        public ?string $deliveredAt,
        public ?Carbon $syncedAt,
    ) {}

    /**
     * Whether the parcel reached the customer (owner, 2026-11-19): Tipax gave it a completion time and its status is not a
     * return or cancellation. «در حال تحویل» has no completion time yet. [CONFIRM] against real Tipax answers.
     */
    public function isDelivered(): bool
    {
        if ($this->deliveredAt === null) {
            return false;
        }

        foreach (self::NOT_DELIVERED as $word) {
            if (str_contains((string) $this->status, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $tracking  Tipax's BriefTracking answer
     */
    public static function fromTipax(array $tracking, ?Carbon $syncedAt): self
    {
        $text = fn (string $key): ?string => is_string($tracking[$key] ?? null) && trim($tracking[$key]) !== '' ? trim($tracking[$key]) : null;

        return new self(
            status: $text('contractStatusName'),
            destinationCity: $text('receiverCityName'),
            branch: $text('terminateBranch') ?? $text('assignedAgencyName'),
            deliveredAt: $text('terminateTime'),
            syncedAt: $syncedAt,
        );
    }
}
