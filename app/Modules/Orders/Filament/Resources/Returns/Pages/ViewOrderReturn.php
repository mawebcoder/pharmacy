<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Pages;

use App\Modules\Orders\Actions\ApproveOrderReturn;
use App\Modules\Orders\Actions\CancelOrderReturn;
use App\Modules\Orders\Actions\ConfirmReturnReceived;
use App\Modules\Orders\Actions\InquireOrderReturn;
use App\Modules\Orders\Actions\RecordReturnRefund;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * A return's page (S27): «تأیید» registers the pickup with Tipax and texts the customer the code; «استعلام از تیپاکس»
 * follows the pickup; «ثبت دریافت در انبار» counts what arrived per row once Tipax reports it delivered to us; «ثبت
 * بازگشت وجه» records the refund staff transferred, only after that (items never sent: once approved); «لغو مرجوعی»
 * only before pickup, also at Tipax.
 */
final class ViewOrderReturn extends ViewRecord
{
    protected static string $resource = OrderReturnResource::class;

    public function getTitle(): string
    {
        return 'مرجوعی '.$this->orderReturn()->getNumber();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('order')
                ->label('سفارش')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->orderReturn()->getOrderId()])),
            Action::make('approve')
                ->label(fn (): string => $this->orderReturn()->needsPickup() ? 'تأیید و ثبت در تیپاکس' : 'تأیید')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->mayUpdate() && $this->orderReturn()->getStatus() === OrderReturnStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->orderReturn()->needsPickup()
                    ? 'تیپاکس بسته‌ها را از آدرس مشتری جمع‌آوری و به انبار می‌آورد؛ کد رهگیری مرجوعی برای مشتری پیامک می‌شود. بازگشت وجه پس از جمع‌آوری ثبت می‌شود.'
                    : 'این کالاها ارسال نشده بودند؛ پس از تأیید، بازگشت وجه را ثبت کنید.')
                ->action(function (ApproveOrderReturn $approve): void {
                    try {
                        $codes = $approve($this->orderReturn(), $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();

                    if ($codes === []) {
                        Notification::make()->success()->title('مرجوعی تأیید شد.')->send();

                        return;
                    }

                    Notification::make()->success()->title('مرجوعی در تیپاکس ثبت شد.')->body('کد رهگیری: '.implode('، ', $codes))->persistent()->send();
                }),
            Action::make('inquire')
                ->label('استعلام از تیپاکس')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->visible(fn (): bool => $this->orderReturn()->needsPickup() && $this->orderReturn()->getTrackingCodes() !== [])
                ->action(function (InquireOrderReturn $inquire): void {
                    try {
                        $progress = $inquire($this->orderReturn());
                    } catch (TipaxRequestFailed $failed) {
                        Notification::make()->danger()->title('تیپاکس پاسخ نداد.')->body($failed->getMessage())->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();
                    Notification::make()->success()
                        ->title('وضعیت تیپاکس: '.($progress?->stage->label() ?? 'نامشخص'))
                        ->body($progress?->status)
                        ->send();
                }),
            // Tipax reports parcels, not items (owner, 2026-11-19): once it says the return was delivered to us, staff count
            // what arrived per row; the refund is then worked out on the received units.
            Action::make('confirmReceived')
                ->label('ثبت دریافت در انبار')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('primary')
                ->visible(fn (): bool => $this->mayUpdate() && $this->orderReturn()->isReceivable())
                ->modalHeading('ثبت دریافت مرجوعی در انبار')
                ->modalDescription('تعداد رسیده از هر ردیف را وارد کنید؛ مبلغ بازگشتی فقط برای کالاهای رسیده محاسبه می‌شود.')
                ->schema(fn (): array => $this->orderReturn()->items->map(fn (OrderReturnItem $row): TextInput => TextInput::make('received.'.$row->getId())
                    ->label(trim($row->getOrderItem()->getProductName().' '.($row->getOrderItem()->getVariantTitle() ?? '')).' · '.$row->getReason()->label())
                    ->helperText('مرجوع‌شده: '.PersianDigits::toPersian((string) $row->getQuantity()))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(0)
                    ->maxValue($row->getQuantity())
                    ->default($row->getQuantity()))->all())
                ->action(function (array $data, ConfirmReturnReceived $confirm): void {
                    try {
                        $confirm($this->orderReturn(), array_map(intval(...), (array) ($data['received'] ?? [])), $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();
                    Notification::make()->success()->title('دریافت در انبار ثبت شد.')->body('مبلغ بازگشتی: '.Toman::format($this->orderReturn()->getRefundTotal()))->send();
                }),
            Action::make('recordRefund')
                ->label('ثبت بازگشت وجه')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                // The same approval as in «بازگشت وجه‌ها» (owner, 2026-11-19): only admins who may approve refunds.
                ->visible(fn (): bool => $this->orderReturn()->isRefundable() && (auth('admin')->user()?->can('Approve:Refund') ?? false))
                ->modalHeading('ثبت بازگشت وجه مرجوعی')
                ->modalDescription(fn (): string => 'وقتی '.Toman::format($this->orderReturn()->getRefundTotal()).' را از بانک یا پنل درگاه به مشتری برگرداندید، شماره پیگیری آن را وارد کنید.')
                ->schema([TextInput::make('reference')->label('شماره پیگیری بازگشت وجه')->required()->maxLength(100)->extraInputAttributes(['dir' => 'ltr'])])
                ->action(function (array $data, RecordReturnRefund $recordRefund): void {
                    try {
                        $recordRefund($this->orderReturn(), (string) $data['reference'], $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();
                    Notification::make()->success()->title('بازگشت وجه ثبت شد.')->send();
                }),
            Action::make('cancel')
                ->label('لغو مرجوعی')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->mayUpdate() && in_array($this->orderReturn()->getStatus(), [OrderReturnStatus::Draft, OrderReturnStatus::Approved], true))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->orderReturn()->getStatus() === OrderReturnStatus::Approved && $this->orderReturn()->needsPickup()
                    ? 'وضعیت از تیپاکس پرسیده می‌شود و جمع‌آوری در تیپاکس هم لغو می‌شود؛ اگر تیپاکس بسته را جمع‌آوری کرده باشد، لغو انجام نمی‌شود.'
                    : 'کالاهای این مرجوعی دوباره قابل مرجوع می‌شوند.')
                ->schema([Textarea::make('reason')->label('دلیل لغو')->maxLength(500)->rows(3)])
                ->action(function (array $data, CancelOrderReturn $cancel): void {
                    try {
                        $cancel($this->orderReturn(), isset($data['reason']) ? (string) $data['reason'] : null, $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();
                    Notification::make()->success()->title('مرجوعی لغو شد.')->send();
                }),
        ];
    }

    private function orderReturn(): OrderReturn
    {
        /** @var OrderReturn */
        return $this->getRecord();
    }

    private function mayUpdate(): bool
    {
        return auth('admin')->user()?->can('update', $this->orderReturn()) ?? false;
    }

    private function adminId(): ?int
    {
        $id = auth('admin')->id();

        return $id === null ? null : (int) $id;
    }
}
