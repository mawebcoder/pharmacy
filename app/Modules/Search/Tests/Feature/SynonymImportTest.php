<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Search\Actions\ImportSearchSynonyms;
use App\Modules\Search\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Modules\Search\Jobs\SyncSearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

use function Pest\Livewire\livewire;

/**
 * @param  list<list<string>>  $rows
 */
function synonymsWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'synonyms-test-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

beforeEach(function (): void {
    Queue::fake();
});

it('imports synonym groups from Excel, skipping the header and bad rows', function (): void {
    $file = synonymsWorkbook([
        ['عبارت', 'هم‌معنی', 'هم‌معنی'],
        ['ویتامین دي', 'ویتامین D', 'D3'],
        ['امگا ۳', 'روغن ماهی، omega 3'],
        ['', 'بدون عبارت'],
        ['تنها'],
    ]);

    $result = app(ImportSearchSynonyms::class)($file, 'xlsx');

    expect($result->created)->toBe(2)
        ->and($result->skipped)->toBe([4 => 'عبارت خالی است', 5 => 'هم‌معنی ندارد'])
        ->and(SearchSynonym::query()->where(SearchSynonym::COLUMN_TERM, 'ویتامین دی')->sole()->getSynonyms())->toBe(['ویتامین D', 'D3'])
        ->and(SearchSynonym::query()->where(SearchSynonym::COLUMN_TERM, 'امگا ۳')->sole()->getSynonyms())->toBe(['روغن ماهی', 'omega 3']);
    Queue::assertPushed(SyncSearchSynonyms::class, 1);
});

it('merges into existing phrases, so importing twice changes nothing', function (): void {
    SearchSynonym::factory()->create([SearchSynonym::COLUMN_TERM => 'ضد آفتاب', SearchSynonym::COLUMN_SYNONYMS => ['sunscreen']]);
    $file = synonymsWorkbook([['ضد آفتاب', 'ضدآفتاب', 'sunscreen']]);

    $first = app(ImportSearchSynonyms::class)($file, 'xlsx');
    $second = app(ImportSearchSynonyms::class)($file, 'xlsx');

    expect($first->updated)->toBe(1)
        ->and($second->updated + $second->created)->toBe(0)
        ->and(SearchSynonym::query()->sole()->getSynonyms())->toBe(['sunscreen', 'ضدآفتاب']);
    Queue::assertPushed(SyncSearchSynonyms::class, 1);
});

it('reads CSV files too', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'synonyms-test-').'.csv';
    file_put_contents($path, "term,synonyms\nآکنه,جوش\n");

    expect(app(ImportSearchSynonyms::class)($path, 'csv')->created)->toBe(1)
        ->and(SearchSynonym::query()->sole()->getSynonyms())->toBe(['جوش']);
});

it('imports from the admin page', function (): void {
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    $upload = UploadedFile::fake()->createWithContent('synonyms.xlsx', (string) file_get_contents(synonymsWorkbook([['کلسیم', 'calcium']])));

    livewire(ManageSearchSynonyms::class)
        ->callAction('import', ['file' => $upload])
        ->assertNotified('۱ گروه جدید، ۰ گروه به‌روز');

    expect(SearchSynonym::query()->sole()->getTerm())->toBe('کلسیم');
});
