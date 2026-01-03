<?php

namespace Daugt\Commerce\Services;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingStatus;
use Statamic\Facades\Entry;

class OrderShippingStatusResolver
{
    public function resolve(OrderEntry $entry): ?string
    {
        $items = $entry->get(OrderEntry::ITEMS);
        if (! is_array($items) || $items === []) {
            return null;
        }

        $hasShippableItems = false;
        $hasShipped = false;
        $allDelivered = true;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $this->resolveProduct($item);
            if (! $product || ! $product->shipping()) {
                continue;
            }

            $hasShippableItems = true;
            $status = ShippingStatus::tryFrom((string) ($item['shipping_status'] ?? ''));

            if ($status === ShippingStatus::DELIVERED) {
                $hasShipped = true;
                continue;
            }

            if ($status === ShippingStatus::SHIPPED) {
                $hasShipped = true;
                $allDelivered = false;
                continue;
            }

            $allDelivered = false;
        }

        if (! $hasShippableItems) {
            return null;
        }

        if ($hasShipped) {
            return $allDelivered
                ? ShippingStatus::DELIVERED->value
                : ShippingStatus::SHIPPED->value;
        }

        return ShippingStatus::PENDING->value;
    }

    private function resolveProduct(array $item): ?ProductEntry
    {
        $productId = $this->firstId($item['product'] ?? null);
        if (! $productId) {
            return null;
        }

        $entry = Entry::find($productId);

        return $entry instanceof ProductEntry ? $entry : null;
    }

    private function firstId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
