<?php

namespace Daugt\Commerce\Carts;

use Daugt\Commerce\Carts\Contracts\CartStore;
use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Facades\Entry as EntryFacade;

class CartManager
{
    public function __construct(private CartStore $store)
    {
    }

    public function get(): array
    {
        return $this->normalizeCart($this->store->get());
    }

    public function items(bool $withEntries = false): array
    {
        $cart = $this->get();
        $items = [];

        foreach ($cart['items'] as $productId => $quantity) {
            $item = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];

            if ($withEntries) {
                $item['entry'] = EntryFacade::find($productId);
            }

            $items[] = $item;
        }

        return $items;
    }

    public function add(string $productId, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            return $this->get();
        }

        $cart = $this->normalizeCart($this->store->get());
        $isSubscription = $this->isSubscriptionProduct($productId);
        $adjusted = false;

        if ($isSubscription) {
            if ($quantity !== 1) {
                $adjusted = true;
            }

            $quantity = 1;

            $subscriptionIds = $this->subscriptionProductIds($cart['items'], $productId);
            if ($subscriptionIds !== []) {
                foreach ($subscriptionIds as $subscriptionId) {
                    unset($cart['items'][$subscriptionId]);
                }
                $adjusted = true;
            }
        }

        $items = $cart['items'];

        if ($isSubscription) {
            $items[$productId] = 1;
        } else {
            $items[$productId] = ($items[$productId] ?? 0) + $quantity;
        }

        $cart['items'] = $items;
        $this->store->put($cart);

        if ($adjusted) {
            $this->flashSubscriptionNotice();
        }

        return $cart;
    }

    public function setQuantity(string $productId, int $quantity): array
    {
        $cart = $this->normalizeCart($this->store->get());

        if (! isset($cart['items'][$productId])) {
            return $cart;
        }

        if ($quantity <= 0) {
            unset($cart['items'][$productId]);
        } else {
            if ($this->isSubscriptionProduct($productId) && $quantity > 1) {
                $cart['items'][$productId] = 1;
                $this->flashSubscriptionNotice();
            } else {
                $cart['items'][$productId] = $quantity;
            }
        }

        $this->store->put($cart);

        return $cart;
    }

    public function remove(string $productId): array
    {
        $cart = $this->normalizeCart($this->store->get());

        unset($cart['items'][$productId]);

        $this->store->put($cart);

        return $cart;
    }

    public function clear(): void
    {
        $this->store->forget();
    }

    private function normalizeCart(array $cart): array
    {
        $cart['items'] = $cart['items'] ?? [];

        $items = [];
        foreach ($cart['items'] as $productId => $quantity) {
            if (! is_string($productId) || $productId === '') {
                continue;
            }

            $items[$productId] = (int) $quantity;
        }

        $cart['items'] = $items;

        return $cart;
    }

    private function isSubscriptionProduct(string $productId): bool
    {
        $entry = EntryFacade::find($productId);

        if (! $entry) {
            return false;
        }

        $billingType = method_exists($entry, 'billingType')
            ? $entry->billingType()
            : $entry->get(ProductEntry::BILLING_TYPE);

        return (string) $billingType === 'recurring';
    }

    private function subscriptionProductIds(array $items, ?string $ignoreProductId = null): array
    {
        $subscriptions = [];

        foreach (array_keys($items) as $productId) {
            if ($ignoreProductId && $productId === $ignoreProductId) {
                continue;
            }

            if ($this->isSubscriptionProduct((string) $productId)) {
                $subscriptions[] = (string) $productId;
            }
        }

        return $subscriptions;
    }

    private function flashSubscriptionNotice(): void
    {
        if (! app()->bound('session.store')) {
            return;
        }

        $message = __('daugt-commerce::cart.subscription_limit');

        session()->flash('daugt_commerce.cart.notice', $message);
    }
}
