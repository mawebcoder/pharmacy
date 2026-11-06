<?php

declare(strict_types=1);

namespace App\Modules\System\Filament\Resources\ActivityLogs\Tables;

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

final class ActivityLogsTable
{
    /**
     * @var array<string, string>
     */
    private const array EVENTS = [
        'created' => 'ایجاد',
        'updated' => 'ویرایش',
        'deleted' => 'حذف',
        'roles_attached' => 'افزودن نقش',
        'roles_detached' => 'برداشتن نقش',
        'permissions_attached' => 'افزودن مجوز',
        'permissions_detached' => 'برداشتن مجوز',
        'two_factor_reset' => 'بازنشانی تأیید دو مرحله‌ای',
    ];

    /**
     * Persian names for logged model fields; unknown fields show as stored.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'name' => 'نام',
        'email' => 'ایمیل',
        'is_active' => 'فعال',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('زمان')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('انجام‌دهنده')
                    ->placeholder('سیستم'),
                TextColumn::make('event')
                    ->label('رویداد')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::eventLabel($state)),
                TextColumn::make('subject_label')
                    ->label('مورد')
                    ->state(fn (Activity $record): string => self::subjectLabel($record)),
                TextColumn::make('changes_summary')
                    ->label('تغییرات')
                    ->state(fn (Activity $record): string => self::changesSummary($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event')->label('رویداد')->options(self::EVENTS),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function eventLabel(?string $event): string
    {
        return $event === null ? '' : (self::EVENTS[$event] ?? $event);
    }

    public static function subjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;

        return match (true) {
            $subject instanceof HasActivityLogLabel => $subject->activityLogLabel(),
            $subject instanceof Role => 'نقش: '.AdminRole::labelFor($subject->name),
            default => class_basename((string) $activity->subject_type).' #'.$activity->subject_id,
        };
    }

    /**
     * Field names for model changes, or the role/permission names for pivot changes.
     */
    private static function changesSummary(Activity $activity): string
    {
        $changedFields = array_keys((array) ($activity->attribute_changes?->get('attributes') ?? []));

        if ($changedFields !== []) {
            return implode('، ', array_map(fn (int|string $field): string => self::FIELDS[$field] ?? (string) $field, $changedFields));
        }

        $roles = (array) ($activity->properties?->get('roles') ?? []);
        $permissions = (array) ($activity->properties?->get('permissions') ?? []);

        return implode('، ', [...array_map(AdminRole::labelFor(...), $roles), ...$permissions]);
    }
}
