<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Orders\Actions\CancelOrderByAdmin;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\MarkOrderRefunded;
use App\Modules\Orders\Actions\OpenOrderPurchase;
use App\Modules\Orders\Actions\SaveAdminNote;
use App\Modules\Orders\Actions\SendOrderOnItsWay;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Shared\Support\Toman;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * An order's page (roadmap S24). Each status button shows only when the admin may make that move now (D3.5) and goes
 * through an Orders action, which logs it with the admin as the actor; a refused change is shown as a notification.
 */
final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'سفارش '.$this->order()->getNumber();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purchase')
                ->label('سند خرید')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (): bool => $this->order()->getPurchase() instanceof OrderPurchase
                    && (auth('admin')->user()?->can('view', $this->order()->getPurchase()) ?? false))
                ->url(fn (): string => OrderPurchaseResource::getUrl('view', ['record' => $this->order()->getPurchase()?->getId()])),
            Action::make('openPurchase')
                ->label('ثبت سند خرید')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('primary')
                ->visible(fn (): bool => ! $this->order()->getPurchase() instanceof OrderPurchase
                    && $this->order()->getStatus()->isPurchase()
                    && (auth('admin')->user()?->can('create', OrderPurchase::class) ?? false))
                ->action(function (OpenOrderPurchase $open): void {
                    try {
                        $purchase = $open($this->order(), $this->adminId());
                    } catch (PurchaseRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    $this->redirect(OrderPurchaseResource::getUrl('view', ['record' => $purchase]));
                }),
            $this->move('startProcessing', 'شروع آماده‌سازی', OrderStatus::Processing, Heroicon::OutlinedArchiveBox)
                ->color('primary'),
            Action::make('sendOnItsWay')
                ->label('تحویل به حمل')
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary')
                ->visible(fn (): bool => $this->mayMoveTo(OrderStatus::OnTheWay))
                ->disabled(fn (): bool => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack)
                ->tooltip(fn (): ?string => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack ? 'پس از دریافت کامل سند خرید فعال می‌شود.' : null)
                ->modalHeading('تحویل سفارش به شرکت حمل')
                ->schema([
                    TextInput::make('tracking_code')
                        ->label('کد رهگیری')
                        ->required()
                        ->maxLength(64)
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->helperText('مشتری این کد را در صفحه سفارش و «پیگیری سفارش» می‌بیند.'),
                ])
                ->action(fn (array $data, SendOrderOnItsWay $send) => $this->attempt(fn () => $send($this->order(), (string) $data['tracking_code'], $this->adminId()))),
            $this->move('markDelivered', 'تحویل شد', OrderStatus::Delivered, Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation(),
            Action::make('cancel')
                ->label('لغو سفارش')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->mayMoveTo(OrderStatus::Cancelled))
                ->modalHeading('لغو سفارش')
                ->modalDescription(fn (): ?string => $this->order()->getPaymentStatus() === PaymentStatus::Paid ? 'مبلغ پرداختی «در انتظار بازگشت وجه» می‌شود.' : null)
                ->schema([
                    Textarea::make('reason')->label('دلیل لغو')->required()->maxLength(500)->rows(3),
                ])
                ->action(fn (array $data, CancelOrderByAdmin $cancel) => $this->attempt(fn () => $cancel($this->order(), $this->adminId(), (string) $data['reason']))),
            Action::make('markRefunded')
                ->label('ثبت بازگشت وجه')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning')
                ->visible(fn (): bool => $this->order()->getPaymentStatus() === PaymentStatus::RefundPending
                    && (auth('admin')->user()?->can('refund', $this->order()) ?? false))
                ->modalHeading('ثبت بازگشت وجه')
                ->modalDescription('وقتی مبلغ را از بانک یا پنل درگاه به مشتری برگرداندید، مبلغ کالاها و هزینه ارسالِ برگشتی و شماره پیگیری آن را وارد کنید.')
                ->fillForm(fn (): array => [
                    'items_amount' => $this->defaultItemsRefund(),
                    'shipping_amount' => $this->order()->getShippingTotal(),
                ])
                ->schema([
                    TextInput::make('items_amount')
                        ->label('مبلغ بازگشتی کالاها')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->required()
                        ->maxValue(fn (): int => Toman::fromRial($this->order()->getItemsPaidTotal()))
                        ->helperText(fn (): string => 'پرداختی مشتری برای کالاها (پس از تخفیف کوپن): '.Toman::format($this->order()->getItemsPaidTotal())
                            .($this->order()->getDeliveredFreeShipping() > 0
                                ? '؛ ارسال رایگانِ این سفارش ('.Toman::format($this->order()->getDeliveredFreeShipping()).') چون تحویل شده بود کسر شده است.'
                                : '')),
                    TextInput::make('shipping_amount')
                        ->label('مبلغ بازگشتی هزینه ارسال')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->required()
                        ->maxValue(fn (): int => Toman::fromRial($this->order()->getShippingTotal()))
                        ->helperText(fn (): string => 'هزینه ارسال پرداختی: '.Toman::format($this->order()->getShippingTotal())),
                    TextInput::make('reference')->label('شماره پیگیری بازگشت وجه')->required()->maxLength(64)->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->action(fn (array $data, MarkOrderRefunded $refund) => $this->attempt(fn () => $refund(
                    $this->order(),
                    (string) $data['reference'],
                    self::refundRial($data['items_amount'] ?? null, [$this->order()->getItemsPaidTotal(), $this->defaultItemsRefund()]),
                    self::refundRial($data['shipping_amount'] ?? null, [$this->order()->getShippingTotal()]),
                ))),
            Action::make('adminNote')
                ->label('یادداشت مدیر')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->mayUpdate())
                ->fillForm(fn (): array => ['note' => $this->order()->getAdminNote()])
                ->schema([
                    Textarea::make('note')->label('یادداشت (فقط برای مدیران)')->maxLength(2000)->rows(4),
                ])
                ->action(fn (array $data, SaveAdminNote $save) => $this->attempt(fn () => $save($this->order(), $data['note'] ?? null), 'یادداشت ذخیره شد.')),
        ];
    }

    /**
     * The product refund the form starts with: what was paid for the products, less the free shipping the store paid
     * for when the order had been delivered (owner, 2026-11-16).
     */
    private function defaultItemsRefund(): int
    {
        return max(0, $this->order()->getItemsPaidTotal() - $this->order()->getDeliveredFreeShipping());
    }

    /**
     * Refund amounts are typed in whole toman while what was paid is in rial: typing a suggested amount's toman means
     * that exact rial amount (12,345,675 rial shows as 1,234,568 toman, which would otherwise read 5 rial too much).
     *
     * @param  list<int>  $suggested  rial amounts the form offers
     */
    private static function refundRial(int|string|null $typed, array $suggested): int
    {
        $rial = (int) $typed;

        foreach ($suggested as $amount) {
            if (Toman::fromRial($rial) === Toman::fromRial($amount)) {
                return $amount;
            }
        }

        return $rial;
    }

    /**
     * A status button without a form.
     */
    private function move(string $name, string $label, OrderStatus $to, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (): bool => $this->mayMoveTo($to))
            ->action(fn (ChangeOrderStatus $change) => $this->attempt(fn () => $change($this->order(), $to, OrderActor::Admin, $this->adminId())));
    }

    private function mayMoveTo(OrderStatus $to): bool
    {
        return $this->mayUpdate() && $this->order()->getStatus()->canMoveTo($to, OrderActor::Admin);
    }

    private function mayUpdate(): bool
    {
        return auth('admin')->user()?->can('update', $this->order()) ?? false;
    }

    /**
     * Runs the change, shows the result and reloads the page's data. Filament refuses to run a hidden action, so each
     * button's visibility (permission and allowed move) also guards its call.
     */
    private function attempt(Closure $change, string $success = 'وضعیت سفارش به‌روز شد.'): void
    {
        try {
            $change();
        } catch (OrderRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();

            return;
        }

        $this->record = $this->order()->refresh();
        Notification::make()->success()->title($success)->send();
    }

    private function order(): Order
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return $order;
    }

    private function adminId(): int
    {
        return (int) auth('admin')->id();
    }
}
