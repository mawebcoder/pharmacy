<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Data;

/**
 * One way to ship an order that the customer can pick at checkout (roadmap S34): a key unique among the options
 * (e.g. «tipax.express»), the service it comes from (the order's shipping_method: «tipax», «flat»), its Persian
 * title, the price in rial, the delivery days when known, whether the price is only an estimate, and the service's
 * own details for the order snapshot.
 */
final readonly class ShippingOptionData
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $key,
        public string $method,
        public string $title,
        public int $price,
        public ?int $deliveryDays,
        public bool $isEstimate,
        public array $details = [],
    ) {}

    public function withPrice(int $price): self
    {
        return new self($this->key, $this->method, $this->title, $price, $this->deliveryDays, $this->isEstimate, $this->details);
    }

    /**
     * @return array{key: string, method: string, title: string, price: int, delivery_days: int|null, is_estimate: bool, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'method' => $this->method,
            'title' => $this->title,
            'price' => $this->price,
            'delivery_days' => $this->deliveryDays,
            'is_estimate' => $this->isEstimate,
            'details' => $this->details,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  from toArray(), e.g. read back from the cache
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            method: (string) $data['method'],
            title: (string) $data['title'],
            price: (int) $data['price'],
            deliveryDays: isset($data['delivery_days']) ? (int) $data['delivery_days'] : null,
            isEstimate: (bool) $data['is_estimate'],
            details: (array) ($data['details'] ?? []),
        );
    }
}
