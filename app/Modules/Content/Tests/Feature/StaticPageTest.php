<?php

declare(strict_types=1);

use App\Modules\Content\Actions\SaveBanner;
use App\Modules\Content\Actions\SavePage;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Data\PageData;
use App\Modules\Content\Database\Seeders\DraftPagesSeeder;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Filament\Resources\Pages\PageResource;
use App\Modules\Content\Filament\Resources\Pages\Pages\CreatePage;
use App\Modules\Content\Filament\Resources\Pages\Pages\EditPage;
use App\Modules\Content\Jobs\ConvertPageImages;
use App\Modules\Content\Models\Banner;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Queries\PlaceBanners;
use App\Modules\Content\Support\PageContent;
use App\Modules\Content\Support\PageHtml;
use App\Modules\Content\Support\PageImages;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Starting data the migrations no longer insert (owner, 2026-11-17).
    $this->seed(DraftPagesSeeder::class);
});

function pageAdmin(AdminRole $role): AdminUser
{
    return AdminUser::factory()->withTwoFactorAuthentication()->withRole($role)->create();
}

describe('the eight footer pages', function (): void {
    it('exist as unpublished drafts with their addresses', function (): void {
        $slugs = Page::query()->orderBy(Page::COLUMN_ID)->pluck(Page::COLUMN_SLUG)->all();

        expect($slugs)->toBe(['about', 'terms', 'privacy', 'returns', 'shipping', 'payment-methods', 'buying-guide', 'licenses'])
            ->and(Page::query()->active()->count())->toBe(0);
        $this->get('/pages/about')->assertNotFound();
    });
});

describe('cleaning the body', function (): void {
    it('removes scripts, event handlers, unsafe URLs and tags the editor does not make', function (string $dirty, string $mustNotContain): void {
        expect((string) PageHtml::clean('<p>متن</p>'.$dirty))->not->toContain($mustNotContain)->toContain('<p>متن</p>');
    })->with([
        'script tag' => ['<script>alert(1)</script>', 'alert'],
        'event handler' => ['<p onclick="alert(1)">کلیک</p>', 'onclick'],
        'image onerror' => ['<img src="x" onerror="alert(1)">', 'onerror'],
        'javascript link' => ['<a href="javascript:alert(1)">لینک</a>', 'javascript:'],
        'data image' => ['<img src="data:image/png;base64,AAAA">', 'data:'],
        'iframe' => ['<iframe src="https://evil.test"></iframe>', 'iframe'],
        'form' => ['<form action="https://evil.test"><input name="x"></form>', '<input'],
        'style overlay' => ['<p style="position:fixed;top:0">x</p>', 'position'],
        'encoded script' => ['&lt;script&gt;alert(1)&lt;/script&gt;', '<script'],
    ]);

    it('keeps headings, lists, tables, safe links and text alignment', function (): void {
        $clean = (string) PageHtml::clean('<h2 style="text-align: center; color: red">ارسال</h2><ul><li>تیپاکس</li></ul><table><tbody><tr><td>۱</td></tr></tbody></table><a href="https://example.test">راهنما</a>');

        expect($clean)->toContain('<h2 style="text-align: center">ارسال</h2>')
            ->toContain('<li>تیپاکس</li>')
            ->toContain('<td>۱</td>')
            ->toContain('href="https://example.test"')
            ->toContain('rel="noopener noreferrer"')
            ->not->toContain('color');
    });

    it('cleans the body on save and strips tags from the title and SEO texts', function (): void {
        $page = app(SavePage::class)(PageData::fromArray([
            Page::COLUMN_TITLE => '<b>شیوه ارسال</b>',
            Page::COLUMN_SLUG => 'Delivery',
            Page::COLUMN_BODY => '<p>متن</p><script>alert(1)</script>',
            Page::COLUMN_IS_ACTIVE => true,
            Page::COLUMN_SEO_DESCRIPTION => '<img src=x onerror=alert(1)>توضیح',
        ]));

        expect($page->getTitle())->toBe('شیوه ارسال')
            ->and($page->getSlug())->toBe('delivery')
            ->and($page->getBody())->toBe('<p>متن</p>')
            ->and($page->getSeoDescription())->toBe('توضیح');
    });

    it('cleans the stored body again when the page is shown', function (): void {
        $page = Page::factory()->create([Page::COLUMN_SLUG => 'guide']);
        DB::table('pages')->where('id', $page->getId())->update(['body' => '<p>امن</p><p onmouseover="alert(1)">x</p><script>alert(2)</script>']);

        $this->get('/pages/guide')
            ->assertOk()
            ->assertSee('امن')
            ->assertDontSee('onmouseover', false)
            ->assertDontSee('alert(2)', false);
    });
});

describe('the storefront page', function (): void {
    it('shows an active page with its title, body, breadcrumb and SEO tags', function (): void {
        Page::factory()->create([
            Page::COLUMN_TITLE => 'شیوه‌های ارسال & هزینه',
            Page::COLUMN_SLUG => 'shipping-costs',
            Page::COLUMN_BODY => '<h2>ارسال با تیپاکس</h2><p>دو تا سه روز کاری.</p>',
            Page::COLUMN_SEO_TITLE => 'هزینه ارسال',
            Page::COLUMN_SEO_DESCRIPTION => 'زمان و هزینه ارسال "سفارش‌ها"',
        ]);

        $this->get('/pages/shipping-costs')
            ->assertOk()
            ->assertSee('<title>هزینه ارسال | ', false)
            ->assertSee('<meta name="description" content="زمان و هزینه ارسال &quot;سفارش‌ها&quot;">', false)
            ->assertSee('شیوه‌های ارسال &amp; هزینه', false)
            ->assertSee('<h2>ارسال با تیپاکس</h2>', false)
            ->assertSee('مسیر صفحه');
    });

    it('is not found when inactive, deleted or unknown', function (string $state): void {
        $page = Page::factory()->create([Page::COLUMN_SLUG => $state === 'unknown' ? 'other' : 'gone']);

        match ($state) {
            'inactive' => $page->setIsActive(false)->save(),
            'deleted' => $page->delete(),
            default => null,
        };

        $this->get('/pages/gone')->assertNotFound();
    })->with(['inactive', 'deleted', 'unknown']);

    it('sends its old address to the new one when its slug changes (S38)', function (): void {
        Page::factory()->create([Page::COLUMN_SLUG => 'gone'])->setSlug('elsewhere')->save();

        $this->get('/pages/gone')->assertStatus(301)->assertRedirect('/pages/elsewhere');
    });
});

describe('banners linking to a page', function (): void {
    it('follow the page address and hide while the page is unpublished', function (): void {
        $page = Page::factory()->create([Page::COLUMN_SLUG => 'guide']);
        $banner = Banner::factory()->linkingTo(LinkType::Page, $page->getId())->create();

        expect($banner->linkUrl())->toBe(url('/pages/guide'));

        $page->setSlug('buying-guide-2')->save();
        expect($banner->refresh()->linkUrl())->toBe(url('/pages/buying-guide-2'));

        $page->setIsActive(false)->save();
        expect($banner->refresh()->linkUrl())->toBeNull();

        $slider = HomeWidget::factory()->ofType(HomeWidgetType::Slider)->create();
        DB::table('banner_placements')->insert(['banner_id' => $banner->getId(), 'place' => BannerPlace::HomeWidget->value, 'place_id' => $slider->getId(), 'sort' => 0, 'created_at' => now(), 'updated_at' => now()]);
        expect(app(PlaceBanners::class)->for(BannerPlace::HomeWidget, $slider->getId()))->toBeEmpty();
    });

    it('cannot be saved without an existing page', function (): void {
        $data = BannerData::fromArray([Banner::COLUMN_TITLE => 'راهنما', Banner::COLUMN_LINK_TYPE => LinkType::Page->value, Banner::COLUMN_LINK_ID => 999_999]);

        expect(fn (): Banner => app(SaveBanner::class)($data))->toThrow(BannerChangeRefused::class);
    });
});

describe('the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('lets a content editor create a page with an address taken from the title', function (): void {
        $this->actingAs(pageAdmin(AdminRole::ContentEditor), 'admin');

        livewire(CreatePage::class)
            ->fillForm([Page::COLUMN_TITLE => 'راهنمای خرید عمده'])
            ->assertSchemaStateSet([Page::COLUMN_SLUG => 'راهنمای-خرید-عمده'])
            ->fillForm([Page::COLUMN_SLUG => 'wholesale', Page::COLUMN_IS_ACTIVE => true])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Page::query()->where(Page::COLUMN_SLUG, 'wholesale')->sole()->isActive())->toBeTrue();
    });

    it('refuses a taken or malformed address', function (string $slug): void {
        $this->actingAs(pageAdmin(AdminRole::ContentEditor), 'admin');

        livewire(CreatePage::class)
            ->fillForm([Page::COLUMN_TITLE => 'درباره', Page::COLUMN_SLUG => $slug])
            ->call('create')
            ->assertHasFormErrors([Page::COLUMN_SLUG]);
    })->with([
        'taken by a draft' => ['about'],
        'spaces' => ['about us'],
        'slashes' => ['a/b'],
        'trailing dash' => ['about-'],
    ]);

    it('edits a page and publishes it', function (): void {
        $this->actingAs(pageAdmin(AdminRole::ContentEditor), 'admin');
        $page = Page::query()->where(Page::COLUMN_SLUG, 'about')->sole();

        livewire(EditPage::class, ['record' => $page->getKey()])
            ->fillForm([Page::COLUMN_BODY => '<p>داروخانه آنلاین از ۱۴۰۵.</p>', Page::COLUMN_IS_ACTIVE => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/pages/about')->assertOk()->assertSee('داروخانه آنلاین از ۱۴۰۵.');
    });

    it('keeps the page list from operators', function (): void {
        $this->actingAs(pageAdmin(AdminRole::Operator), 'admin')
            ->get(PageResource::getUrl('index'))
            ->assertForbidden();
    });
});

describe('page images', function (): void {
    beforeEach(function (): void {
        Storage::fake(PageContent::disk());
        Storage::disk(PageContent::disk())->put('pages/photo.png', testImage(400, 300)->getContent());
    });

    function pageWithPhoto(): Page
    {
        return app(SavePage::class)(PageData::fromArray([
            Page::COLUMN_TITLE => 'درباره ما',
            Page::COLUMN_SLUG => 'with-photo',
            Page::COLUMN_BODY => '<p>متن</p><img data-id="pages/photo.png" src="/storage/pages/photo.png" alt="داروخانه">',
            Page::COLUMN_IS_ACTIVE => true,
        ]));
    }

    it('makes an AVIF copy of each image in the background and offers it first, lazily', function (): void {
        pageWithPhoto();

        Storage::disk(PageContent::disk())->assertExists(PageImages::avifPath('pages/photo.png'));
        expect(Storage::disk(PageContent::disk())->mimeType(PageImages::avifPath('pages/photo.png')))->toBe('image/avif');
        $html = (string) $this->get('/pages/with-photo')
            ->assertOk()
            ->assertSee('<picture><source type="image/avif" srcset="', false)
            ->assertSee('loading="lazy"', false)
            ->getContent();

        // A browser without AVIF skips the <source> and loads the uploaded file from the <img>.
        preg_match('#<picture>.*?<img[^>]* src="([^"]+)"#s', $html, $fallback);
        expect($fallback[1] ?? '')->toContain('photo.png')->not->toContain('.avif');
    });

    it('queues the copy on the media queue, and shows the uploaded image lazily until it exists', function (): void {
        Queue::fake();

        pageWithPhoto();

        Queue::assertPushedOn('media', ConvertPageImages::class);
        $this->get('/pages/with-photo')->assertOk()->assertDontSee('image/avif', false)->assertSee('loading="lazy"', false);
        $this->artisan('content:page-images')->expectsOutputToContain('1 pages queued')->assertSuccessful();
    });
});
