<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Pages;

use App\Modules\Orders\Actions\ApproveOrderReturn;
use App\Modules\Orders\Actions\CancelOrderReturn;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Models\OrderReturn;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * A return's page (S27): «تأیید» registers the pickup with Tipax and texts the customer the code; «لغو مرجوعی» also
 * cancels it at Tipax when approved. Both ask first.
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
                ->label('تأیید و ثبت در تیپاکس')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->mayUpdate() && $this->orderReturn()->getStatus() === OrderReturnStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription('تیپاکس بسته‌ها را از آدرس مشتری جمع‌آوری و به انبار می‌آورد؛ کد رهگیری مرجوعی برای مشتری پیامک می‌شود.')
                ->action(function (ApproveOrderReturn $approve): void {
                    try {
                        $codes = $approve($this->orderReturn(), $this->adminId());
                    } catch (OrderRefused $refused) {
                        Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

                        return;
                    }

                    $this->record = $this->orderReturn()->refresh();
                    Notification::make()->success()->title('مرجوعی در تیپاکس ثبت شد.')->body('کد رهگیری: '.implode('، ', $codes))->persistent()->send();
                }),
            Action::make('cancel')
                ->label('لغو مرجوعی')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->mayUpdate() && $this->orderReturn()->getStatus() !== OrderReturnStatus::Cancelled)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->orderReturn()->getStatus() === OrderReturnStatus::Approved
                    ? 'جمع‌آوری در تیپاکس هم لغو می‌شود؛ اگر تیپاکس بسته را برداشته باشد، لغو انجام نمی‌شود.'
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
