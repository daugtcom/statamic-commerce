<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Jobs\ArchivePaymentProduct;
use Daugt\Commerce\Payments\PaymentProviderResolver;

class ArchivePaymentProductTest extends TestCase
{
    public function test_archive_does_not_throw_with_dummy_provider(): void
    {
        $resolver = $this->app->make(PaymentProviderResolver::class);
        (new ArchivePaymentProduct('dummy_prod', 'dummy_price'))->handle($resolver);

        $this->assertTrue(true);
    }
}
