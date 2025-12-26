<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Payments\Stores\DummyIdStore;
use Daugt\Commerce\ServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        // allows "clearCachedUrls" to be called during tests in the facade
        Nav::shouldReceive('clearCachedUrls')->zeroOrMoreTimes();
        $this->addToAssertionCount(-1);

        config()->set('statamic.daugt-commerce.payment.provider', 'dummy');
        DummyIdStore::reset();
    }
}
