<?php

declare(strict_types=1);

namespace App\Modules\Payments\Console;

use App\Modules\Payments\Exceptions\ZarinpalRequestFailed;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalClient;
use Illuminate\Console\Command;

/**
 * Checks the Zarinpal merchant id and connection (roadmap D11.4, S36) by asking for the unverified payments list,
 * which opens no payment and takes no money.
 */
final class ZarinpalPingCommand extends Command
{
    protected $signature = 'zarinpal:ping';

    protected $description = 'Check the Zarinpal merchant id and connection';

    public function handle(ZarinpalClient $client): int
    {
        $mode = $client->isSandbox() ? 'آزمایشی (sandbox)' : 'اصلی';

        try {
            $unverified = $client->unverified();
        } catch (ZarinpalRequestFailed $failed) {
            $this->components->error("زرین‌پال ({$mode}): {$failed->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("زرین‌پال ({$mode}) پاسخ داد · پرداخت‌های تأییدنشده: ".count($unverified));

        return self::SUCCESS;
    }
}
