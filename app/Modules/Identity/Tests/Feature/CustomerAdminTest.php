<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Filament\Resources\Customers\CustomerResource;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Identity\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Filament\Resources\SmsLogs\SmsLogResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

it('lists customers and finds one by a mobile typed in Persian digits', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    $sara = Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);
    $other = Customer::factory()->create([Customer::COLUMN_MOBILE => '09350000000']);

    livewire(ListCustomers::class)
        ->assertCanSeeTableRecords([$sara, $other])
        ->searchTable('۰۹۱۲۳۴۵')
        ->assertCanSeeTableRecords([$sara])
        ->assertCanNotSeeTableRecords([$other]);
});

it('lets an operator block a customer, ending their remembered sign-ins and tokens', function (): void {
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $operator->assignRole(AdminRole::Operator->value);
    $this->actingAs($operator, 'admin');
    $customer = Customer::factory()->create();
    $customer->setRememberToken('old-remember-token');
    $customer->save();
    $customer->createToken('app');

    livewire(ListCustomers::class)
        ->callAction(TestAction::make('block')->table($customer))
        ->assertNotified('مشتری مسدود شد.');

    $customer->refresh();
    expect($customer->isBlocked())->toBeTrue()
        ->and($customer->getRememberToken())->not->toBe('old-remember-token')
        ->and($customer->tokens()->count())->toBe(0)
        ->and(Activity::query()->where('subject_type', $customer->getMorphClass())->where('event', 'updated')->exists())->toBeTrue();

    livewire(ListCustomers::class)
        ->callAction(TestAction::make('unblock')->table($customer));

    expect($customer->refresh()->isBlocked())->toBeFalse();
});

it('hides customers from content editors and SMS logs from operators', function (): void {
    $editor = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $editor->assignRole(AdminRole::ContentEditor->value);
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->create();
    $operator->assignRole(AdminRole::Operator->value);

    $this->actingAs($editor, 'admin')->get(CustomerResource::getUrl('index'))->assertForbidden();
    $this->actingAs($operator, 'admin')->get(CustomerResource::getUrl('index'))->assertOk();
    $this->actingAs($operator, 'admin')->get(SmsLogResource::getUrl('index'))->assertForbidden();
});

it('shows super admins the SMS log with masked mobiles and hidden codes', function (): void {
    config(['sms.pretend' => true]);
    expect(app(SendTemplatedSms::class)(new SmsMessageData('09123456789', SmsTemplate::Otp, ['48213']))->success)->toBeTrue();
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');

    $this->get(SmsLogResource::getUrl('index'))
        ->assertOk()
        ->assertSee('0912***6789')
        ->assertSee('کد ورود')
        ->assertDontSee('48213');
});

it('opens the customer page from a row with their details and block action', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    $customer = Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);

    livewire(ListCustomers::class)
        ->assertTableActionExists('block')
        ->assertSeeHtml(CustomerResource::getUrl('view', ['record' => $customer]));

    livewire(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertSee('09123456789')
        ->assertSee('سارا')
        ->callAction('block');

    expect($customer->refresh()->isBlocked())->toBeTrue();
});
