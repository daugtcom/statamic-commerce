<?php

namespace Daugt\Commerce\Tests;

use Illuminate\Support\Facades\File;

class StorefrontScaffoldCommandTest extends TestCase
{
    public function test_scaffold_storefront_creates_views_and_routes(): void
    {
        $shopView = resource_path('views/shop/index.antlers.html');
        $productView = resource_path('views/products/product.antlers.html');
        $routesPath = base_path('routes/web.php');

        File::ensureDirectoryExists(dirname($routesPath));
        File::put($routesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        File::delete($shopView);
        File::delete($productView);

        $this->artisan('statamic:daugt-commerce:scaffold-storefront')->assertExitCode(0);

        $this->assertFileExists($shopView);
        $this->assertFileExists($productView);

        $routes = File::get($routesPath);

        $this->assertStringContainsString("Route::statamic('shop', 'shop/index');", $routes);
    }

    public function test_scaffold_storefront_does_not_overwrite_existing_files_without_force(): void
    {
        $productView = resource_path('views/products/product.antlers.html');

        File::ensureDirectoryExists(dirname($productView));
        File::put($productView, 'custom product template');

        $this->artisan('statamic:daugt-commerce:scaffold-storefront')->assertExitCode(0);

        $this->assertSame('custom product template', File::get($productView));
    }
}
