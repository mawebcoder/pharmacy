<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Seeders;

use App\Modules\Catalog\Data\PurchasableVariantData;
use App\Modules\Catalog\Queries\OrderItemSnapshots;
use App\Modules\Catalog\Queries\PurchasableVariants;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderStatusLog;
use App\Modules\Orders\Support\OrderNumber;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Local demo orders (owner, 2026-11-14): one order in each of the seven statuses (roadmap D3.5) for the demo customer
 * DEMO_MOBILE, so «سفارش‌ها» can be seen in every state. Sign in with that mobile (the local OTP bypass code works).
 * forMobile() seeds them for another customer instead (created when missing; an existing one is left as it is):
 * app(DemoOrdersSeeder::class)->forMobile('09…')->setContainer(app())->__invoke()
 * Items come from the first buyable SKUs of the catalog with real snapshots; every status is reached through
 * ChangeOrderStatus, so the status logs read like real ones. Running it again replaces the demo orders.
 * Refuses to run in production. php artisan db:seed --class="App\Modules\Orders\Database\Seeders\DemoOrdersSeeder"
 */
final class DemoOrdersSeeder extends Seeder
{
    public const string DEMO_MOBILE = '09121234567';

    public const string DEMO_NOTE = 'demo-orders-seeder';

    private string $mobile = self::DEMO_MOBILE;

    /** The customer's name for the order snapshots, set in run(). */
    private string $customerName = 'مشتری نمایشی';

    /**
     * Status => the moves that reach it from pending, how many days ago it was placed.
     *
     * @var array<string, array{moves: list<array{0: OrderStatus, 1: OrderActor}>, days_ago: int}>
     */
    private const array PATHS = [
        'pending' => ['moves' => [], 'days_ago' => 0],
        'expired' => ['moves' => [[OrderStatus::Expired, OrderActor::System]], 'days_ago' => 1],
        'paid' => ['moves' => [[OrderStatus::Paid, OrderActor::System]], 'days_ago' => 1],
        'processing' => ['moves' => [[OrderStatus::Paid, OrderActor::System], [OrderStatus::Processing, OrderActor::Admin]], 'days_ago' => 2],
        'on_the_way' => ['moves' => [[OrderStatus::Paid, OrderActor::System], [OrderStatus::Processing, OrderActor::Admin], [OrderStatus::OnTheWay, OrderActor::Admin]], 'days_ago' => 3],
        'delivered' => ['moves' => [[OrderStatus::Paid, OrderActor::System], [OrderStatus::Processing, OrderActor::Admin], [OrderStatus::OnTheWay, OrderActor::Admin], [OrderStatus::Delivered, OrderActor::Admin]], 'days_ago' => 6],
        'cancelled' => ['moves' => [[OrderStatus::Paid, OrderActor::System], [OrderStatus::Cancelled, OrderActor::Customer]], 'days_ago' => 4],
    ];

    public function forMobile(string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function run(PurchasableVariants $variants, OrderItemSnapshots $snapshots, ChangeOrderStatus $changeStatus, StoreSettings $settings): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Demo orders are for local use only; nothing was seeded.');

            return;
        }

        $candidates = array_values(array_map(intval(...), DB::table('product_variants')->where('is_active', true)->whereNull('deleted_at')->orderBy('id')->limit(20)->pluck('id')->all()));
        $buyable = array_values(array_filter($variants->find($candidates), fn ($variant): bool => $variant->canBuy()));

        if ($buyable === []) {
            $this->command?->warn('No buyable SKU in the catalog; seed products first.');

            return;
        }

        $customerId = $this->demoCustomerId();
        $this->forgetPreviousDemoOrders($customerId);
        $itemSnapshots = $snapshots->for(array_map(fn ($variant): int => $variant->variantId, $buyable));

        // Oldest first, so ids follow the placing dates and «سفارش‌ها» (newest first) reads in date order.
        $paths = self::PATHS;
        uasort($paths, fn (array $a, array $b): int => $b['days_ago'] <=> $a['days_ago']);

        foreach (array_values($paths) as $index => $path) {
            $lines = [$buyable[$index % count($buyable)]];

            if ($index % 2 === 1 && count($buyable) > 1) {
                $lines[] = $buyable[($index + 1) % count($buyable)];
            }

            $order = $this->createPendingOrder($customerId, $lines, $itemSnapshots, Carbon::now()->subDays($path['days_ago'])->subHours($index), $settings);

            foreach ($path['moves'] as [$status, $actor]) {
                $changeStatus($order, $status, $actor);
            }

            $order->setPaymentStatus(match ($order->getStatus()) {
                OrderStatus::Pending => PaymentStatus::Pending,
                OrderStatus::Expired => PaymentStatus::Unpaid,
                OrderStatus::Cancelled => PaymentStatus::RefundPending,
                default => PaymentStatus::Paid,
            });

            if ($order->getStatus() === OrderStatus::Expired) {
                $order->setExpiresAt($order->getPlacedAt()->copy()->addMinutes((int) config('payments.pending_expiry_minutes')));
            }

            $order->save();
        }

        $this->command?->info('Seeded '.count(self::PATHS).' demo orders for '.$this->mobile.'.');
    }

    /**
     * Orders must not use Identity's models (C3 rule 1): the demo customer is a plain row.
     */
    private function demoCustomerId(): int
    {
        $customer = DB::table('customers')->where('mobile', $this->mobile)->first(['id', 'first_name', 'last_name']);

        if ($customer !== null) {
            $name = trim(((string) ($customer->first_name ?? '')).' '.((string) ($customer->last_name ?? '')));
            $this->customerName = $name === '' ? $this->mobile : $name;

            return (int) $customer->id;
        }

        return DB::table('customers')->insertGetId([
            'mobile' => $this->mobile,
            'first_name' => 'مشتری',
            'last_name' => 'نمایشی',
            'theme' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forgetPreviousDemoOrders(int $customerId): void
    {
        $orderIds = Order::query()->ownedBy($customerId)->where(Order::COLUMN_ADMIN_NOTE, self::DEMO_NOTE)->pluck(Order::COLUMN_ID);

        OrderStatusLog::query()->whereIn(OrderStatusLog::COLUMN_ORDER_ID, $orderIds)->delete();
        OrderItem::query()->whereIn(OrderItem::COLUMN_ORDER_ID, $orderIds)->delete();
        Order::query()->whereKey($orderIds)->delete();
    }

    /**
     * @param  list<PurchasableVariantData>  $lines
     * @param  array<int, array{product: array<string, mixed>, sku: array<string, mixed>}>  $itemSnapshots
     */
    private function createPendingOrder(int $customerId, array $lines, array $itemSnapshots, Carbon $placedAt, StoreSettings $settings): Order
    {
        $subtotal = array_sum(array_map(fn ($variant): int => $variant->price, $lines));
        $free = $settings->freeShippingThreshold() > 0 && $subtotal >= $settings->freeShippingThreshold();
        $shipping = $free ? 0 : $settings->shippingFlatRate();

        $order = (new Order)
            ->setCustomerId($customerId)
            ->setStatus(OrderStatus::Pending)
            ->setPaymentStatus(PaymentStatus::Pending)
            ->setAddressSnapshot([
                'address_id' => null, 'title' => 'خانه', 'receiver_name' => $this->customerName, 'receiver_mobile' => $this->mobile,
                'province_id' => 8, 'province' => 'تهران', 'city_id' => 1, 'city' => 'تهران', 'main_street' => 'ولیعصر',
                'side_street' => 'فاطمی', 'alley' => 'نسترن', 'block' => null, 'details' => null, 'plaque' => '12', 'unit' => '3',
                'floor' => '2', 'postal_code' => '1415934571',
            ])
            ->setCustomerSnapshot(['name' => $this->customerName, 'mobile' => $this->mobile])
            ->setSubtotal($subtotal)
            ->setShippingTotal($shipping)
            ->setTotal($subtotal + $shipping)
            ->setShippingMethod('flat')
            ->setShippingQuoteSnapshot([
                'method' => 'flat', 'label' => 'ارسال', 'price' => $shipping, 'free' => $free, 'free_reason' => $free ? 'threshold' : null,
                'flat_rate' => $settings->shippingFlatRate(), 'free_shipping_threshold' => $settings->freeShippingThreshold(),
            ])
            ->setAdminNote(self::DEMO_NOTE)
            ->setPlacedAt($placedAt)
            ->setExpiresAt($placedAt->copy()->addMinutes((int) config('payments.pending_expiry_minutes')));
        $order->save();
        $order->setNumber(OrderNumber::for($order->getId(), $placedAt))->save();
        ChangeOrderStatus::log($order->getId(), null, OrderStatus::Pending, OrderActor::System);

        foreach ($lines as $variant) {
            (new OrderItem)
                ->setOrderId($order->getId())
                ->setVariantId($variant->variantId)
                ->setProductId($variant->productId)
                ->setProductName($variant->productName)
                ->setVariantTitle($variant->variantTitle)
                ->setSku($variant->sku)
                ->setBrandName($variant->brandName)
                ->setImageUrl($variant->image?->url(320, 'webp'))
                ->setProductType($variant->productType)
                ->setRequiresPrescription($variant->requiresPrescription)
                ->setQuantity(1)
                ->setProductSnapshot($itemSnapshots[$variant->variantId] ?? [])
                ->setListPrice($variant->compareAtPrice ?? $variant->price)
                ->setSalePrice($variant->isOnSale ? $variant->price : null)
                ->setUnitPrice($variant->price)
                ->setTotal($variant->price)
                ->save();
        }

        return $order;
    }
}
