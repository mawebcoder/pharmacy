<?php

declare(strict_types=1);

use App\Modules\Content\Actions\SendContactMessage;
use App\Modules\Content\Data\ContactMessageData;
use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Modules\Content\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

/*
| «تماس با ما» (roadmap S34): the message is saved for staff; 3 messages every 10 minutes per IP and per mobile.
*/

/**
 * @return array<string, string>
 */
function contactForm(array $overrides = []): array
{
    return [
        ContactMessage::COLUMN_NAME => 'سارا محمدی',
        ContactMessage::COLUMN_MOBILE => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
        ContactMessage::COLUMN_EMAIL => '',
        ContactMessage::COLUMN_SUBJECT => ContactSubject::Order->value,
        ContactMessage::COLUMN_BODY => 'سفارش من هنوز ارسال نشده است.',
        ...$overrides,
    ];
}

beforeEach(function (): void {
    RateLimiter::clear('contact:ip:127.0.0.1');
});

it('saves the message as new for staff, with the mobile normalised and tags stripped', function (): void {
    livewire('content::contact-page')
        ->set('form', contactForm([ContactMessage::COLUMN_BODY => '<a href="x">سفارش</a> من نرسیده است.']))
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('پیام شما رسید.');

    expect(ContactMessage::query()->sole())
        ->getMobile()->toBe('09123456789')
        ->getBody()->toBe('سفارش من نرسیده است.')
        ->getStatus()->toBe(ContactMessageStatus::New)
        ->getCustomerId()->toBeNull();
});

it('fills in a signed-in customer\'s name and mobile and links the message to them', function (): void {
    $customer = Customer::factory()->create([Customer::COLUMN_FIRST_NAME => 'علی', Customer::COLUMN_LAST_NAME => 'رضایی', Customer::COLUMN_MOBILE => '09351234567']);
    $this->actingAs($customer, 'customer');

    livewire('content::contact-page')
        ->assertSet('form.name', 'علی رضایی')
        ->assertSet('form.mobile', '09351234567')
        ->set('form.subject', ContactSubject::Payment->value)
        ->set('form.body', 'مبلغ دو بار از حسابم کم شد.')
        ->call('send')
        ->assertHasNoErrors();

    expect(ContactMessage::query()->sole()->getCustomerId())->toBe($customer->getId());
});

it('asks for the name, mobile, subject and message', function (): void {
    livewire('content::contact-page')
        ->set('form', contactForm([ContactMessage::COLUMN_NAME => '', ContactMessage::COLUMN_MOBILE => '', ContactMessage::COLUMN_SUBJECT => '', ContactMessage::COLUMN_BODY => '']))
        ->call('send')
        ->assertHasErrors(['form.name' => 'required', 'form.mobile' => 'required', 'form.subject' => 'required', 'form.body' => 'required']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('refuses a mobile that is not an Iranian mobile number', function (): void {
    livewire('content::contact-page')
        ->set('form', contactForm([ContactMessage::COLUMN_MOBILE => '02188776655']))
        ->call('send')
        ->assertSee('شماره موبایل معتبر نیست');
});

it('takes 3 messages every 10 minutes from one visitor, then asks them to wait', function (): void {
    foreach (['09121111111', '09122222222', '09123333333'] as $mobile) {
        livewire('content::contact-page')->set('form', contactForm([ContactMessage::COLUMN_MOBILE => $mobile]))->call('send')->assertHasNoErrors();
    }

    livewire('content::contact-page')
        ->set('form', contactForm([ContactMessage::COLUMN_MOBILE => '09124444444']))
        ->call('send')
        ->assertHasErrors('form.body')
        ->assertSee('پیام‌های شما زیاد بود؛ ۱۰ دقیقه دیگر دوباره تلاش کنید.');

    expect(ContactMessage::query()->count())->toBe(3);

    $this->travel(SendContactMessage::WINDOW_SECONDS + 1)->seconds();

    livewire('content::contact-page')->set('form', contactForm([ContactMessage::COLUMN_MOBILE => '09124444444']))->call('send')->assertHasNoErrors();
});

it('takes 3 messages every 10 minutes for one mobile, whatever the IP', function (): void {
    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
        RateLimiter::clear('contact:ip:'.$ip);
        $_ = rescue(fn () => app(SendContactMessage::class)(ContactMessageData::fromArray(contactForm(), null, $ip)), report: false);
    }

    expect(ContactMessage::query()->count())->toBe(3);
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    });

    it('counts new messages on the menu, marks one read when opened, then answered', function (): void {
        $message = ContactMessage::factory()->create();
        ContactMessage::factory()->answered()->create();

        expect(ContactMessageResource::getNavigationBadge())->toBe('۱');

        livewire(ViewContactMessage::class, ['record' => $message->getId()])
            ->assertSee($message->getBody())
            ->callAction('answered');

        expect($message->fresh())
            ->getStatus()->toBe(ContactMessageStatus::Answered)
            ->getReadAt()->not->toBeNull()
            ->getAnsweredAt()->not->toBeNull()
            ->and(ContactMessageResource::getNavigationBadge())->toBeNull();
    });

    it('keeps the messages from an admin without a role', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->create(), 'admin')
            ->get(ContactMessageResource::getUrl('index'))
            ->assertForbidden();
    });
});
