<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Filament\Resources\Menus\MenuResource;
use App\Modules\Content\Filament\Resources\Menus\Pages\EditMenu;
use App\Modules\Content\Filament\Resources\Menus\RelationManagers\MenuItemsRelationManager;
use App\Modules\Content\Models\Menu;
use App\Modules\Content\Models\MenuItem;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Queries\MenuLinks;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| Menus (roadmap S34; owner 2026-11-17): «دسته‌بندی کالاها» stays built from the categories; the header links beside
| it, the three footer columns and the phone and tablet menu are edited in «منوها». An item picks its target like a
| banner does and is left out while that target is gone or hidden.
*/

function menuLabels(MenuLocation $location): array
{
    return array_column(app(MenuLinks::class)->for($location)['links'], 'label');
}

function menuOf(MenuLocation $location): Menu
{
    return Menu::query()->where(Menu::COLUMN_LOCATION, $location)->sole();
}

it('starts with the links the header and footer had, leaving out pages still in draft', function (): void {
    expect(menuLabels(MenuLocation::Header))->toBe(['همه کالاها', 'برندها', 'تماس با ما'])
        ->and(app(MenuLinks::class)->for(MenuLocation::Footer1)['title'])->toBe('راهنمای خرید')
        ->and(menuLabels(MenuLocation::Footer1))->toBe(['پرسش‌های متداول'])
        ->and(menuLabels(MenuLocation::Footer3))->toBe(['پیگیری سفارش'])
        ->and(menuLabels(MenuLocation::Mobile))->toBe(['همه کالاها', 'برندها', 'پیشنهادهای لحظه‌ای', 'پیگیری سفارش', 'پرسش‌های متداول', 'تماس با ما']);
});

it('shows a page link once the page is published, at its current address', function (): void {
    menuLabels(MenuLocation::Header);
    $about = Page::query()->where(Page::COLUMN_SLUG, 'about')->sole();

    $about->setIsActive(true)->setSlug('about-us')->save();

    $links = app(MenuLinks::class)->for(MenuLocation::Header)['links'];

    expect(array_column($links, 'label'))->toBe(['همه کالاها', 'برندها', 'درباره ما', 'تماس با ما'])
        ->and($links[2]['href'])->toBe(route('pages.show', 'about-us'));
});

it('leaves out an item that is switched off or whose brand is hidden', function (): void {
    $brand = Brand::factory()->create();
    MenuItem::factory()->create([MenuItem::COLUMN_TITLE => 'سینره', MenuItem::COLUMN_LINK_TYPE => LinkType::Brand, MenuItem::COLUMN_LINK_ID => $brand->getId()]);
    MenuItem::factory()->inactive()->create([MenuItem::COLUMN_TITLE => 'خاموش']);

    expect(menuLabels(MenuLocation::Header))->toContain('سینره')->not->toContain('خاموش');

    $brand->setIsActive(false)->save();
    MenuLinks::forget();

    expect(menuLabels(MenuLocation::Header))->not->toContain('سینره');
});

it('renders the menus on every page: header with icons, footer columns and the phone menu', function (): void {
    MenuItem::factory()->external()->create([MenuItem::COLUMN_MENU_ID => menuOf(MenuLocation::Footer2)->getId(), MenuItem::COLUMN_TITLE => 'اینستاگرام']);

    $this->get(route('faq'))
        ->assertOk()
        ->assertSeeInOrder(['همه کالاها', 'برندها', 'تماس با ما'])
        ->assertSee(['راهنمای خرید', 'داروخانه', 'قوانین', 'پیشنهادهای لحظه‌ای', 'aria-controls="site-menu"'], false)
        ->assertSeeInOrder(['href="https://instagram.com/hutana"', 'target="_blank" rel="noopener"', 'اینستاگرام'], false);
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('adds a link with an icon to the end of a menu, pointing at a picked page', function (): void {
        $menu = menuOf(MenuLocation::Header);
        $terms = Page::query()->where(Page::COLUMN_SLUG, 'terms')->sole();
        $terms->setIsActive(true)->save();

        livewire(MenuItemsRelationManager::class, ['ownerRecord' => $menu, 'pageClass' => EditMenu::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                MenuItem::COLUMN_TITLE => 'قوانین',
                MenuItem::COLUMN_ICON => MenuIcon::Document->value,
                MenuItem::COLUMN_LINK_TYPE => LinkType::Page->value,
                'link_page_id' => $terms->getId(),
            ])
            ->assertHasNoFormErrors();

        $links = app(MenuLinks::class)->for(MenuLocation::Header)['links'];

        expect(end($links))->toBe(['label' => 'قوانین', 'href' => $terms->url(), 'icon' => 'document-text', 'newTab' => false]);
    });

    it('reorders a menu by drag and drop', function (): void {
        $menu = menuOf(MenuLocation::Header);
        $ids = $menu->items()->pluck('id')->all();

        livewire(MenuItemsRelationManager::class, ['ownerRecord' => $menu, 'pageClass' => EditMenu::class])
            ->call('reorderTable', array_reverse($ids));

        expect(menuLabels(MenuLocation::Header))->toBe(['تماس با ما', 'برندها', 'همه کالاها']);
    });

    it('renames a footer column', function (): void {
        livewire(EditMenu::class, ['record' => menuOf(MenuLocation::Footer3)->getId()])
            ->fillForm([Menu::COLUMN_TITLE => 'قوانین و مقررات'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(MenuLinks::class)->for(MenuLocation::Footer3)['title'])->toBe('قوانین و مقررات');
    });

    it('keeps the menus from operators', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin')
            ->get(MenuResource::getUrl('index'))
            ->assertForbidden();
    });
});
