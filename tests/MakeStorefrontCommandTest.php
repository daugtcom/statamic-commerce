<?php

namespace Daugt\Commerce\Tests;

use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

class MakeStorefrontCommandTest extends TestCase
{
    public function test_make_storefront_creates_views_and_cart_partial(): void
    {
        $shopView = resource_path('views/shop/index.antlers.html');
        $cartPartial = resource_path('views/shop/_cart.antlers.html');
        $productView = resource_path('views/products/product.antlers.html');

        File::delete($shopView);
        File::delete($cartPartial);
        File::delete($productView);

        $this->artisan('statamic:daugt-commerce:make-storefront --without-shop-page')->assertExitCode(0);

        $this->assertFileExists($shopView);
        $this->assertFileExists($cartPartial);
        $this->assertFileExists($productView);
        $this->assertStringContainsString('#daugt-commerce-cart', File::get($cartPartial));
    }

    public function test_make_storefront_does_not_overwrite_existing_files_without_force(): void
    {
        $productView = resource_path('views/products/product.antlers.html');

        File::ensureDirectoryExists(dirname($productView));
        File::put($productView, 'custom product template');

        $this->artisan('statamic:daugt-commerce:make-storefront --without-shop-page')->assertExitCode(0);

        $this->assertSame('custom product template', File::get($productView));
    }

    public function test_make_storefront_can_ensure_shop_page_entry(): void
    {
        $pages = CollectionFacade::make('pages');
        $pages->title('Pages');
        $pages->save();

        $this->artisan('statamic:daugt-commerce:make-storefront')->assertExitCode(0);

        $entry = Entry::query()
            ->where('collection', 'pages')
            ->where('slug', 'shop')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('shop/index', $entry->get('template'));
    }
}
