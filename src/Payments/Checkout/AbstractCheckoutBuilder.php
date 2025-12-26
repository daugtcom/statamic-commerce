<?php

namespace Daugt\Commerce\Payments\Checkout;

use Daugt\Commerce\Carts\CartManager;

abstract class AbstractCheckoutBuilder implements CheckoutBuilder
{
    protected function cartItems(bool $withEntries = false): array
    {
        return app(CartManager::class)->items($withEntries);
    }
}
