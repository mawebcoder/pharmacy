<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Forms;

use App\Modules\Shipping\Actions\SaveVariantBoxCapacities;
use App\Modules\Shipping\Queries\VariantBoxCapacities;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * «جا در کارتن‌ها» on the SKU forms (roadmap S31, owner 2026-11-18): for every available box, how many pieces of this
 * SKU fit it; empty means it does not fit. At least one box is required, as the packer has nothing else to go by.
 * «کپی از تنوع دیگر» fills the numbers from a similar SKU. Saved through Shipping's SaveVariantBoxCapacities.
 */
final class BoxCapacityFields
{
    public const string STATE = 'box_capacities';

    private const string COPY_FROM = 'box_capacities_copy_from';

    public static function section(): Section
    {
        $boxes = app(VariantBoxCapacities::class)->boxOptions();
        $fields = [];

        $firstBox = array_key_first($boxes);

        foreach ($boxes as $boxId => $label) {
            $fields[] = TextInput::make(self::STATE.'.'.$boxId)
                ->label($label)
                // At least one box: the first is required while every box is empty.
                ->required(fn (Get $get): bool => $boxId === $firstBox && ! self::anyFilled($get, array_keys($boxes)))
                ->validationMessages(['required' => 'دست‌کم برای یک کارتن بنویسید چند عدد از این تنوع در آن جا می‌شود.'])
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(9999)
                ->suffix('عدد')
                ->placeholder('جا نمی‌شود');
        }

        return Section::make('جا در کارتن‌ها')
            ->description('از این تنوع چند عدد در هر کارتن جا می‌شود؟ خالی یعنی در آن کارتن جا نمی‌شود. دست‌کم یک کارتن لازم است؛ بسته‌بندی سفارش از همین عددها و وزن حساب می‌شود.')
            ->columns(['default' => 2, 'md' => 3, 'xl' => 4])
            ->schema([
                ImageSelect::skus(Select::make(self::COPY_FROM))
                    ->label('کپی از تنوع دیگر')
                    ->placeholder('یک تنوع مشابه انتخاب کنید')
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state) use ($boxes): void {
                        if (blank($state)) {
                            return;
                        }

                        $copied = app(VariantBoxCapacities::class)->for([(int) $state])[(int) $state] ?? [];

                        foreach (array_keys($boxes) as $boxId) {
                            $set(self::STATE.'.'.$boxId, $copied[$boxId] ?? null);
                        }
                    })
                    ->columnSpanFull(),
                ...$fields,
            ]);
    }

    /**
     * @param  list<int>  $boxIds
     */
    private static function anyFilled(Get $get, array $boxIds): bool
    {
        foreach ($boxIds as $boxId) {
            if ((int) $get(self::STATE.'.'.$boxId) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The form state of a SKU's numbers, for editing.
     *
     * @return array<int, int>
     */
    public static function state(int $variantId): array
    {
        return app(VariantBoxCapacities::class)->for([$variantId])[$variantId] ?? [];
    }

    /**
     * Saves the numbers typed for every available box (an empty box removes the SKU from it).
     *
     * @param  array<string, mixed>  $data  the SKU form state
     */
    public static function save(int $variantId, array $data): void
    {
        $typed = (array) ($data[self::STATE] ?? []);
        $capacities = [];

        foreach (array_keys(app(VariantBoxCapacities::class)->boxOptions()) as $boxId) {
            $count = $typed[$boxId] ?? null;
            $capacities[$boxId] = blank($count) ? null : (int) $count;
        }

        app(SaveVariantBoxCapacities::class)($variantId, $capacities);
    }
}
