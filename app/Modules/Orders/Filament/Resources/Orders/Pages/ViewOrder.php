<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Checkout\Actions\EditPaidOrder;
use App\Modules\Checkout\Actions\ReviseUnpaidOrder;
use App\Modules\Checkout\Data\PaidOrderEditData;
use App\Modules\Checkout\Data\ReviseOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Orders\Actions\CancelOrderByAdmin;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\OpenOrderPurchase;
use App\Modules\Orders\Actions\SaveAdminNote;
use App\Modules\Orders\Actions\SendOrderOnItsWay;
use App\Modules\Orders\Actions\ShipOrderWithTipax;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Schemas\PanelOrderForm;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderInvoice;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Orders\Queries\PaidEditableOrders;
use App\Modules\Orders\Support\PrintFrame;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
            // Status changes stay separate buttons, each confirmed (owner, 2026-11-19).
            $this->move('startProcessing', 'شروع آماده‌سازی', OrderStatus::Processing, Heroicon::OutlinedArchiveBox)
                ->color('primary'),
            // Registered with Tipax, which gives the tracking code (S35, owner 2026-11-19); typing it by hand is in the menu.
            Action::make('sendOnItsWay')
                ->label('تحویل به حمل')
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary')
                ->visible(fn (): bool => $this->mayMoveTo(OrderStatus::OnTheWay))
                ->disabled(fn (): bool => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack)
                ->tooltip(fn (): ?string => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack ? 'پس از دریافت کامل سند خرید فعال می‌شود.' : null)
                ->requiresConfirmation()
                ->modalHeading('ثبت در تیپاکس و تحویل به حمل')
                ->modalDescription('بسته‌های این سفارش در تیپاکس ثبت می‌شوند و کد رهگیری برای مشتری پیامک می‌شود.')
                ->action(function (ShipOrderWithTipax $ship): void {
                    try {
                        $codes = $ship($this->order(), $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('در تیپاکس ثبت شد.')->body('کد رهگیری: '.implode('، ', $codes))->persistent()->send();
                }),
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
            // Everything else in one menu behind three dots (owner, 2026-11-19), in sections: shipping, purchase, other.
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('sendOnItsWayManually')
                        ->label('ثبت دستی کد رهگیری')
                        ->icon(Heroicon::OutlinedTruck)
                        ->color('gray')
                        ->visible(fn (): bool => $this->mayMoveTo(OrderStatus::OnTheWay))
                        ->disabled(fn (): bool => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack)
                        ->tooltip(fn (): ?string => $this->order()->getSourcingStatus() !== SourcingStatus::ReadyToPack ? 'پس از دریافت کامل سند خرید فعال می‌شود.' : null)
                        ->modalHeading('تحویل به حمل با کد رهگیری دستی')
                        ->schema([
                            TextInput::make('tracking_code')
                                ->label('کد رهگیری')
                                ->required()
                                ->maxLength(64)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->helperText('مشتری این کد را در صفحه سفارش و «پیگیری سفارش» می‌بیند.'),
                        ])
                        ->action(fn (array $data, SendOrderOnItsWay $send) => $this->attempt(fn () => $send($this->order(), (string) $data['tracking_code'], $this->adminId()))),
                    // «مرجوعی‌ها» (S27, owner 2026-11-19): opens a new return for this order; the items and units are picked there.
                    Action::make('createReturn')
                        ->label('ثبت مرجوعی')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('warning')
                        ->visible(fn (OrderReturns $returns): bool => (auth('admin')->user()?->can('create', OrderReturn::class) ?? false) && $returns->canReturn($this->order()))
                        ->url(fn (): string => OrderReturnResource::getUrl('create', ['order' => $this->order()->getId()])),
                    // An unpaid panel order edited by staff (owner, 2026-11-19): priced again, the new link texted.
                    Action::make('editUnpaid')
                        ->label('ویرایش سفارش')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('gray')
                        ->visible(fn (): bool => $this->mayUpdate() && $this->order()->canBePaid() && $this->order()->getSource() === OrderSource::Panel)
                        ->modalHeading('ویرایش سفارش پرداخت‌نشده')
                        ->modalDescription('قیمت‌ها و هزینه ارسال دوباره حساب می‌شوند، لینک پرداخت قبلی بسته می‌شود و لینک تازه برای مشتری پیامک می‌شود. مهلت پرداخت تغییر نمی‌کند.')
                        ->fillForm(fn (): array => [
                            'address_id' => $this->order()->getAddressId(),
                            'items' => $this->order()->items->map(fn ($item): array => ['variant_id' => $item->getVariantId(), 'quantity' => $item->getQuantity()])->values()->all(),
                            'manual_discount' => $this->order()->getManualDiscount(),
                            'manual_discount_reason' => $this->order()->getManualDiscountReason(),
                        ])
                        ->schema(fn (): array => PanelOrderForm::editFields($this->order()->getCustomerId()))
                        ->action(function (array $data, ReviseUnpaidOrder $revise): void {
                            $quantities = [];

                            foreach ((array) ($data['items'] ?? []) as $row) {
                                $variantId = (int) ($row['variant_id'] ?? 0);
                                $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) ($row['quantity'] ?? 0);
                            }

                            try {
                                $revised = $revise(new ReviseOrderData(
                                    orderId: $this->order()->getId(),
                                    addressId: (int) $data['address_id'],
                                    quantities: $quantities,
                                    adminId: $this->adminId(),
                                    manualDiscount: (int) ($data['manual_discount'] ?? 0),
                                    manualDiscountReason: isset($data['manual_discount_reason']) ? (string) $data['manual_discount_reason'] : null,
                                ));
                            } catch (CheckoutRefused $refused) {
                                Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

                                return;
                            }

                            $this->record = $this->order()->refresh();
                            Notification::make()->success()
                                ->title('سفارش ویرایش شد؛ مبلغ تازه '.Toman::format($revised->total).' و لینک پرداخت پیامک شد.')
                                ->body($revised->couponDropped === null ? null : 'کد تخفیف حذف شد: '.$revised->couponDropped)
                                ->send();
                        }),
                    // A paid order edited before it goes out (owner, 2026-11-19): items, units and address; the difference
                    // is texted as a one-hour link, or opens a refund record.
                    Action::make('editPaid')
                        ->label('ویرایش سفارش')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('gray')
                        ->visible(fn (): bool => $this->mayUpdate() && PaidEditableOrders::editable($this->order()))
                        ->modalHeading('ویرایش سفارش پرداخت‌شده')
                        ->modalDescription('کالاهای مانده با همان مبلغی که مشتری پرداخته حساب می‌شوند، کالای تازه با قیمت امروز، و هزینه ارسال دوباره حساب می‌شود. اگر مبلغ بیشتر شود، لینک پرداخت تفاوت (یک ساعته) پیامک می‌شود و سفارش تا پرداخت ارسال نمی‌شود؛ اگر کمتر شود، بازگشت وجه در انتظار تأیید ثبت می‌شود.')
                        ->fillForm(fn (): array => [
                            'address_id' => $this->order()->getAddressId(),
                            'items' => $this->order()->items->map(fn ($item): array => ['variant_id' => $item->getVariantId(), 'quantity' => $item->getQuantity()])->values()->all(),
                        ])
                        ->schema(fn (): array => PanelOrderForm::editFields($this->order()->getCustomerId(), withDiscount: false))
                        ->action(function (array $data, EditPaidOrder $editPaid): void {
                            $quantities = [];

                            foreach ((array) ($data['items'] ?? []) as $row) {
                                $variantId = (int) ($row['variant_id'] ?? 0);
                                $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) ($row['quantity'] ?? 0);
                            }

                            try {
                                $edited = $editPaid(new PaidOrderEditData($this->order()->getId(), (int) $data['address_id'], $quantities, $this->adminId()));
                            } catch (CheckoutRefused $refused) {
                                Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

                                return;
                            }

                            $this->record = $this->order()->refresh();
                            Notification::make()->success()
                                ->title('سفارش ویرایش شد؛ مبلغ تازه '.Toman::format($edited->total).'.')
                                ->body(match (true) {
                                    $edited->customerOwes > 0 => 'مشتری باید '.Toman::format($edited->customerOwes).' پرداخت کند؛ لینک یک‌ساعته پیامک شد.',
                                    $edited->refundOpened > 0 => 'بازگشت وجه '.Toman::format($edited->refundOpened).' در انتظار تأیید ثبت شد.',
                                    default => null,
                                })
                                ->persistent()
                                ->send();
                        }),
                    // An unpaid order's payment link texted to the customer again (owner, 2026-11-19); the difference owed
                    // after an edit gets another hour.
                    Action::make('resendPaymentLink')
                        ->label('ارسال دوباره لینک پرداخت')
                        ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->color('gray')
                        ->visible(fn (): bool => $this->mayUpdate() && ($this->order()->canBePaid() || $this->order()->getDifferenceDue() > 0))
                        ->requiresConfirmation()
                        ->modalDescription('لینک پرداخت این سفارش برای مشتری پیامک می‌شود.')
                        ->action(function (): void {
                            if ($this->order()->getDifferenceDue() > 0 && ! $this->order()->canPayDifference()) {
                                $this->order()->setDifferenceDueUntil(now()->addMinutes(EditPaidOrder::DIFFERENCE_LINK_MINUTES))->save();
                            }

                            OrderPaymentLinkRequested::dispatch($this->order()->getId());
                            Notification::make()->success()->title('لینک پرداخت برای مشتری پیامک شد.')->send();
                        }),
                    // «فاکتور فروش» with the tracking code (owner, 2026-11-19): opens the print dialog, no new tab.
                    Action::make('printInvoice')
                        ->label('چاپ فاکتور')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->color('gray')
                        ->visible(fn (): bool => OrderInvoice::isAvailable($this->order()))
                        ->alpineClickHandler(fn (): string => PrintFrame::script(route('orders.invoice.print', $this->order()->getId()))),
                    // Tipax's parcel status, asked when the admin clicks (owner, 2026-11-19); the answer also reaches the customer's page.
                    Action::make('trackParcel')
                        ->label('استعلام از تیپاکس')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->color('gray')
                        ->visible(fn (): bool => filled($this->order()->getTrackingCode())
                            && (auth('admin')->user()?->can('view', $this->order()) ?? false))
                        ->action(function (ParcelTrackingStatus $parcels): void {
                            try {
                                $parcel = $parcels->checkNow($this->order()->getId(), (string) $this->order()->getTrackingCode());
                            } catch (TipaxRequestFailed $failed) {
                                Notification::make()->danger()->title('تیپاکس پاسخ نداد.')->body($failed->getMessage())->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('وضعیت مرسوله: '.($parcel->status ?? 'نامشخص'))
                                ->body(implode(' · ', array_filter([
                                    $parcel->destinationCity === null ? null : 'مقصد: '.$parcel->destinationCity,
                                    $parcel->branch === null ? null : 'نمایندگی: '.$parcel->branch,
                                    $parcel->deliveredAt === null ? null : 'تحویل: '.$parcel->deliveredAt,
                                ])) ?: null)
                                ->persistent()
                                ->send();
                        }),
                ])->dropdown(false),
                ActionGroup::make([
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
                ])->dropdown(false),
                ActionGroup::make([
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
                ])->dropdown(false),
            ])
                ->label('عملیات')
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray'),
        ];
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
            ->requiresConfirmation()
            ->modalDescription('وضعیت سفارش به «'.$to->label().'» تغییر می‌کند.')
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
