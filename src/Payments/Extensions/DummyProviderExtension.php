<?php

namespace Daugt\Commerce\Payments\Extensions;

use Daugt\Commerce\Payments\Checkout\DummyCheckoutBuilder;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class DummyProviderExtension extends AbstractPaymentProviderExtension
{
    public function checkoutView(array $params): ?array
    {
        return app(DummyCheckoutBuilder::class)->build($params);
    }
}
