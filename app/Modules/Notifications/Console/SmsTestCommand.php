<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Support\SmsManager;
use App\Modules\Shared\Support\IranMobile;
use Illuminate\Console\Command;

/**
 * Sends a test sign-in code (roadmap D11.6 setup step 4) and prints what the provider answered.
 */
final class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {mobile : 09xxxxxxxxx} {--provider= : melipayamak (default: SMS_PROVIDER)}';

    protected $description = 'Send a test sign-in SMS (template «otp») and show the provider result';

    public function handle(SendTemplatedSms $send, SmsManager $sms): int
    {
        $mobile = IranMobile::normalize((string) $this->argument('mobile'));

        if ($mobile === null) {
            $this->components->error('شماره موبایل نامعتبر است.');

            return self::FAILURE;
        }

        $provider = $this->option('provider') === null ? SmsProvider::configured() : SmsProvider::tryFrom((string) $this->option('provider'));

        if ($provider === null) {
            $this->components->error('سامانه پیامک ناشناخته است؛ گزینه‌ها: '.implode('، ', array_column(SmsProvider::cases(), 'value')));

            return self::FAILURE;
        }

        $result = $send(new SmsMessageData($mobile, SmsTemplate::Otp, ['12345']), $provider);

        $result->success
            ? $this->components->info('ارسال شد ('.$provider->label().($sms->isPretending() ? '، آزمایشی' : '').'): شناسه '.$result->providerMessageId)
            : $this->components->error('ارسال نشد: کد '.$result->code.' · '.$result->error);

        return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
