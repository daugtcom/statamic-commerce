<?php

namespace Daugt\Commerce\Widgets;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Illuminate\Support\Str;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class OrdersList extends Widget
{
    /**
     * Orders List widget
     *
     * Displays a list of orders for the currently authenticated
     * Control Panel user.
     *
     * ## Config options
     *
     * - `title` (string, optional)
     *   Widget title shown in the Control Panel.
     */
    public function component()
    {
        $user = auth()->user();
        $title = $this->config('title') ?? __('daugt-commerce::orders.widget.title');

        if (! $user) {
            return VueComponent::render('OrdersList', ['title' => $title, 'orders' => []]);
        }

        $orders = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->where(OrderEntry::USER, $user->id())
            ->get()
            ->filter(fn ($order) => $order instanceof OrderEntry)
            ->sortByDesc(fn (OrderEntry $order) => $order->orderNumber())
            ->values();

        $payload = $orders
            ->map(fn (OrderEntry $order) => $this->orderPayload($order))
            ->values()
            ->all();

        return VueComponent::render('OrdersList', [
            'title' => $title,
            'orders' => $payload,
        ]);
    }

    private function orderPayload(OrderEntry $order): array
    {
        $status = $order->status() ?? '';
        $shippingStatus = $order->augmentedValue(OrderEntry::SHIPPING_STATUS)->value();

        return [
            'id' => (string) $order->id(),
            'order_number' => $order->orderNumber(),
            'status' => $status,
            'shipping_status' => $shippingStatus,
            'succeeded_at' => $order->succeededAt(),
            'items' => $this->itemsPayload($order),
            'invoices' => $this->invoicesPayload($order),
        ];
    }

    private function itemsPayload(OrderEntry $order): array
    {
        $items = $order->get(OrderEntry::ITEMS);
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $productId = $item['product'] ?? null;
                $productId = is_array($productId) ? ($productId[0] ?? null) : $productId;
                $product = $productId ? Entry::find($productId) : null;

                $productImage = $product instanceof ProductEntry ? $this->productImageUrl($product) : null;
                $shippingStatus = null;
                if ($product instanceof ProductEntry && $product->shipping()) {
                    $shippingStatus = $item['shipping_status'] ?? null;
                }

                return [
                    'product' => $product->get('title'),
                    'media' => $productImage,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'shipping_status' => $shippingStatus,
                ];
            })
            ->values()
            ->all();
    }

    private function invoicesPayload(OrderEntry $order): array
    {
        $invoiceIds = $order->get(OrderEntry::INVOICES);
        if (! is_array($invoiceIds)) {
            return [];
        }

        return collect($invoiceIds)
            ->map(fn ($id) => is_string($id) ? Entry::find($id) : null)
            ->filter(fn ($entry) => $entry instanceof InvoiceEntry)
            ->map(function (InvoiceEntry $invoice) {
                $status = $invoice->get(InvoiceEntry::STATUS);

                return [
                    'id' => (string) $invoice->id(),
                    'number' => $invoice->invoiceNumber() ?? $invoice->stripeInvoiceId() ?? (string) $invoice->id(),
                    'status' => $status,
                    'invoice_url' => $invoice->invoiceUrl(),
                ];
            })
            ->values()
            ->all();
    }

    private function productImageUrl(ProductEntry $product): ?string
    {
        $asset = $product->augmentedValue(ProductEntry::MEDIA)->value()->first();

        if (! $asset instanceof AssetContract || ! $asset->isImage()) {
            return null;
        }

        return $asset->manipulate([
            'w' => 64,
            'h' => 64,
            'fit' => 'crop_focal',
        ]);
    }
}
