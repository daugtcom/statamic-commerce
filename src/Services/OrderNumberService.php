<?php

namespace Daugt\Commerce\Services;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Support\AddonSettings;
use Statamic\Facades\Entry;

class OrderNumberService
{
    private const SETTINGS_KEY = 'next_order_number';

    public function assign(OrderEntry $order): void
    {
        $current = $order->orderNumber();

        if ($current !== null && $current > 0) {
            if ($this->orderNumberTaken($current, $order->id())) {
                $current = $this->nextOrderNumber();
                $order->set(OrderEntry::ORDER_NUMBER, $current);
            }
            $this->ensureNextIsAtLeast($current + 1);
            return;
        }

        $next = $this->nextOrderNumber();
        $order->set(OrderEntry::ORDER_NUMBER, $next);
        $this->ensureNextIsAtLeast($next + 1);
    }

    private function nextOrderNumber(): int
    {
        $setting = AddonSettings::get(self::SETTINGS_KEY);
        $next = is_numeric($setting) ? (int) $setting : null;
        $maxExisting = $this->maxExistingOrderNumber();

        if ($next !== null && $next > 0 && $next > $maxExisting) {
            return $next;
        }

        return $maxExisting + 1;
    }

    private function maxExistingOrderNumber(): int
    {
        $max = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->get()
            ->max(OrderEntry::ORDER_NUMBER);

        return is_numeric($max) ? (int) $max : 0;
    }

    private function ensureNextIsAtLeast(int $next): void
    {
        if ($next < 1) {
            $next = 1;
        }

        $current = AddonSettings::get(self::SETTINGS_KEY);
        $current = is_numeric($current) ? (int) $current : null;

        if ($current !== null && $current >= $next) {
            return;
        }

        AddonSettings::set(self::SETTINGS_KEY, $next);
    }

    private function orderNumberTaken(int $orderNumber, ?string $orderId = null): bool
    {
        $query = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->where(OrderEntry::ORDER_NUMBER, $orderNumber);

        if ($orderId) {
            $query->where('id', '!=', $orderId);
        }

        return $query->count() > 0;
    }
}
