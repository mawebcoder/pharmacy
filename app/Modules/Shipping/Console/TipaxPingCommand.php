<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Console;

use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Console\Command;

/**
 * Checks the Tipax credentials and connection (roadmap D11.3 step 5, S33): signs in and reads the account and its
 * wallet, creating nothing. Exits non-zero on any failure, for deploy scripts.
 */
final class TipaxPingCommand extends Command
{
    protected $signature = 'tipax:ping';

    protected $description = 'Check the Tipax credentials and connection';

    public function handle(TipaxClient $tipax): int
    {
        $host = (string) parse_url((string) config('shipping.tipax.base_url'), PHP_URL_HOST);

        try {
            $account = $tipax->account();
            $wallet = $tipax->walletBalance();
        } catch (TipaxRequestFailed $failed) {
            $this->components->error("تیپاکس ({$host}): {$failed->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'تیپاکس (%s): حساب «%s» (شماره %s) · موجودی کیف پول %s',
            $host,
            $account['full_name'] ?? '—',
            PersianDigits::toPersian((string) ($account['customer_id'] ?? '—')),
            PersianDigits::toPersian(number_format($wallet)),
        ));

        return self::SUCCESS;
    }
}
