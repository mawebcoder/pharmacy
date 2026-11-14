<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One store setting (roadmap D3.6). Read through Settings\Queries\StoreSettings, written by SaveStoreSettings.
 */
final class Setting extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_KEY = 'key';

    public const string COLUMN_VALUE = 'value';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getSettingKey(): string
    {
        return (string) $this->getAttribute(self::COLUMN_KEY);
    }

    public function setSettingKey(string $key): static
    {
        return $this->setAttribute(self::COLUMN_KEY, $key);
    }

    public function getValue(): mixed
    {
        return $this->getAttribute(self::COLUMN_VALUE);
    }

    public function setValue(mixed $value): static
    {
        return $this->setAttribute(self::COLUMN_VALUE, $value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('settings')->logOnly([self::COLUMN_KEY, self::COLUMN_VALUE])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function activityLogLabel(): string
    {
        return 'تنظیم: '.$this->getSettingKey();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [self::COLUMN_VALUE => 'json'];
    }
}
