<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Jobs\ArchiveStripeProduct;
use Daugt\Commerce\Tests\Support\FakeStripeClient;

class ArchiveStripeProductTest extends TestCase
{
    public function test_archive_skips_when_no_product_id(): void
    {
        $fakeStripe = new FakeStripeClient();

        (new ArchiveStripeProduct(null, null))->handle($fakeStripe);

        $this->assertCount(0, $fakeStripe->products->updated);
        $this->assertCount(0, $fakeStripe->prices->updated);
    }

    public function test_archive_deactivates_product_and_price(): void
    {
        $fakeStripe = new FakeStripeClient();

        (new ArchiveStripeProduct('prod_123', 'price_123'))->handle($fakeStripe);

        $this->assertSame('price_123', $fakeStripe->prices->updated[0]['id']);
        $this->assertSame(['active' => false], $fakeStripe->prices->updated[0]['payload']);

        $this->assertSame('prod_123', $fakeStripe->products->updated[0]['id']);
        $this->assertSame(['active' => false], $fakeStripe->products->updated[0]['payload']);
    }
}
