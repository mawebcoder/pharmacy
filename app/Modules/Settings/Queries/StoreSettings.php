<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Settings\Enums\SocialPlatform;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianDigits;
use Illuminate\Support\Facades\Cache;

/**
 * Typed store settings with their defaults (roadmap D3.6, started in S21), cached until SaveStoreSettings changes
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

    public const string SHIPPING_ORIGIN_CITY_ID = 'shipping.origin_city_id';

    public const string SHIPPING_MARKUP_PERCENT = 'shipping.markup_percent';

    /**
     * Who sends the parcels and from where, for registering them with Tipax (S35, owner 2026-11-19).
     */
    public const string SHIPPING_SENDER_NAME = 'shipping.sender_name';

    public const string SHIPPING_SENDER_MOBILE = 'shipping.sender_mobile';

    public const string SHIPPING_SENDER_ADDRESS = 'shipping.sender_address';

    public const string SHIPPING_SENDER_POSTAL_CODE = 'shipping.sender_postal_code';

    public const string SHIPPING_SENDER_LATITUDE = 'shipping.sender_latitude';

    public const string SHIPPING_SENDER_LONGITUDE = 'shipping.sender_longitude';

    /**
     * Tipax's codes sent with every package (S33): picked from Tipax's own lists; empty means the .env default.
     */
    public const string TIPAX_PACK_TYPE = 'tipax.pack_type';

    public const string TIPAX_PACKING_ID = 'tipax.packing_id';

    public const string TIPAX_CONTENT_GENERAL_ID = 'tipax.content_general_id';

    public const string TIPAX_CONTENT_LIQUID_ID = 'tipax.content_liquid_id';

    /**
     * Highest markup the admin can add to the shipping services' prices.
     */
    public const int MAX_SHIPPING_MARKUP_PERCENT = 100;

    public const string WHATSAPP_NUMBER = 'contact.whatsapp_number';

    public const string TELEGRAM_USERNAME = 'contact.telegram_username';

    public const string CHAT_MESSAGE_PRODUCT = 'contact.chat_message_product';

    public const string CHAT_MESSAGE_GENERAL = 'contact.chat_message_general';

    public const string PHONE = 'contact.phone';

    public const string EMAIL = 'contact.email';

    public const string ADDRESS = 'contact.address';

    public const string WORKING_HOURS = 'contact.working_hours';

    /**
     * The footer's social links (S30): a list of ['platform' => SocialPlatform value, 'url' => https address], in order.
     */
    public const string SOCIAL_LINKS = 'social.links';

    public const string SEARCH_CATEGORY_IDS = 'search.category_ids';

    /**
     * Staff mobiles texted when a customer asks for a return (S27, owner 2026-11-19): a list of «09…» numbers.
     */
    public const string RETURN_NOTIFY_MOBILES = 'returns.notify_mobiles';

    public const string SEARCH_BRAND_IDS = 'search.brand_ids';

    /**
     * Words a search ignores (S39, owner 2026-11-27): buying words like «قیمت» or «خرید» that only blur what is searched.
     */
    public const string SEARCH_IGNORED_WORDS = 'search.ignored_words';

    public const array DEFAULT_IGNORED_WORDS = ['قیمت', 'خرید', 'فروش', 'ارزان', 'اصل', 'اصلی', 'بهترین', 'سفارش', 'آنلاین', 'اورجینال'];

    /**
     * «تنظیمات پیامک» (S37): the low-credit threshold in rial; empty means SMS_LOW_CREDIT_BELOW. The provider, its
     * credentials and pattern ids come from .env only (config/sms.php; owner, 2026-11-28).
     */
    public const string SMS_LOW_CREDIT_BELOW = 'sms.low_credit_below';

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
        self::SHIPPING_ORIGIN_CITY_ID => null,
        self::SHIPPING_MARKUP_PERCENT => 0,
        self::SHIPPING_SENDER_NAME => '',
        self::SHIPPING_SENDER_MOBILE => '',
        self::SHIPPING_SENDER_ADDRESS => '',
        self::SHIPPING_SENDER_POSTAL_CODE => '',
        self::SHIPPING_SENDER_LATITUDE => '',
        self::SHIPPING_SENDER_LONGITUDE => '',
        self::TIPAX_PACK_TYPE => null,
        self::TIPAX_PACKING_ID => null,
        self::TIPAX_CONTENT_GENERAL_ID => null,
        self::TIPAX_CONTENT_LIQUID_ID => null,
        self::WHATSAPP_NUMBER => '',
        self::TELEGRAM_USERNAME => '',
        self::CHAT_MESSAGE_PRODUCT => 'سلام، درباره «{product}» {variant} سؤال دارم: {link}',
        self::CHAT_MESSAGE_GENERAL => 'سلام، سؤالی دارم.',
        self::PHONE => '',
        self::EMAIL => '',
        self::ADDRESS => '',
        self::WORKING_HOURS => '',
        self::SOCIAL_LINKS => [],
        self::SEARCH_CATEGORY_IDS => [],
        self::SEARCH_BRAND_IDS => [],
        self::SEARCH_IGNORED_WORDS => self::DEFAULT_IGNORED_WORDS,
        self::RETURN_NOTIFY_MOBILES => [],
        self::SMS_LOW_CREDIT_BELOW => null,
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
     * The flat shipping price (rial): charged, marked an estimate, when no shipping service can price an order
     * (S34); free over the threshold.
     */
    public function shippingFlatRate(): int
    {
        return max(0, (int) $this->get(self::SHIPPING_FLAT_RATE));
    }

    /**
     * The city parcels are sent from (our warehouse; a Tipax city id, D4), or null while the admin has not set it, in
     * which case shipping services cannot price and the flat rate is charged (S34).
     */
    public function shippingOriginCityId(): ?int
    {
        $cityId = (int) $this->get(self::SHIPPING_ORIGIN_CITY_ID);

        return $cityId > 0 ? $cityId : null;
    }

    /**
     * Percent added to the shipping services' prices (S34), e.g. to cover the gap with the branch counter price.
     */
    public function shippingMarkupPercent(): int
    {
        return min(self::MAX_SHIPPING_MARKUP_PERCENT, max(0, (int) $this->get(self::SHIPPING_MARKUP_PERCENT)));
    }

    /**
     * The warehouse as Tipax's sender (S35): name, mobile, full address, postal code and map point; empty strings where
     * the admin has not filled them in.
     *
     * @return array{name: string, mobile: string, address: string, postal_code: string, latitude: string, longitude: string}
     */
    public function shippingSender(): array
    {
        return [
            'name' => (string) $this->get(self::SHIPPING_SENDER_NAME),
            'mobile' => (string) $this->get(self::SHIPPING_SENDER_MOBILE),
            'address' => (string) $this->get(self::SHIPPING_SENDER_ADDRESS),
            'postal_code' => (string) $this->get(self::SHIPPING_SENDER_POSTAL_CODE),
            'latitude' => (string) $this->get(self::SHIPPING_SENDER_LATITUDE),
            'longitude' => (string) $this->get(self::SHIPPING_SENDER_LONGITUDE),
        ];
    }

    /**
     * A Tipax code the admin picked (TIPAX_* keys above), or null to use the .env default.
     */
    public function tipaxCode(string $key): ?int
    {
        $code = (int) $this->get($key);

        return $code > 0 ? $code : null;
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
     * The store's phone number as typed («۰۲۱-۱۲۳۴۵۶۷۸»), or null while the admin has not set one (S30).
     */
    public function phone(): ?string
    {
        return $this->text(self::PHONE);
    }

    /**
     * The phone number as a tel: link (Latin digits, no spaces or dashes), or null without one.
     */
    public function phoneUrl(): ?string
    {
        $phone = $this->phone();

        return $phone === null ? null : 'tel:'.preg_replace('/[^0-9+]/', '', PersianDigits::toLatin($phone));
    }

    public function email(): ?string
    {
        return $this->text(self::EMAIL);
    }

    public function address(): ?string
    {
        return $this->text(self::ADDRESS);
    }

    /**
     * When the store answers, e.g. «شنبه تا پنجشنبه، ۹ تا ۱۷».
     */
    public function workingHours(): ?string
    {
        return $this->text(self::WORKING_HOURS);
    }

    /**
     * The social links in the admin's order (S30); links to an unknown network are left out.
     *
     * @return list<array{platform: SocialPlatform, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach ((array) $this->get(self::SOCIAL_LINKS) as $link) {
            $platform = is_array($link) ? SocialPlatform::tryFrom((string) ($link['platform'] ?? '')) : null;
            $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : '';

            if ($platform !== null && $url !== '') {
                $links[] = ['platform' => $platform, 'url' => $url];
            }
        }

        return $links;
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

    /**
     * @return list<string>
     */
    public function searchIgnoredWords(): array
    {
        return array_values(array_filter(array_map(fn (mixed $word): string => trim((string) $word), (array) $this->get(self::SEARCH_IGNORED_WORDS)), fn (string $word): bool => $word !== ''));
    }

    /**
     * Super admins are alerted below this credit (rial), or null for the .env value.
     */
    public function smsLowCreditBelow(): ?int
    {
        $threshold = $this->get(self::SMS_LOW_CREDIT_BELOW);

        return is_numeric($threshold) ? max(0, (int) $threshold) : null;
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

    private function text(string $key): ?string
    {
        $value = trim((string) $this->get($key));

        return $value === '' ? null : $value;
    }

    /**
     * @return list<int>
     */
    private function ids(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? array_values(array_unique(array_map(intval(...), $value))) : [];
    }

    /**
     * Staff mobiles texted when a customer asks for a return (S27), as «09…»; empty while none is set.
     *
     * @return list<string>
     */
    public function returnNotifyMobiles(): array
    {
        $mobiles = [];

        foreach ((array) $this->get(self::RETURN_NOTIFY_MOBILES) as $mobile) {
            $mobile = IranMobile::normalize((string) $mobile);

            if ($mobile !== null && ! in_array($mobile, $mobiles, true)) {
                $mobiles[] = $mobile;
            }
        }

        return $mobiles;
    }
}
