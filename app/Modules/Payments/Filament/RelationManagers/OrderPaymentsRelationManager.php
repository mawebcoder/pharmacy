<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\RelationManagers;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Gateways\FakePaymentGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * «تراکنش‌های پرداخت» on an order's admin page (roadmap S29, owner 2026-11-15): every payment attempt, newest first,
 * read-only. At most one is paid. The relation is built here from the order record, so the Orders module does not
 * need the Payment model (module boundaries, C3).
 */
final class OrderPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'تراکنش‌های پرداخت';

    protected static ?string $modelLabel = 'تراکنش';

    protected static ?string $pluralModelLabel = 'تراکنش‌ها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @return HasMany<Payment, Model>
     */
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(Payment::class, Payment::COLUMN_ORDER_ID);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort(Payment::COLUMN_ID, 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make(Payment::COLUMN_CREATED_AT)
                    ->label('زمان')
                    ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                TextColumn::make(Payment::COLUMN_GATEWAY)
                    ->label('درگاه')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'zarinpal' => 'زرین‌پال',
                        FakePaymentGateway::NAME => 'درگاه آزمایشی',
                        default => $state,
                    }),
                TextColumn::make(Payment::COLUMN_AMOUNT)
                    ->label('مبلغ')
                    ->formatStateUsing(fn (int $state): string => Toman::format($state)),
                TextColumn::make(Payment::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (PaymentAttemptStatus $state): string => $state->label())
                    ->color(fn (PaymentAttemptStatus $state): string => $state->color()),
                TextColumn::make(Payment::COLUMN_REF_ID)
                    ->label('شماره پیگیری بانک')
                    ->copyable()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
                TextColumn::make(Payment::COLUMN_CARD_PAN)
                    ->label('کارت')
                    ->formatStateUsing(fn (string $state): string => PersianDigits::toPersian($state))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
                TextColumn::make(Payment::COLUMN_FAILURE_REASON)
                    ->label('دلیل ناموفق بودن')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make(Payment::COLUMN_VERIFIED_AT)
                    ->label('تأیید')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->placeholder('—'),
            ]);
    }
}
