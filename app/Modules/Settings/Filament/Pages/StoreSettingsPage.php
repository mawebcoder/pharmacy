<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Pages;

use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «تنظیمات فروشگاه» (roadmap D3.6, started in S19; S35 adds contact details, logo, maintenance mode …).
 *
 * @property-read Schema $form
 */
final class StoreSettingsPage extends Page
{
    use HasPageShield;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'تنظیمات فروشگاه';

    protected static ?string $title = 'تنظیمات فروشگاه';

    protected static ?string $slug = 'store-settings';

    public function mount(StoreSettings $settings): void
    {
        $this->form->fill([
            'max_quantity_per_order' => $settings->maxQuantityPerOrder(),
            'free_shipping_threshold' => $settings->freeShippingThreshold(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('سبد خرید')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_quantity_per_order')
                            ->label('حداکثر تعداد هر کالا در یک سفارش')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(999)
                            ->required()
                            ->helperText('وقتی نه تنوع (SKU) و نه محصول حداکثری تعیین کرده باشد، همین عدد اعمال می‌شود.'),
                        TextInput::make('free_shipping_threshold')
                            ->label('حد ارسال رایگان')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('سفارش‌های از این مبلغ به بالا رایگان ارسال می‌شوند؛ صفر یعنی بدون ارسال رایگان. در سبد خرید و نوار اطلاعیه نمایش داده می‌شود.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('ذخیره')->submit('save')])->key('form-actions'),
                ]),
        ]);
    }

    public function save(SaveStoreSettings $saveSettings): void
    {
        $data = $this->form->getState();

        $saveSettings([
            StoreSettings::MAX_QUANTITY_PER_ORDER => (int) $data['max_quantity_per_order'],
            StoreSettings::FREE_SHIPPING_THRESHOLD => (int) $data['free_shipping_threshold'],
        ]);

        Notification::make()->success()->title('تنظیمات ذخیره شد.')->send();
    }
}
