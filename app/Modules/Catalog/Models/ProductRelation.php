<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A link from one product to another: related, alternative or frequently bought together (roadmap D3.1).
 */
final class ProductRelation extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_PRODUCT_ID = 'product_id';

    public const string COLUMN_RELATED_PRODUCT_ID = 'related_product_id';

    public const string COLUMN_TYPE = 'type';

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

    public function getRelatedProductId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_RELATED_PRODUCT_ID);
    }

    public function setRelatedProductId(int $productId): static
    {
        return $this->setAttribute(self::COLUMN_RELATED_PRODUCT_ID, $productId);
    }

    public function getType(): ProductRelationType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function setType(ProductRelationType $type): static
    {
        return $this->setAttribute(self::COLUMN_TYPE, $type);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, self::COLUMN_RELATED_PRODUCT_ID)->withTrashed();
    }

    public function getRelatedProduct(): ?Product
    {
        return $this->getRelationValue('relatedProduct');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([self::COLUMN_PRODUCT_ID, self::COLUMN_RELATED_PRODUCT_ID, self::COLUMN_TYPE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'محصول مرتبط #'.$this->getProductId().' → #'.$this->getRelatedProductId();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PRODUCT_ID => 'integer',
            self::COLUMN_RELATED_PRODUCT_ID => 'integer',
            self::COLUMN_TYPE => ProductRelationType::class,
        ];
    }
}
