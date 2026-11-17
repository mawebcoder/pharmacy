<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\FaqFactory;
use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A question on /faq (roadmap D3.6, S34): its group, the question, a plain-text answer and its place in the group.
 */
final class Faq extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_GROUP = 'group';

    public const string COLUMN_QUESTION = 'question';

    public const string COLUMN_ANSWER = 'answer';

    public const string COLUMN_SORT = 'sort';

    public const string COLUMN_IS_ACTIVE = 'is_active';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getGroup(): FaqGroup
    {
        return $this->getAttribute(self::COLUMN_GROUP);
    }

    public function setGroup(FaqGroup $group): static
    {
        return $this->setAttribute(self::COLUMN_GROUP, $group);
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

    public function isActive(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): static
    {
        return $this->setAttribute(self::COLUMN_IS_ACTIVE, $isActive);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::COLUMN_IS_ACTIVE, true);
    }

    public function activityLogLabel(): string
    {
        return 'پرسش متداول: '.$this->getQuestion();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_GROUP, self::COLUMN_QUESTION, self::COLUMN_ANSWER, self::COLUMN_IS_ACTIVE])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_GROUP => FaqGroup::class,
            self::COLUMN_IS_ACTIVE => 'boolean',
        ];
    }
}
