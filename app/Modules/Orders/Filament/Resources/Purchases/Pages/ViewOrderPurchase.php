<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Purchases\Pages;

use App\Modules\Orders\Actions\ReceiveWholePurchase;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Models\OrderPurchase;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

/**
 * A purchase document's page (S25): «سفارش» goes back to its order; «دریافت کامل همه ردیف‌ها» and the note are for
 * admins who may update it. Rows are added, changed and received in the relation manager.
 */
final class ViewOrderPurchase extends ViewRecord
{
    protected static string $resource = OrderPurchaseResource::class;

    public function getTitle(): string
    {
        return 'سند خرید '.$this->purchase()->getNumber();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('order')
                ->label('سفارش')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->purchase()->getOrderId()])),
            Action::make('receiveAll')
                ->label('دریافت کامل همه ردیف‌ها')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('همه ردیف‌ها به‌طور کامل دریافت‌شده ثبت می‌شوند و کسری‌های ثبت‌شده پاک می‌شوند.')
                ->visible(fn (): bool => $this->mayUpdate() && $this->purchase()->getStatus() === PurchaseStatus::Registered)
                ->action(function (ReceiveWholePurchase $receive): void {
                    $receive($this->purchase());
                    $this->refreshPurchase('همه ردیف‌ها دریافت شد.');
                }),
            Action::make('note')
                ->label('یادداشت')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->mayUpdate())
                ->fillForm(fn (): array => ['note' => $this->purchase()->getNote()])
                ->schema([Textarea::make('note')->label('یادداشت (فقط برای مدیران)')->maxLength(2000)->rows(4)])
                ->action(function (array $data): void {
                    $note = trim((string) ($data['note'] ?? ''));
                    $this->purchase()->setNote($note === '' ? null : $note)->save();
                    $this->refreshPurchase('یادداشت ذخیره شد.');
                }),
        ];
    }

    /**
     * A row changed in the relation manager: show the new status, cost and margin.
     */
    #[On('refresh-purchase')]
    public function refreshHeader(): void
    {
        $this->record = $this->purchase()->refresh();
    }

    private function purchase(): OrderPurchase
    {
        /** @var OrderPurchase */
        return $this->getRecord();
    }

    private function mayUpdate(): bool
    {
        return auth('admin')->user()?->can('update', $this->purchase()) ?? false;
    }

    private function refreshPurchase(string $message): void
    {
        $this->record = $this->purchase()->refresh();
        Notification::make()->success()->title($message)->send();
    }
}
