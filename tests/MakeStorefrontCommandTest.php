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
        $checkoutView = resource_path('views/checkout.antlers.html');

        File::delete($shopView);
        File::delete($cartPartial);
        File::delete($productView);
        File::delete($checkoutView);

        $this->artisan('statamic:daugt-commerce:make-storefront --without-shop-page --without-checkout-page')->assertExitCode(0);

        $this->assertFileExists($shopView);
        $this->assertFileExists($cartPartial);
        $this->assertFileExists($productView);
        $this->assertFileExists($checkoutView);
        $this->assertSame(
            rtrim(File::get($this->sourceTemplate('shop/index.antlers.html'))),
            rtrim(File::get($shopView))
        );
        $this->assertSame(
            rtrim(File::get($this->sourceTemplate('shop/_cart.antlers.html'))),
            rtrim(File::get($cartPartial))
        );
        $this->assertSame(
            rtrim(File::get($this->sourceTemplate('products/product.antlers.html'))),
            rtrim(File::get($productView))
        );
        $this->assertSame(
            rtrim(File::get($this->sourceTemplate('checkout.antlers.html'))),
            rtrim(File::get($checkoutView))
        );
    }

    public function test_make_storefront_does_not_overwrite_existing_files_without_force(): void
    {
        $productView = resource_path('views/products/product.antlers.html');

        File::ensureDirectoryExists(dirname($productView));
        File::put($productView, 'custom product template');

        $this->artisan('statamic:daugt-commerce:make-storefront --without-shop-page --without-checkout-page')->assertExitCode(0);

        $this->assertSame('custom product template', File::get($productView));
    }

    public function test_make_storefront_can_ensure_shop_and_checkout_page_entries(): void
    {
        $pages = CollectionFacade::make('pages');
        $pages->title('Pages');
        $pages->save();

        $this->artisan('statamic:daugt-commerce:make-storefront')->assertExitCode(0);

        $shopEntry = Entry::query()
            ->where('collection', 'pages')
            ->where('slug', 'shop')
            ->first();

        $checkoutEntry = Entry::query()
            ->where('collection', 'pages')
            ->where('slug', 'checkout')
            ->first();

        $this->assertNotNull($shopEntry);
        $this->assertNotNull($checkoutEntry);
        $this->assertSame('shop/index', $shopEntry->get('template'));
        $this->assertSame('checkout', $checkoutEntry->get('template'));
    }

    private function sourceTemplate(string $relativePath): string
    {
        return dirname(__DIR__) . '/resources/views/' . ltrim($relativePath, '/');
    }
}
