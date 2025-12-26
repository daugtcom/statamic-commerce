<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Listeners\ApplyPaymentProviderExtensions;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class ProductPaymentBlueprintListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_listener_adds_stripe_tab_for_stripe_provider(): void
    {
        config()->set('statamic.daugt-commerce.payment.provider', 'stripe');

        $blueprint = (new ProductBlueprint())([], false);
        $blueprint->setHandle('collections/products/product');

        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-stripe')
            ->published(true);
        $entry->saveQuietly();

        $listener = $this->app->make(ApplyPaymentProviderExtensions::class);
        $listener->handle(new EntryBlueprintFound($blueprint, $entry));

        $this->assertTrue($blueprint->hasTab('stripe'));
        $this->assertTrue($blueprint->hasField(ProductEntry::STRIPE_TAX_CODE));
        $this->assertTrue($blueprint->hasField(ProductEntry::STRIPE_PRODUCT_ID));
        $this->assertTrue($blueprint->hasField(ProductEntry::STRIPE_PRICE_ID));
    }

    public function test_listener_removes_stripe_tab_for_non_stripe_provider(): void
    {
        $blueprint = (new ProductBlueprint())([], false);
        $blueprint->setHandle('collections/products/product');

        $contents = $blueprint->contents();
        $contents['tabs']['stripe'] = [
            'sections' => [
                [
                    'fields' => [
                        [
                            'handle' => ProductEntry::STRIPE_PRODUCT_ID,
                            'field' => ['type' => 'text'],
                        ],
                    ],
                ],
            ],
        ];
        $blueprint->setContents($contents);

        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-nonstripe')
            ->published(true);
        $entry->saveQuietly();

        $listener = $this->app->make(ApplyPaymentProviderExtensions::class);
        $listener->handle(new EntryBlueprintFound($blueprint, $entry));

        $this->assertFalse($blueprint->hasTab('stripe'));
    }
}
