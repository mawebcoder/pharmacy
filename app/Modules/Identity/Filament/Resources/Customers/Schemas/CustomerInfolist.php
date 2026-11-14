<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers\Schemas;

use App\Modules\Identity\Models\Customer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A customer's details (roadmap D3.3). Their orders join this page as a relation manager with the orders step.
 */
final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات مشتری')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        TextEntry::make(Customer::COLUMN_MOBILE)
                            ->label('موبایل')
                            ->copyable()
                            ->extraAttributes(['dir' => 'ltr']),
                        TextEntry::make(Customer::COLUMN_FIRST_NAME)->label('نام')->placeholder('—'),
                        TextEntry::make(Customer::COLUMN_LAST_NAME)->label('نام خانوادگی')->placeholder('—'),
                        TextEntry::make(Customer::COLUMN_EMAIL)->label('ایمیل')->placeholder('—'),
                        TextEntry::make(Customer::COLUMN_NATIONAL_ID)
                            ->label('کد ملی')
                            ->state(fn (Customer $record): ?string => $record->getNationalId())
                            ->placeholder('—'),
                        TextEntry::make(Customer::COLUMN_BIRTH_DATE)
                            ->label('تاریخ تولد')
                            ->jalaliDate() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                            ->placeholder('—'),
                        IconEntry::make(Customer::COLUMN_IS_BLOCKED)
                            ->label('مسدود')
                            ->boolean()
                            ->trueColor('danger')
                            ->falseColor('gray'),
                        TextEntry::make(Customer::COLUMN_LAST_LOGIN_AT)
                            ->label('آخرین ورود')
                            ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                            ->placeholder('—'),
                        TextEntry::make(Customer::COLUMN_CREATED_AT)
                            ->label('عضویت')
                            ->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ]),
            ]);
    }
}
