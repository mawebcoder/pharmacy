<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFaqFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A question and answer shown in the FAQ tab of a product page (roadmap D3.1, B9.5).
 */
final class ProductFaq extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ProductFaqFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_QUESTION = 'question';

    public const string COLUMN_ANSWER = 'answer';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProductId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_PRODUCT_ID);
    }

    public function setProductId(int $productId): static
    {
        return $this->setAttribute(self::COLUMN_PRODUCT_ID, $productId);
    }

    public function getQuestion(): string
    {
        return (string) $this->getAttribute(self::COLUMN_QUESTION);
    }

    public function setQuestion(string $question): static
    {
        return $this->setAttribute(self::COLUMN_QUESTION, $question);
    }

    public function getAnswer(): string
    {
        return (string) $this->getAttribute(self::COLUMN_ANSWER);
    }

    public function setAnswer(string $answer): static
    {
        return $this->setAttribute(self::COLUMN_ANSWER, $answer);
    }

    public function getSort(): int
    {
        return (int) $this->getAttribute(self::COLUMN_SORT);
    }

    public function setSort(int $sort): static
    {
        return $this->setAttribute(self::COLUMN_SORT, $sort);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getProduct(): ?Product
    {
        return $this->getRelationValue('product');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_PRODUCT_ID, self::COLUMN_QUESTION, self::COLUMN_ANSWER, self::COLUMN_SORT])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'پرسش محصول: '.$this->getQuestion();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PRODUCT_ID => 'integer',
            self::COLUMN_SORT => 'integer',
        ];
    }
}
