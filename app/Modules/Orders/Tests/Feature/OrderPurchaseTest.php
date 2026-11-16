<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Orders\Actions\DeletePurchaseItem;
use App\Modules\Orders\Actions\OpenOrderPurchase;
use App\Modules\Orders\Actions\ReceivePurchaseItem;
use App\Modules\Orders\Actions\SavePurchaseItem;
use App\Modules\Orders\Actions\SendOrderOnItsWay;
use App\Modules\Orders\Data\PurchaseItemData;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Exceptions\PurchaseRefused;
use App\Modules\Orders\Filament\Pages\SupplierShoppingList;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\Purchases\OrderPurchaseResource;
use App\Modules\Orders\Filament\Resources\Purchases\Pages\ViewOrderPurchase;
use App\Modules\Orders\Filament\Resources\Purchases\RelationManagers\PurchaseItemsRelationManager;
use App\Modules\Orders\Listeners\MarkOrderPaid;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderPurchase;
use App\Modules\Orders\Models\OrderPurchaseItem;
use App\Modules\Orders\Support\PurchaseMargin;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

    // Paid: 3 × cream at 500,000 rial each and 1 × serum at 1,000,000 rial; 2,500,000 rial for the items.
    $this->order = Order::factory()->paid()->create([Order::COLUMN_SUBTOTAL => 2_500_000, Order::COLUMN_TOTAL => 2_500_000]);
    $this->cream = purchaseOrderItem($this->order, 'کرم اوره', 3, 500_000);
    $this->serum = purchaseOrderItem($this->order, 'سرم ویتامین سی', 1, 1_000_000);
    $this->alborz = Provider::factory()->create([Provider::COLUMN_NAME => 'پخش البرز']);
    $this->sina = Provider::factory()->create([Provider::COLUMN_NAME => 'پخش سینا']);
});

function purchaseOrderItem(Order $order, string $name, int $quantity, int $unitPrice): OrderItem
{
    $item = (new OrderItem)
        ->setOrderId($order->getId())
        ->setProductName($name)
        ->setSku('SKU-'.fake()->unique()->numerify('#####'))
        ->setQuantity($quantity)
        ->setProductSnapshot([])
        ->setListPrice($unitPrice)
        ->setUnitPrice($unitPrice)
        ->setPaidUnit($unitPrice)
        ->setPaidTotal($unitPrice * $quantity);
    $item->save();

    return $item;
}

function purchaseRow(OrderPurchase $purchase, OrderItem $item, Provider $provider, int $quantity, int $unitCost): OrderPurchaseItem
{
    return app(SavePurchaseItem::class)($purchase, new PurchaseItemData($item->getId(), $provider->getId(), $quantity, $unitCost));
}

it('creates no purchase document when the order is paid', function (): void {
    $order = Order::factory()->create();
    purchaseOrderItem($order, 'کرم', 1, 500_000);

    app(MarkOrderPaid::class)->handle(new PaymentSucceeded($order->getId(), 1));

    expect($order->refresh()->getStatus())->toBe(OrderStatus::Paid)
        ->and(OrderPurchase::query()->count())->toBe(0)
        ->and(OrderPurchaseItem::query()->count())->toBe(0);
});

it('opens an empty document from the order page and links the order and the document both ways', function (): void {
    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionHidden('purchase')
        ->callAction('openPurchase');

    $purchase = OrderPurchase::query()->sole();

    expect($purchase)->getOrderId()->toBe($this->order->getId())->getStatus()->toBe(PurchaseStatus::Draft)
        ->and($purchase->items()->count())->toBe(0)
        ->and($this->order->refresh()->getSourcingStatus())->toBe(SourcingStatus::AwaitingReceipt);

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionHidden('openPurchase')
        ->assertActionHasUrl('purchase', OrderPurchaseResource::getUrl('view', ['record' => $purchase]));
    livewire(ViewOrderPurchase::class, ['record' => $purchase->getRouteKey()])
        ->assertActionHasUrl('order', OrderResource::getUrl('view', ['record' => $this->order]))
        ->assertSee($this->order->getNumber());
});

it('opens a document only for a paid order that is not cancelled, and only one', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);

    expect(app(OpenOrderPurchase::class)($this->order, 1)->getId())->toBe($purchase->getId())
        ->and(fn () => app(OpenOrderPurchase::class)(Order::factory()->create(), 1))->toThrow(PurchaseRefused::class)
        ->and(fn () => app(OpenOrderPurchase::class)(Order::factory()->paid()->create([Order::COLUMN_STATUS => OrderStatus::Cancelled]), 1))->toThrow(PurchaseRefused::class);
});

it('splits an item over suppliers, never past its quantity, and registers the document once every unit has a row', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);

    purchaseRow($purchase, $this->cream, $this->alborz, 2, 450_000);
    purchaseRow($purchase, $this->cream, $this->sina, 1, 470_000);

    expect(fn () => purchaseRow($purchase, $this->cream, $this->sina, 1, 470_000))->toThrow(PurchaseRefused::class)
        ->and($purchase->refresh()->getStatus())->toBe(PurchaseStatus::Draft);

    purchaseRow($purchase, $this->serum, $this->alborz, 1, 800_000);

    expect($purchase->refresh())
        ->getStatus()->toBe(PurchaseStatus::Registered)
        ->getTotalCost()->toBe(2 * 450_000 + 470_000 + 800_000)
        ->and(OrderPurchaseItem::query()->where(OrderPurchaseItem::COLUMN_ORDER_ITEM_ID, $this->cream->getId())->sum(OrderPurchaseItem::COLUMN_QUANTITY))->toEqual(3);
});

it('works the final margin out of what the customer paid for the items less the rows', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);

    // Draft (the serum has no row yet): no final margin.
    expect(PurchaseMargin::orderMargin($this->order->refresh()))->toBeNull();

    purchaseRow($purchase, $this->serum, $this->sina, 1, 1_100_000);
    $order = $this->order->refresh();

    expect(PurchaseMargin::orderMargin($order))->toBe(2_500_000 - (1_350_000 + 1_100_000))
        ->and(PurchaseMargin::itemMargin($order, $this->cream->refresh()))->toBe(1_500_000 - 1_350_000)
        ->and(PurchaseMargin::itemMargin($order, $this->serum->refresh()))->toBe(-100_000)
        ->and($this->cream->refresh()->getUnitCost())->toBeNull();

    $this->get(OrderPurchaseResource::getUrl('view', ['record' => $purchase]))->assertOk()->assertSee(Toman::format(50_000));
    // The order's page shows the document in its own section, with its rows.
    $this->get(OrderResource::getUrl('view', ['record' => $this->order]))
        ->assertOk()
        ->assertSee(['سند خرید', $purchase->refresh()->getNumber(), 'پخش سینا', Toman::format(1_100_000), Toman::format(50_000)]);
});

it('is ready to pack only when every row of a registered document arrived', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $creamRow = purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);
    $serumRow = purchaseRow($purchase, $this->serum, $this->alborz, 1, 800_000);

    app(ReceivePurchaseItem::class)($creamRow, received: 2, missing: 1, missingNote: 'تأمین‌کننده موجودی نداشت');

    expect($this->order->refresh()->getSourcingStatus())->toBe(SourcingStatus::PartiallyReceived)
        ->and($purchase->refresh()->getStatus())->toBe(PurchaseStatus::Registered)
        ->and($creamRow->refresh()->getMissingNote())->toBe('تأمین‌کننده موجودی نداشت');

    app(ReceivePurchaseItem::class)($creamRow, received: 3);
    app(ReceivePurchaseItem::class)($serumRow, received: 1);

    expect($this->order->refresh()->getSourcingStatus())->toBe(SourcingStatus::ReadyToPack)
        ->and($purchase->refresh())->getStatus()->toBe(PurchaseStatus::Received)->getReceivedAt()->not->toBeNull()
        ->and(fn () => app(ReceivePurchaseItem::class)($serumRow, received: 2))->toThrow(PurchaseRefused::class);
});

it('enables «تحویل به حمل» only once the purchase document has fully arrived', function (): void {
    $this->order->setStatus(OrderStatus::Processing)->save();
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $creamRow = purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);
    $serumRow = purchaseRow($purchase, $this->serum, $this->alborz, 1, 800_000);

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertActionVisible('sendOnItsWay')->assertActionDisabled('sendOnItsWay');
    expect(fn () => app(SendOrderOnItsWay::class)($this->order->refresh(), 'TPX-1', 1))->toThrow(OrderRefused::class);

    app(ReceivePurchaseItem::class)($creamRow, received: 3);
    app(ReceivePurchaseItem::class)($serumRow, received: 1);

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionEnabled('sendOnItsWay')
        ->callAction('sendOnItsWay', ['tracking_code' => 'TPX-1'])
        ->assertHasNoActionErrors();

    expect($this->order->refresh()->getStatus())->toBe(OrderStatus::OnTheWay);
});

it('lets rows change for 48 hours after the document is registered, but still records receiving', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $row = purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);
    purchaseRow($purchase, $this->serum, $this->alborz, 1, 800_000);

    $this->travel(47)->hours();
    app(SavePurchaseItem::class)($purchase, new PurchaseItemData($this->cream->getId(), $this->sina->getId(), 3, 460_000), $row);
    expect($purchase->refresh()->isLocked())->toBeFalse()
        ->and($row->refresh()->getProviderName())->toBe('پخش سینا');

    $this->travel(2)->hours();
    $rows = livewire(PurchaseItemsRelationManager::class, ['ownerRecord' => $purchase, 'pageClass' => ViewOrderPurchase::class]);

    expect($purchase->refresh()->isLocked())->toBeTrue()
        ->and(fn () => app(SavePurchaseItem::class)($purchase, new PurchaseItemData($this->cream->getId(), $this->alborz->getId(), 3, 400_000), $row))->toThrow(PurchaseRefused::class)
        ->and(fn () => app(DeletePurchaseItem::class)($row))->toThrow(PurchaseRefused::class);

    $rows->assertActionHidden(TestAction::make('editRow')->table($row))
        ->assertActionHidden(TestAction::make('deleteRow')->table($row))
        ->assertActionVisible(TestAction::make('receive')->table($row));

    app(ReceivePurchaseItem::class)($row, received: 3);
    expect($row->refresh()->getReceivedQuantity())->toBe(3);
});

it('keeps rows with units received or missing', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $row = purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);
    app(ReceivePurchaseItem::class)($row, received: 2);

    expect(fn () => app(DeletePurchaseItem::class)($row))->toThrow(PurchaseRefused::class)
        ->and(fn () => app(SavePurchaseItem::class)($purchase, new PurchaseItemData($this->cream->getId(), $this->alborz->getId(), 1, 450_000), $row))->toThrow(PurchaseRefused::class);
});

it('adds rows from the document page, caps the quantity and warns when the cost is at or above what the customer paid per unit', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $rows = fn () => livewire(PurchaseItemsRelationManager::class, ['ownerRecord' => $purchase, 'pageClass' => ViewOrderPurchase::class]);

    $rows()->mountAction(TestAction::make('addRow')->table())
        ->assertActionDataSet(['customer_paid' => null])
        ->fillForm(['order_item_id' => $this->cream->getId()])
        ->assertActionDataSet(['customer_paid' => Toman::format(500_000).' هر واحد · '.Toman::format(1_500_000).' برای ۳ عدد'])
        ->assertSchemaComponentHidden('loss_warning')
        ->fillForm(['unit_cost' => '50,000'])
        ->assertSchemaComponentVisible('loss_warning');

    $rows()->callAction(TestAction::make('addRow')->table(), ['order_item_id' => $this->cream->getId(), 'provider_id' => $this->alborz->getId(), 'quantity' => 4, 'unit_cost' => '45,000'])
        ->assertHasActionErrors(['quantity' => 'max']);

    $rows()->callAction(TestAction::make('addRow')->table(), ['order_item_id' => $this->cream->getId(), 'provider_id' => $this->alborz->getId(), 'quantity' => 3, 'unit_cost' => '50,000', 'expiry_date' => '2027-06-30'])
        ->assertHasNoActionErrors()
        ->assertNotified('قیمت خرید کرم اوره برابر یا بیشتر از پرداختی مشتری برای هر واحد است.');

    expect(OrderPurchaseItem::query()->sole())->getQuantity()->toBe(3)->getUnitCost()->toBe(500_000)->getProviderName()->toBe('پخش البرز')
        ->getExpiryDate()->toDateString()->toBe('2027-06-30');
});

it('offers only the items with units left, says how many, and hides «افزودن» once all are covered', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    purchaseRow($purchase, $this->cream, $this->alborz, 2, 450_000);
    purchaseRow($purchase, $this->serum, $this->alborz, 1, 800_000);
    $rows = fn () => livewire(PurchaseItemsRelationManager::class, ['ownerRecord' => $purchase, 'pageClass' => ViewOrderPurchase::class]);

    $rows()->mountAction(TestAction::make('addRow')->table())
        ->assertFormFieldExists('order_item_id', fn (Select $field): bool => $field->getOptions() === [$this->cream->getId() => 'کرم اوره — ۱ از ۳ عدد باقی‌مانده (۲ عدد ثبت شده)'])
        ->fillForm(['order_item_id' => $this->cream->getId()])
        ->assertActionDataSet(['quantity' => 1]);

    purchaseRow($purchase, $this->cream, $this->sina, 1, 470_000);

    $rows()->assertActionHidden(TestAction::make('addRow')->table());
});

it('lists rows still to arrive by supplier and exports them', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $arrived = purchaseRow($purchase, $this->cream, $this->alborz, 3, 450_000);
    $open = purchaseRow($purchase, $this->serum, $this->sina, 1, 800_000);
    app(ReceivePurchaseItem::class)($arrived, received: 3);

    livewire(SupplierShoppingList::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$arrived])
        ->callAction('excel')
        ->assertFileDownloaded('supplier-shopping-list.xlsx');
});

it('keeps content editors out of purchase documents', function (): void {
    $purchase = app(OpenOrderPurchase::class)($this->order, 1);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');

    $this->get(OrderPurchaseResource::getUrl('index'))->assertForbidden();
    $this->get(OrderPurchaseResource::getUrl('view', ['record' => $purchase]))->assertForbidden();
    $this->get(SupplierShoppingList::getUrl())->assertForbidden();
});
