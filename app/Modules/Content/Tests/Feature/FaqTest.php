<?php

declare(strict_types=1);

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Modules\Content\Models\Faq;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

it('shows the active questions under their group, in the admin\'s order', function (): void {
    Faq::factory()->create([Faq::COLUMN_GROUP => FaqGroup::Payment, Faq::COLUMN_QUESTION => 'پرداخت در محل دارید؟', Faq::COLUMN_SORT => 1]);
    Faq::factory()->create([Faq::COLUMN_QUESTION => 'دومین پرسش ارسال', Faq::COLUMN_SORT => 2]);
    Faq::factory()->create([Faq::COLUMN_QUESTION => 'اولین پرسش ارسال', Faq::COLUMN_SORT => 1]);
    Faq::factory()->inactive()->create([Faq::COLUMN_QUESTION => 'پرسش پنهان']);

    $this->get(route('faq'))
        ->assertOk()
        ->assertSeeInOrder(['سفارش و ارسال', 'اولین پرسش ارسال', 'دومین پرسش ارسال', 'پرداخت', 'پرداخت در محل دارید؟'])
        ->assertDontSee(['پرسش پنهان', 'حساب کاربری']);
});

it('shows an answer as text, never as HTML', function (): void {
    Faq::factory()->create([Faq::COLUMN_ANSWER => '<script>alert(1)</script>پاسخ']);

    $this->get(route('faq'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('adds a question at the end of its group, with tags stripped', function (): void {
        Faq::factory()->create([Faq::COLUMN_GROUP => FaqGroup::Returns, Faq::COLUMN_SORT => 7]);

        livewire(ManageFaqs::class)
            ->callAction(TestAction::make(CreateAction::class), [
                Faq::COLUMN_GROUP => FaqGroup::Returns->value,
                Faq::COLUMN_QUESTION => 'مهلت بازگشت کالا چند روز است؟',
                Faq::COLUMN_ANSWER => '<b>هفت روز</b> پس از تحویل.',
            ])
            ->assertHasNoFormErrors();

        expect(Faq::query()->latest('id')->first())
            ->getSort()->toBe(8)
            ->getAnswer()->toBe('هفت روز پس از تحویل.');
    });

    it('asks for the question and the answer', function (): void {
        livewire(ManageFaqs::class)
            ->callAction(TestAction::make(CreateAction::class), [Faq::COLUMN_GROUP => FaqGroup::Other->value])
            ->assertHasFormErrors([Faq::COLUMN_QUESTION => 'required', Faq::COLUMN_ANSWER => 'required']);
    });

    it('reorders the questions by drag and drop', function (): void {
        $first = Faq::factory()->create([Faq::COLUMN_QUESTION => 'الف', Faq::COLUMN_SORT => 1]);
        $second = Faq::factory()->create([Faq::COLUMN_QUESTION => 'ب', Faq::COLUMN_SORT => 2]);

        livewire(ManageFaqs::class)->call('reorderTable', [$second->getId(), $first->getId()]);

        $this->get(route('faq'))->assertSeeInOrder(['ب', 'الف']);
    });
});
