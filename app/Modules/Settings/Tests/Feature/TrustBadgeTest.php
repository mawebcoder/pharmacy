<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Settings\Database\Factories\TrustBadgeFactory;
use App\Modules\Settings\Filament\Resources\TrustBadges\Pages\ManageTrustBadges;
use App\Modules\Settings\Filament\Resources\TrustBadges\TrustBadgeResource;
use App\Modules\Settings\Models\TrustBadge;
use App\Modules\Settings\Support\TrustBadgeEmbed;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| Trust badges (roadmap S30): e-Namad's code is pasted as they hand it out and shown in every page's footer, cleaned
| to their own link and image.
*/

it('renders the e-Namad badge in the footer with the referrer policy e-Namad checks', function (): void {
    TrustBadge::factory()->create();

    $this->get(route('faq'))
        ->assertOk()
        ->assertSee('نمادهای اعتماد')
        ->assertSee('href="https://trustseal.enamad.ir/?id&#61;123456&amp;Code&#61;AbCdEf"', false)
        ->assertSee('src="https://trustseal.enamad.ir/logo.aspx?id&#61;123456&amp;Code&#61;AbCdEf"', false)
        ->assertSee('referrerpolicy="origin"', false);
});

it('leaves the footer without badges while none is active', function (): void {
    TrustBadge::factory()->inactive()->create();

    $this->get(route('faq'))->assertOk()->assertDontSee('نمادهای اعتماد');
});

it('keeps only e-Namad and Samandehi links and images from a pasted code', function (string $code, ?string $expected): void {
    expect(TrustBadgeEmbed::clean($code))->toBe($expected);
})->with([
    'script around the seal' => [
        '<script>alert(1)</script><a href="https://trustseal.enamad.ir/?id=1" onclick="x()"><img src="https://trustseal.enamad.ir/logo.aspx?id=1" onerror="x()"></a>',
        '<a href="https://trustseal.enamad.ir/?id&#61;1" rel="noopener"><img src="https://trustseal.enamad.ir/logo.aspx?id&#61;1" /></a>',
    ],
    'another host' => ['<a href="https://evil.example/"><img src="https://evil.example/x.png"></a>', null],
    'javascript link' => ['<a href="javascript:alert(1)"><img src="https://logo.samandehi.ir/logo.aspx?id=9"></a>', '<a rel="noopener"><img src="https://logo.samandehi.ir/logo.aspx?id&#61;9" /></a>'],
    'iframe' => ['<iframe src="https://trustseal.enamad.ir/"></iframe>', null],
]);

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    });

    it('adds a badge from e-Namad\'s code at the end', function (): void {
        TrustBadge::factory()->create([TrustBadge::COLUMN_SORT => 4]);

        livewire(ManageTrustBadges::class)
            ->callAction(TestAction::make(CreateAction::class), [
                TrustBadge::COLUMN_TITLE => 'ساماندهی',
                TrustBadge::COLUMN_EMBED_CODE => "<img referrerpolicy='origin' id='rgvjsizpnbqejzpefukzrgvj' src='https://logo.samandehi.ir/logo.aspx?id=9&p=xyz' alt='logo-samandehi'>",
            ])
            ->assertHasNoFormErrors();

        expect(TrustBadge::query()->latest('id')->first())
            ->getSort()->toBe(5)
            ->getEmbedCode()->toContain('src="https://logo.samandehi.ir/logo.aspx?id&#61;9&amp;p&#61;xyz"');
    });

    it('refuses a code that is not from e-Namad or Samandehi', function (): void {
        livewire(ManageTrustBadges::class)
            ->callAction(TestAction::make(CreateAction::class), [
                TrustBadge::COLUMN_TITLE => 'نماد',
                TrustBadge::COLUMN_EMBED_CODE => '<script src="https://evil.example/x.js"></script>',
            ])
            ->assertNotified('این کد از نماد اعتماد الکترونیکی (enamad.ir) یا ساماندهی (samandehi.ir) نیست؛ کد را همان‌طور که سایت نماد داده کپی کنید.');

        expect(TrustBadge::query()->count())->toBe(0);
    });

    it('asks for an image when there is no code', function (): void {
        livewire(ManageTrustBadges::class)
            ->callAction(TestAction::make(CreateAction::class), [TrustBadge::COLUMN_TITLE => 'مجوز وزارت بهداشت'])
            ->assertHasFormErrors([TrustBadge::IMAGE => 'required']);
    });

    it('keeps the badges from content editors', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin')
            ->get(TrustBadgeResource::getUrl('index'))
            ->assertForbidden();
    });
});

it('has a factory code as e-Namad hands it out', function (): void {
    expect(TrustBadgeEmbed::clean(TrustBadgeFactory::ENAMAD_CODE))->toContain('<img');
});
