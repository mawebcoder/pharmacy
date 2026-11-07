<?php

declare(strict_types=1);

namespace App\Modules\Search\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One search from the search page (roadmap D21.4.4 step 6): what was typed, how many products it found, and which
 * product was opened from the results. Feeds the search insights page. Not audited (it is a log itself).
 */
final class SearchLog extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_QUERY = 'query';

    public const string COLUMN_NORMALIZED_QUERY = 'normalized_query';

    public const string COLUMN_RESULTS_COUNT = 'results_count';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_CLICKED_PRODUCT_ID = 'clicked_product_id';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getQuery(): string
    {
        return (string) $this->getAttribute(self::COLUMN_QUERY);
    }

    public function setQuery(string $query): static
    {
        return $this->setAttribute(self::COLUMN_QUERY, mb_substr($query, 0, 255));
    }

    public function getNormalizedQuery(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NORMALIZED_QUERY);
    }

    public function setNormalizedQuery(string $query): static
    {
        return $this->setAttribute(self::COLUMN_NORMALIZED_QUERY, mb_substr($query, 0, 255));
    }

    public function getResultsCount(): int
    {
        return (int) $this->getAttribute(self::COLUMN_RESULTS_COUNT);
    }

    public function setResultsCount(int $count): static
    {
        return $this->setAttribute(self::COLUMN_RESULTS_COUNT, $count);
    }

    public function getClickedProductId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CLICKED_PRODUCT_ID);

        return $value === null ? null : (int) $value;
    }

    public function setClickedProductId(?int $productId): static
    {
        return $this->setAttribute(self::COLUMN_CLICKED_PRODUCT_ID, $productId);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_RESULTS_COUNT => 'integer',
            self::COLUMN_CLICKED_PRODUCT_ID => 'integer',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
