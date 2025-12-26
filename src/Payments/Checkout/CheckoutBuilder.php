<?php

namespace Daugt\Commerce\Payments\Checkout;

interface CheckoutBuilder
{
    public function build(array $params): ?array;
}
