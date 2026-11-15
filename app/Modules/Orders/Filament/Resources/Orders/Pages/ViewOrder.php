<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Orders\Actions\CancelOrderByAdmin;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\MarkOrderRefunded;
use App\Modules\Orders\Actions\SaveAdminNote;
use App\Modules\Orders\Actions\SendOrderOnItsWay;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\Order;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * An order's page (roadmap S29). Each status button shows only when the admin may make that move now (D3.5) and goes
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
            $this->move('startProcessing', 'شروع آماده‌سازی', OrderStatus::Processing, Heroicon::OutlinedArchiveBox)
                ->color('primary'),
            Action::make('sendOnItsWay')
                ->label('تحویل به حمل')
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary')
                ->visible(fn (): bool => $this->mayMoveTo(OrderStatus::OnTheWay))
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
                ->modalDescription('وقتی مبلغ را از بانک یا پنل درگاه به مشتری برگرداندید، شماره پیگیری آن را وارد کنید.')
                ->schema([
                    TextInput::make('reference')->label('شماره پیگیری بازگشت وجه')->required()->maxLength(64)->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->action(fn (array $data, MarkOrderRefunded $refund) => $this->attempt(fn () => $refund($this->order(), (string) $data['reference']))),
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
