<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\ContactMessages\Pages;

use App\Modules\Content\Actions\ChangeContactMessageStatus;
use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Modules\Content\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One contact message: opening a new one marks it read (for staff who may update messages).
 */
final class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactMessage $message */
        $message = $this->getRecord();

        if (auth('admin')->user()?->can('update', $message) ?? false) {
            app(ChangeContactMessageStatus::class)($message, ContactMessageStatus::Read);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('answered')
                ->label('پاسخ داده شد')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('پاسخ را تلفنی یا با ایمیل داده‌اید؟ پیام «پاسخ داده شد» می‌شود.')
                ->visible(fn (ContactMessage $record): bool => $record->getStatus() !== ContactMessageStatus::Answered
                    && (auth('admin')->user()?->can('update', $record) ?? false))
                ->action(function (ContactMessage $record, ChangeContactMessageStatus $changeStatus): void {
                    $changeStatus($record, ContactMessageStatus::Answered);
                    $this->refreshFormData([ContactMessage::COLUMN_STATUS]);
                }),
        ];
    }
}
