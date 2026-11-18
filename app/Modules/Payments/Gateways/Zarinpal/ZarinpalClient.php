<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Zarinpal;

use App\Modules\Payments\Exceptions\ZarinpalRequestFailed;
use Throwable;
use ZarinPal\Sdk\ClientBuilder;
use ZarinPal\Sdk\Endpoint\PaymentGateway\PaymentGateway as SdkGateway;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\UnverifiedRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;
use ZarinPal\Sdk\HttpClient\Exception\ResponseException;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

/**
 * Zarinpal's payment API v4 through the official SDK (zarinpal/zarinpal-php-sdk; roadmap D7.1, D7.6, S36): request,
 * verify and the unverified list, in rial. The SDK sends through LaravelPsrClient (logged, fakeable); every SDK
 * failure becomes ZarinpalRequestFailed with Zarinpal's own code when it answered with one. Sandbox or production
 * and both addresses come from config/payments.php (.env).
 */
final readonly class ZarinpalClient
{
    /**
     * @param  array{merchant_id: ?string, sandbox: bool, base_url: string, sandbox_base_url: string, currency: string}  $settings
     */
    public function __construct(
        private array $settings,
        private LaravelPsrClient $http,
        private int $timeoutSeconds,
    ) {}

    public function isSandbox(): bool
    {
        return $this->settings['sandbox'];
    }

    /**
     * Opens a payment: Zarinpal's authority and the StartPay page to send the customer to.
     *
     * @return array{authority: string, url: string, fee: int|null, fee_type: string|null}
     *
     * @throws ZarinpalRequestFailed
     */
    public function request(int $amount, string $description, string $callbackUrl, ?string $mobile): array
    {
        $gateway = $this->gateway();
        $payment = new RequestRequest;
        $payment->amount = $amount;
        $payment->description = mb_substr($description, 0, 500);
        $payment->callback_url = $callbackUrl;
        $payment->mobile = $mobile;
        $payment->currency = $this->settings['currency'];
        $response = $this->call(fn () => $gateway->request($payment));

        if ($response->code !== 100 || $response->authority === '') {
            throw ZarinpalRequestFailed::refused($response->code);
        }

        return [
            'authority' => $response->authority,
            'url' => $gateway->getRedirectUrl($response->authority),
            'fee' => isset($response->fee) ? (int) $response->fee : null,
            'fee_type' => isset($response->fee_type) ? (string) $response->fee_type : null,
        ];
    }

    /**
     * Confirms a payment with the amount it was opened for: code 100 (verified now) or 101 (already verified).
     *
     * @return array{code: int, ref_id: string, card_pan: string|null, card_hash: string|null, fee_type: string|null, fee: int|null}
     *
     * @throws ZarinpalRequestFailed for any other code
     */
    public function verify(int $amount, string $authority): array
    {
        $gateway = $this->gateway();
        $verify = new VerifyRequest;
        $verify->amount = $amount;
        $verify->authority = $authority;
        $response = $this->call(fn () => $gateway->verify($verify));

        if (! in_array($response->code, [100, 101], true)) {
            throw ZarinpalRequestFailed::refused($response->code);
        }

        return [
            'code' => $response->code,
            'ref_id' => (string) ($response->ref_id ?? ''),
            'card_pan' => isset($response->card_pan) ? (string) $response->card_pan : null,
            'card_hash' => isset($response->card_hash) ? (string) $response->card_hash : null,
            'fee_type' => isset($response->fee_type) ? (string) $response->fee_type : null,
            'fee' => isset($response->fee) && is_numeric($response->fee) ? (int) $response->fee : null,
        ];
    }

    /**
     * Payments Zarinpal took but nobody verified yet (its last 100).
     *
     * @return list<array{authority: string, amount: int}>
     *
     * @throws ZarinpalRequestFailed
     */
    public function unverified(): array
    {
        $gateway = $this->gateway();
        $response = $this->call(fn () => $gateway->unverified(new UnverifiedRequest));

        return array_values(array_map(fn (array $row): array => [
            'authority' => (string) $row['authority'],
            'amount' => (int) $row['amount'],
        ], $response->authorities));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws ZarinpalRequestFailed
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (Throwable $failed) {
            // The SDK mishandles error bodies (it throws a TypeError), so the code is read from the answer itself.
            $code = $this->http->lastErrorCode() ?? ($failed instanceof ResponseException && $failed->getCode() < 0 ? $failed->getCode() : null);

            throw $code === null ? ZarinpalRequestFailed::unreachable($failed) : ZarinpalRequestFailed::refused($code, $failed);
        }
    }

    /**
     * @throws ZarinpalRequestFailed
     */
    private function gateway(): SdkGateway
    {
        if (blank($this->settings['merchant_id'])) {
            throw ZarinpalRequestFailed::notConfigured();
        }

        return (new ZarinPal(new Options([
            'client_builder' => new ClientBuilder($this->timeoutSeconds, $this->http),
            'merchant_id' => (string) $this->settings['merchant_id'],
            'sandbox' => $this->settings['sandbox'],
            'base_url' => $this->settings['base_url'],
            'sandbox_base_url' => $this->settings['sandbox_base_url'],
        ])))->paymentGateway();
    }
}
