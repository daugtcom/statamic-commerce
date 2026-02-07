<?php

namespace Daugt\Commerce\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Statamic\Console\RunsInPlease;

class ScaffoldStorefrontCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:daugt-commerce:scaffold-storefront
        {--force : Overwrite existing scaffold files}
        {--without-routes : Skip updating routes/web.php}';

    protected $description = 'Scaffold optional storefront templates and route wiring (listing/detail/search/filter).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $templates = [
            [
                'stub' => 'views/shop/index.antlers.html.stub',
                'destination' => resource_path('views/shop/index.antlers.html'),
            ],
            [
                'stub' => 'views/products/product.antlers.html.stub',
                'destination' => resource_path('views/products/product.antlers.html'),
            ],
        ];

        foreach ($templates as $template) {
            $this->publishStub(
                $template['stub'],
                $template['destination'],
                $force
            );
        }

        if (! (bool) $this->option('without-routes')) {
            $this->ensureStorefrontRoutes();
        }

        $this->newLine();
        $this->info('Storefront scaffolding complete.');
        $this->line('- Listing route: /shop');
        $this->line('- Product detail route: collection route (/shop/product/{slug})');
        $this->line('- Reuses existing cart tags/components.');

        return self::SUCCESS;
    }

    private function publishStub(string $stubRelativePath, string $destinationPath, bool $force): void
    {
        $sourcePath = $this->stubBasePath() . '/' . ltrim($stubRelativePath, '/');

        if (! File::exists($sourcePath)) {
            $this->warn("Missing scaffold stub: {$stubRelativePath}");
            return;
        }

        if (File::exists($destinationPath) && ! $force) {
            $this->line("Skipped existing file: {$this->relativePath($destinationPath)}");
            return;
        }

        File::ensureDirectoryExists(dirname($destinationPath));
        File::copy($sourcePath, $destinationPath);

        $this->info("Published: {$this->relativePath($destinationPath)}");
    }

    private function ensureStorefrontRoutes(): void
    {
        $routesPath = base_path('routes/web.php');

        if (! File::exists($routesPath)) {
            File::ensureDirectoryExists(dirname($routesPath));
            File::put($routesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n");
        }

        $existing = File::get($routesPath);

        if (str_contains($existing, "Route::statamic('shop', 'shop/index')")) {
            $this->line('Storefront route already present in routes/web.php');
            return;
        }

        $stub = File::get($this->stubBasePath() . '/routes/web.php.stub');

        $separator = str_ends_with($existing, "\n") ? '' : "\n";
        File::put($routesPath, $existing . $separator . "\n" . rtrim($stub) . "\n");

        $this->info('Updated: routes/web.php');
    }

    private function stubBasePath(): string
    {
        return __DIR__ . '/../../../resources/stubs/storefront';
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), '/');
    }
}
