<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed store settings with their defaults (roadmap D3.6, started in S19), cached until SaveStoreSettings changes
 * them. Other modules read settings only through this query.
 */
final readonly class StoreSettings
{
    public const string CACHE_KEY = 'settings:store';

    public const string MAX_QUANTITY_PER_ORDER = 'cart.max_quantity_per_order';

    public const string FREE_SHIPPING_THRESHOLD = 'cart.free_shipping_threshold';

    public const string REMOVAL_DELAY_SECONDS = 'cart.removal_delay_seconds';

    /**
     * Longest countdown the admin can set before a product leaves the cart.
     */
    public const int MAX_REMOVAL_DELAY_SECONDS = 10;

    public const string SHIPPING_FLAT_RATE = 'shipping.flat_rate';

    public const string WHATSAPP_NUMBER = 'contact.whatsapp_number';

    public const string TELEGRAM_USERNAME = 'contact.telegram_username';

    public const string CHAT_MESSAGE_PRODUCT = 'contact.chat_message_product';

    public const string CHAT_MESSAGE_GENERAL = 'contact.chat_message_general';

    public const string SEARCH_CATEGORY_IDS = 'search.category_ids';

    public const string SEARCH_BRAND_IDS = 'search.brand_ids';

    /**
     * Most categories, and most brands, the admin can pin to the search panel.
     */
    public const int MAX_SEARCH_SHORTCUTS = 8;

    /**
     * Defaults when the admin has not saved a value (the threshold in rial).
     */
    public const array DEFAULTS = [
        self::MAX_QUANTITY_PER_ORDER => 10,
        self::FREE_SHIPPING_THRESHOLD => 5_000_000,
        self::REMOVAL_DELAY_SECONDS => 3,
        self::SHIPPING_FLAT_RATE => 500_000,
        self::WHATSAPP_NUMBER => '',
        self::TELEGRAM_USERNAME => '',
        self::CHAT_MESSAGE_PRODUCT => 'سلام، درباره «{product}» {variant} سؤال دارم: {link}',
        self::CHAT_MESSAGE_GENERAL => 'سلام، سؤالی دارم.',
        self::SEARCH_CATEGORY_IDS => [],
        self::SEARCH_BRAND_IDS => [],
    ];

    /**
     * The store-wide limit per SKU in one order, used when neither the SKU nor its product sets one.
     */
    public function maxQuantityPerOrder(): int
    {
        return max(1, (int) $this->get(self::MAX_QUANTITY_PER_ORDER));
    }

    /**
     * Orders from this amount (rial) ship free; 0 turns the offer off.
     */
    public function freeShippingThreshold(): int
    {
        return max(0, (int) $this->get(self::FREE_SHIPPING_THRESHOLD));
    }

    /**
     * Seconds a removed product waits, with a countdown and «انصراف», before it leaves the cart (owner, 2026-11-15);
     * 0 removes at once.
     */
    public function removalDelaySeconds(): int
    {
        return min(self::MAX_REMOVAL_DELAY_SECONDS, max(0, (int) $this->get(self::REMOVAL_DELAY_SECONDS)));
    }

    /**
     * The one shipping price (rial) until real shipping arrives in phase 8 (S25); free over the threshold.
     */
    public function shippingFlatRate(): int
    {
        return max(0, (int) $this->get(self::SHIPPING_FLAT_RATE));
    }

    /**
     * The support WhatsApp number as «09xxxxxxxxx» (owner, 2026-11-14), or null while the admin has not set one.
     */
    public function whatsAppNumber(): ?string
    {
        $number = (string) $this->get(self::WHATSAPP_NUMBER);

        return $number === '' ? null : $number;
    }

    /**
     * A wa.me link that opens a chat with the support number with $text typed in, or null without a number.
     */
    public function whatsAppChatUrl(string $text = ''): ?string
    {
        $number = $this->whatsAppNumber();

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/98'.substr($number, 1).($text === '' ? '' : '?text='.rawurlencode($text));
    }

    /**
     * The support Telegram username without «@» (owner, 2026-11-14), or null while the admin has not set one.
     */
    public function telegramUsername(): ?string
    {
        $username = (string) $this->get(self::TELEGRAM_USERNAME);

        return $username === '' ? null : $username;
    }

    /**
     * A t.me link that opens a chat with the support account with $text typed in, or null without a username.
     */
    public function telegramChatUrl(string $text = ''): ?string
    {
        $username = $this->telegramUsername();

        if ($username === null) {
            return null;
        }

        return 'https://t.me/'.$username.($text === '' ? '' : '?text='.rawurlencode($text));
    }

    /**
     * The admin's draft message for a product (owner, 2026-11-14): {product} is the name, {variant} the picked SKU
     * (in brackets, left out when there is none) and {link} the product page.
     */
    public function productChatMessage(string $product, ?string $variant, string $link): string
    {
        $message = strtr((string) $this->get(self::CHAT_MESSAGE_PRODUCT), [
            '{product}' => $product,
            '{variant}' => filled($variant) ? '('.$variant.')' : '',
            '{link}' => $link,
        ]);

        return trim((string) preg_replace('/ {2,}/u', ' ', $message));
    }

    /**
     * The admin's draft message where there is no product (the header).
     */
    public function generalChatMessage(): string
    {
        return trim((string) $this->get(self::CHAT_MESSAGE_GENERAL));
    }

    /**
     * Categories the search panel shows when the box is empty or the text matches none (owner, 2026-11-15), in the
     * admin's order.
     *
     * @return list<int>
     */
    public function searchCategoryIds(): array
    {
        return $this->ids(self::SEARCH_CATEGORY_IDS);
    }

    /**
     * Brands the search panel shows when the box is empty or the text matches none (owner, 2026-11-15), in the
     * admin's order.
     *
     * @return list<int>
     */
    public function searchBrandIds(): array
    {
        return $this->ids(self::SEARCH_BRAND_IDS);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $saved */
        $saved = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck(Setting::COLUMN_VALUE, Setting::COLUMN_KEY)->all());

        return [...self::DEFAULTS, ...$saved];
    }

    /**
     * @return list<int>
     */
    private function ids(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? array_values(array_unique(array_map(intval(...), $value))) : [];
    }
}
