<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\Refunds\Pages;

use App\Modules\Payments\Actions\ApproveRefund;
use App\Modules\Payments\Actions\RejectRefund;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Filament\Resources\Refunds\RefundResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * A refund's page (owner, 2026-11-19): «تأیید بازگشت وجه» once the money is transferred, with its reference (a cancelled
 * order's items and shipping may be lowered), which texts the customer; «رد» with a reason. Both ask first.
 */
final class ViewRefund extends ViewRecord
{
    protected static string $resource = RefundResource::class;

    public function getTitle(): string
    {
        return 'بازگشت وجه '.Toman::format($this->refund()->getAmount());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('تأیید بازگشت وجه')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->mayDecide())
                ->modalHeading('تأیید بازگشت وجه')
                ->modalDescription(fn (): string => 'وقتی '.Toman::format($this->refund()->getAmount()).' را به مشتری واریز کردید، شماره پیگیری را وارد کنید. به مشتری پیامک می‌شود.')
                ->fillForm(fn (): array => [
                    'items' => $this->refund()->getDetails()['items'] ?? null,
                    'shipping' => $this->refund()->getDetails()['shipping'] ?? null,
                ])
                ->schema(fn (): array => array_values(array_filter([
                    $this->refund()->getDetails() === [] ? null : TextInput::make('items')
                        ->label('مبلغ بازگشتی کالاها')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->required()
                        ->maxValue(Toman::fromRial((int) ($this->refund()->getDetails()['items'] ?? 0))),
                    $this->refund()->getDetails() === [] ? null : TextInput::make('shipping')
                        ->label('مبلغ بازگشتی هزینه ارسال')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->required()
                        ->maxValue(Toman::fromRial((int) ($this->refund()->getDetails()['shipping'] ?? 0))),
                    TextInput::make('reference')->label('شماره پیگیری واریز')->required()->maxLength(64)->extraInputAttributes(['dir' => 'ltr']),
                ])))
                ->action(function (array $data, ApproveRefund $approve): void {
                    $details = $this->refund()->getDetails();

                    try {
                        $approve(
                            $this->refund(),
                            (string) $data['reference'],
                            $this->adminId(),
                            $details === [] ? null : self::rial($data['items'] ?? null, (int) ($details['items'] ?? 0)),
                            $details === [] ? null : self::rial($data['shipping'] ?? null, (int) ($details['shipping'] ?? 0)),
                        );
                    } catch (RefundRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    $this->record = $this->refund()->refresh();
                    Notification::make()->success()->title('بازگشت وجه تأیید شد؛ به مشتری پیامک شد.')->send();
                }),
            Action::make('reject')
                ->label('رد')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->mayDecide())
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('دلیل رد')->required()->maxLength(255)->rows(3)])
                ->action(function (array $data, RejectRefund $reject): void {
                    try {
                        $reject($this->refund(), (string) $data['reason'], $this->adminId());
                    } catch (RefundRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    $this->record = $this->refund()->refresh();
                    Notification::make()->success()->title('بازگشت وجه رد شد.')->send();
                }),
        ];
    }

    /**
     * Amounts are typed in whole toman while they are kept in rial: the suggested amount's toman means that exact rial.
     */
    private static function rial(int|string|null $typed, int $suggested): int
    {
        $rial = (int) $typed;

        return Toman::fromRial($rial) === Toman::fromRial($suggested) ? $suggested : $rial;
    }

    private function refund(): Payment
    {
        /** @var Payment */
        return $this->getRecord();
    }

    private function mayDecide(): bool
    {
        return $this->refund()->getStatus() === PaymentAttemptStatus::Pending
            && (auth('admin')->user()?->can('approveRefund', $this->refund()) ?? false);
    }

    private function adminId(): int
    {
        return (int) auth('admin')->id();
    }
}
