<?php

namespace Daugt\Commerce\Payments\Checkout;

class DummyCheckoutBuilder extends AbstractCheckoutBuilder
{
    public function build(array $params): ?array
    {
        return null;
    }
}
