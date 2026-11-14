<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;
use App\Modules\Search\Livewire\InstantSearch;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Search\Queries\RecentSearches;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

function searchedBy(?int $customerId, string $query, string $at): void
{
    (new SearchLog)
        ->setQuery($query)
        ->setNormalizedQuery(SearchText::normalize($query))
        ->setResultsCount(1)
        ->setCustomerId($customerId)
        ->forceFill([SearchLog::COLUMN_CREATED_AT => Carbon::parse($at)])
        ->save();
}

beforeEach(function (): void {
    $this->customer = Customer::factory()->create();
});

it('gives a customer their latest 6 distinct searches, newest first, and never someone else\'s', function (): void {
    foreach (['ویتامین', 'کرم', 'شامپو', 'ضدآفتاب', 'قرص آهن', 'ژل', 'مسواک'] as $index => $query) {
        searchedBy($this->customer->getId(), $query, '2026-11-14 10:0'.$index);
    }
    searchedBy($this->customer->getId(), 'ويتامين', '2026-11-14 10:09');
    searchedBy(Customer::factory()->create()->getId(), 'دیگری', '2026-11-14 11:00');

    expect(array_column(app(RecentSearches::class)->for($this->customer->getId()), 'q'))
        ->toBe(['ويتامين', 'مسواک', 'ژل', 'قرص آهن', 'ضدآفتاب', 'شامپو']);
});

it('sends a signed-in customer\'s history with popular products and none for guests', function (): void {
    searchedBy($this->customer->getId(), 'ویتامین', '2026-11-14 10:00');

    livewire(InstantSearch::class)->set('popularRequested', true)->assertSet('serverRecent', []);

    $this->actingAs($this->customer, 'customer');
    livewire(InstantSearch::class)
        ->set('popularRequested', true)
        ->assertSet('serverRecent', fn (array $recent): bool => array_column($recent, 'q') === ['ویتامین']);
});

it('forgets only the signed-in customer\'s history and keeps the searches for insights', function (): void {
    $other = Customer::factory()->create();
    searchedBy($this->customer->getId(), 'ویتامین', '2026-11-14 10:00');
    searchedBy($other->getId(), 'کرم', '2026-11-14 10:01');
    $this->actingAs($this->customer, 'customer');

    livewire(InstantSearch::class)->call('clearRecent');

    expect(SearchLog::query()->count())->toBe(2)
        ->and(SearchLog::query()->where(SearchLog::COLUMN_CUSTOMER_ID, $this->customer->getId())->count())->toBe(0)
        ->and(SearchLog::query()->where(SearchLog::COLUMN_CUSTOMER_ID, $other->getId())->count())->toBe(1);
});
