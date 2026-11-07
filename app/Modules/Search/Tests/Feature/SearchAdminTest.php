<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Search\Filament\Pages\SearchInsights;
use App\Modules\Search\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Modules\Search\Jobs\SyncSearchSynonyms;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Search\Models\SearchSynonym;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Queue::fake();
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
});

it('creates a synonym group and sends it to the search index', function (): void {
    livewire(ManageSearchSynonyms::class)
        ->callAction(CreateAction::class, [SearchSynonym::COLUMN_TERM => 'روی', SearchSynonym::COLUMN_SYNONYMS => ['زینک', 'zinc']])
        ->assertHasNoFormErrors();

    expect(SearchSynonym::query()->sole()->getSynonyms())->toBe(['زینک', 'zinc']);
    Queue::assertPushed(SyncSearchSynonyms::class);
});

it('lists searches without results and adds a synonym from there', function (): void {
    foreach (['ویتامیم', 'ویتامیم', 'کرم'] as $query) {
        (new SearchLog)->setQuery($query)->setNormalizedQuery($query)->setResultsCount($query === 'کرم' ? 4 : 0)->save();
    }
    $zero = SearchLog::query()->where(SearchLog::COLUMN_QUERY, 'ویتامیم')->orderBy('id')->first();
    $found = SearchLog::query()->where(SearchLog::COLUMN_QUERY, 'کرم')->sole();

    livewire(SearchInsights::class)
        ->assertCanSeeTableRecords([$zero])
        ->assertCanNotSeeTableRecords([$found])
        ->callAction(TestAction::make('addSynonym')->table($zero), ['term' => 'ویتامیم', 'synonyms' => ['ویتامین']]);

    expect(SearchSynonym::query()->sole()->getSynonyms())->toBe(['ویتامین']);
});

it('keeps operators out of search admin pages', function (string $url): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();

    $this->actingAs($operator, 'admin')->get($url)->assertForbidden();
})->with(['/admin/search-synonyms', '/admin/search-insights']);
