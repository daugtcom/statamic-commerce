<?php

namespace Daugt\Commerce\Carts;

use Daugt\Commerce\Carts\Contracts\CartStore;
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
        $items = $cart['items'];

        $items[$productId] = ($items[$productId] ?? 0) + $quantity;

        $cart['items'] = $items;
        $this->store->put($cart);

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
            $cart['items'][$productId] = $quantity;
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
}
