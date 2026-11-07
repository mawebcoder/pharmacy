<?php

declare(strict_types=1);

namespace App\Modules\Search\Models;

use App\Modules\Search\Database\Factories\SearchSynonymFactory;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Words that mean the same in a search (roadmap D21.5.6), e.g. «ویتامین دی» = «ویتامین d» = «d3» = «vitamin d».
 * The term and its synonyms form one group: searching any of them finds the others.
 */
final class SearchSynonym extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<SearchSynonymFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_TERM = 'term';

    public const string COLUMN_SYNONYMS = 'synonyms';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getTerm(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TERM);
    }

    public function setTerm(string $term): static
    {
        return $this->setAttribute(self::COLUMN_TERM, $term);
    }

    /**
     * @return list<string>
     */
    public function getSynonyms(): array
    {
        $synonyms = $this->getAttribute(self::COLUMN_SYNONYMS);

        return is_array($synonyms) ? array_values(array_map(strval(...), $synonyms)) : [];
    }

    /**
     * @param  list<string>  $synonyms
     */
    public function setSynonyms(array $synonyms): static
    {
        return $this->setAttribute(self::COLUMN_SYNONYMS, $synonyms);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('search')
            ->logOnly([self::COLUMN_TERM, self::COLUMN_SYNONYMS])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'مترادف جستجو: '.$this->getTerm();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [self::COLUMN_SYNONYMS => 'array'];
    }
}
