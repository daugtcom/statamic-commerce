<?php

namespace Daugt\Commerce\Actions;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingStatus;
use Statamic\Actions\Action;
use Statamic\Facades\Entry;

class UpdateOrderShippingStatus extends Action
{
    protected $icon = "programming-module-box-cube";
    protected $fields = [
        'shipping_status' => [
            'type' => 'dictionary',
            'dictionary' => 'shipping_statuses',
            'display' => 'daugt-commerce::orders.actions.shipping_status',
            'default' => ShippingStatus::SHIPPED->value,
            'max_items' => 1,
        ],
    ];

    public static function title()
    {
        return __('daugt-commerce::orders.actions.update_shipping_status');
    }

    public function visibleTo($item)
    {
        return $item instanceof OrderEntry;
    }

    public function authorize($user, $order)
    {
        return $user->can('edit', $order);
    }

    public function run($items, $values)
    {
        $status = ShippingStatus::tryFrom((string) ($values['shipping_status'] ?? ''));
        if (! $status) {
            throw new \InvalidArgumentException(__('Invalid shipping status.'));
        }

        $orders = $items->filter(fn ($item) => $item instanceof OrderEntry);
        $failures = $orders->reject(fn (OrderEntry $order) => $this->updateOrder($order, $status));
        $total = $orders->count();

        if ($failures->isNotEmpty()) {
            $success = $total - $failures->count();

            if ($total === 1) {
                throw new \Exception(__('Order shipping status could not be updated.'));
            }

            if ($success === 0) {
                throw new \Exception(__('Order shipping statuses could not be updated.'));
            }

            throw new \Exception(__(':success/:total orders were updated', ['total' => $total, 'success' => $success]));
        }

        return trans_choice('Order shipping status updated|Order shipping statuses updated', $total);
    }

    private function updateOrder(OrderEntry $order, ShippingStatus $status): bool
    {
        $items = $order->get(OrderEntry::ITEMS);
        if (! is_array($items)) {
            return false;
        }

        $updated = [];
        $hasUpdates = false;

        foreach ($items as $item) {
            if (! is_array($item)) {
                $updated[] = $item;
                continue;
            }

            $product = $this->resolveProduct($item);
            if (! $product || ! $product->shipping()) {
                $updated[] = $item;
                continue;
            }

            $item['shipping_status'] = $status->value;
            $updated[] = $item;
            $hasUpdates = true;
        }

        if (! $hasUpdates) {
            return true;
        }

        $order->set(OrderEntry::ITEMS, $updated);

        return $order->save();
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
