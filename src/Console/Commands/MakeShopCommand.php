<?php

namespace Daugt\Commerce\Console\Commands;

use Daugt\Commerce\Console\AsciiArt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class MakeShopCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:daugt-commerce:make-shop
        {--force : Overwrite existing shop template files}
        {--without-shop-page : Skip creating a shop page entry}
        {--without-checkout-page : Skip creating a checkout page entry}
        {--page-collection=pages : Collection handle for the shop listing page}
        {--page-slug=shop : Slug for the shop listing page}
        {--checkout-slug=checkout : Slug for the checkout page}';

    protected $aliases = [
        'statamic:daugt-commerce:make-shop',
        'statamic:daugt-commerce:make-store',
        'statamic:daugt-commerce:make-storefront',
    ];

    protected $description = 'Create shop example templates (listing/detail/cart) with optional shop page entry.';

    public function handle(): int
    {
        $this->output->write((new AsciiArt())());

        $force = (bool) $this->option('force');

        foreach ($this->shopTemplateMap() as $source => $destination) {
            $this->publishTemplate($source, $destination, $force);
        }

        $this->ensureShopPages($force);

        $this->newLine();
        $this->info('Shop templates published successfully.');
        $this->line('- Product detail route is managed by the products collection route.');
        $this->line('- Listing route: /shop');
        $this->line('- Checkout route: /checkout');
        $this->line('- Cart drawer partial: resources/views/shop/_cart.antlers.html');
        $this->line('- Include {{ partial:shop/cart }} once in your layout (for example in layout.antlers.html).');

        return self::SUCCESS;
    }

    private function publishTemplate(string $sourcePath, string $destinationPath, bool $force): void
    {
        if (! File::exists($sourcePath)) {
            throw new \RuntimeException(sprintf(
                'Missing shop template source at [%s].',
                $sourcePath
            ));
        }

        if (File::exists($destinationPath) && ! $force) {
            $this->line("Skipped existing file: {$this->relativePath($destinationPath)}");
            return;
        }

        File::ensureDirectoryExists(dirname($destinationPath));
        File::copy($sourcePath, $destinationPath);

        $this->info("Published: {$this->relativePath($destinationPath)}");
    }

    private function ensureShopPages(bool $force): void
    {
        $collectionHandle = (string) $this->option('page-collection');

        if (! (bool) $this->option('without-shop-page')) {
            $this->ensurePageEntry(
                $collectionHandle,
                (string) $this->option('page-slug'),
                'shop/index',
                $force
            );
        }

        if (! (bool) $this->option('without-checkout-page')) {
            $this->ensurePageEntry(
                $collectionHandle,
                (string) $this->option('checkout-slug'),
                'checkout',
                $force
            );
        }
    }

    private function ensurePageEntry(
        string $collectionHandle,
        string $slugOption,
        string $template,
        bool $force
    ): void
    {
        $slug = trim(Str::slug($slugOption), '/');

        if ($slug === '') {
            $this->warn("Page slug is empty after normalization for template [{$template}], skipping page creation.");
            return;
        }

        $collection = Collection::find($collectionHandle);
        if (! $collection) {
            $this->warn("Collection [{$collectionHandle}] not found. Create a page manually that uses template [{$template}].");
            return;
        }

        $existing = Entry::query()
            ->where('collection', $collectionHandle)
            ->where('slug', $slug)
            ->first();

        if ($existing && ! $force) {
            $this->line("Skipped existing page entry: {$collectionHandle}/{$slug}");
            return;
        }

        $entry = $existing ?: Entry::make()
            ->collection($collectionHandle)
            ->slug($slug);

        $title = Str::title(str_replace(['-', '_'], ' ', $slug));

        $entry->published(true);
        $entry->data(array_merge($entry->data()->all(), [
            'title' => $title,
            'template' => $template,
        ]));
        $entry->save();

        $this->info("Ensured page entry: {$collectionHandle}/{$slug} ({$template})");
    }

    private function shopTemplateMap(): array
    {
        return [
            $this->templateSourcePath('shop/index.antlers.html') => resource_path('views/shop/index.antlers.html'),
            $this->templateSourcePath('shop/_cart.antlers.html') => resource_path('views/shop/_cart.antlers.html'),
            $this->templateSourcePath('products/product.antlers.html') => resource_path('views/products/product.antlers.html'),
            $this->templateSourcePath('checkout.antlers.html') => resource_path('views/checkout.antlers.html'),
        ];
    }

    private function templateSourcePath(string $relativePath): string
    {
        return __DIR__ . '/../../../resources/views/' . ltrim($relativePath, '/');
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), '/');
    }
}
