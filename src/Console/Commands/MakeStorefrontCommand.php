<?php

namespace Daugt\Commerce\Console\Commands;

use Daugt\Commerce\Console\AsciiArt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class MakeStorefrontCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:daugt-commerce:make-storefront
        {--force : Overwrite existing storefront files}
        {--without-shop-page : Skip creating a shop page entry}
        {--page-collection=pages : Collection handle for the storefront listing page}
        {--page-slug=shop : Slug for the storefront listing page}';

    protected $description = 'Create storefront example templates (listing/detail/cart) with optional shop page entry.';

    public function handle(): int
    {
        $this->output->write((new AsciiArt())());

        $force = (bool) $this->option('force');

        $files = [
            resource_path('views/shop/index.antlers.html') => $this->shopIndexTemplate(),
            resource_path('views/shop/_cart.antlers.html') => $this->shopCartTemplate(),
            resource_path('views/products/product.antlers.html') => $this->productTemplate(),
        ];

        foreach ($files as $destination => $contents) {
            $this->publishTemplate($destination, $contents, $force);
        }

        if (! (bool) $this->option('without-shop-page')) {
            $this->ensureShopPage($force);
        }

        $this->newLine();
        $this->info('Storefront make command complete.');
        $this->line('- Product detail route is managed by the products collection route.');
        $this->line('- Listing route: /shop');
        $this->line('- Cart drawer partial: resources/views/shop/_cart.antlers.html');
        $this->line('- Include {{ partial:shop/cart }} once in your layout (for example in layout.antlers.html).');

        return self::SUCCESS;
    }

    private function publishTemplate(string $destinationPath, string $contents, bool $force): void
    {
        if (File::exists($destinationPath) && ! $force) {
            $this->line("Skipped existing file: {$this->relativePath($destinationPath)}");
            return;
        }

        File::ensureDirectoryExists(dirname($destinationPath));
        File::put($destinationPath, rtrim($contents) . PHP_EOL);

        $this->info("Published: {$this->relativePath($destinationPath)}");
    }

    private function ensureShopPage(bool $force): void
    {
        $collectionHandle = (string) $this->option('page-collection');
        $slug = (string) $this->option('page-slug');
        $slug = trim(Str::slug($slug), '/');

        if ($slug === '') {
            $this->warn('Shop page slug is empty after normalization, skipping shop page creation.');
            return;
        }

        $collection = Collection::find($collectionHandle);
        if (! $collection) {
            $this->warn("Collection [{$collectionHandle}] not found. Create a page manually that uses template [shop/index].");
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
            'template' => 'shop/index',
        ]));
        $entry->save();

        $this->info("Ensured page entry: {$collectionHandle}/{$slug}");
    }

    private function shopIndexTemplate(): string
    {
        return <<<'ANTLERS'
<div class="container mx-auto mt-28 px-4 pb-12">
    <div class="mb-8 rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-slate-900">Shop</h1>

            <button
                type="button"
                class="relative inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700"
                onclick="document.querySelector('#daugt-commerce-cart')?.showModal()"
            >
                Cart
                {{ if daugt_commerce:cart_count != "0" }}
                    <span class="ml-2 inline-flex size-5 items-center justify-center rounded-full bg-slate-900 text-xs text-white">
                        {{ daugt_commerce:cart_count }}
                    </span>
                {{ /if }}
            </button>
        </div>

        <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-center">
            <input
                type="search"
                name="search"
                value="{{ get:search }}"
                placeholder="Search products"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />

            {{ if get:category }}
                <input type="hidden" name="category" value="{{ get:category }}" />
            {{ /if }}

            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                Search
            </button>

            {{ if get:search || get:category }}
                <a href="{{ current_url }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                    Reset
                </a>
            {{ /if }}
        </form>

        <div class="mt-4 flex flex-wrap gap-2">
            <a
                href="{{ current_url }}"
                class="rounded-full border px-3 py-1 text-sm {{ if !get:category }}border-slate-900 bg-slate-900 text-white{{ else }}border-slate-300 text-slate-700{{ /if }}"
            >
                All
            </a>

            {{ daugt_commerce:storefront_categories }}
                <a
                    href="{{ url }}"
                    class="rounded-full border px-3 py-1 text-sm {{ if active }}border-slate-900 bg-slate-900 text-white{{ else }}border-slate-300 text-slate-700{{ /if }}"
                >
                    {{ title }}
                    <span class="ml-1 text-xs opacity-80">{{ count }}</span>
                </a>
            {{ /daugt_commerce:storefront_categories }}
        </div>
    </div>

    {{ daugt_commerce:storefront_products search="{get:search}" category="{get:category}" }}
        {{ if no_results }}
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                No products found for your current filters.
            </div>
        {{ else }}
            <article class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="md:flex">
                    <a href="{{ url }}" class="block md:w-72">
                        {{ if media }}
                            <img src="{{ glide :src="media[0]" width="900" height="600" fit="crop" }}" alt="{{ title }}" class="h-52 w-full object-cover md:h-full" />
                        {{ else }}
                            <div class="h-52 w-full bg-slate-100 md:h-full"></div>
                        {{ /if }}
                    </a>

                    <div class="flex flex-1 flex-col p-5">
                        <div class="mb-2 flex items-start justify-between gap-4">
                            <h2 class="text-xl font-semibold text-slate-900">
                                <a href="{{ url }}">{{ title }}</a>
                            </h2>
                            <div class="shrink-0 text-base font-semibold text-slate-900">
                                {{ daugt_commerce:money :value="price" }}
                            </div>
                        </div>

                        {{ if description }}
                            <div class="mb-4 text-sm text-slate-600 line-clamp-3">{{ description | strip_tags }}</div>
                        {{ /if }}

                        <div class="mt-auto flex flex-wrap items-center gap-2">
                            {{ if external_product && external_product_url }}
                                <a href="{{ external_product_url }}" target="_blank" rel="noopener" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                                    Buy external
                                </a>
                            {{ else }}
                                {{ daugt_commerce:add_to_cart }}
                                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                                        Add to cart
                                    </button>
                                {{ /daugt_commerce:add_to_cart }}
                            {{ /if }}

                            <a href="{{ url }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                                View details
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        {{ /if }}
    {{ /daugt_commerce:storefront_products }}
</div>
ANTLERS;
    }

    private function shopCartTemplate(): string
    {
        $source = __DIR__ . '/../../../resources/views/shop/_cart.antlers.html';

        if (! File::exists($source)) {
            throw new \RuntimeException('Missing storefront cart template at resources/views/shop/_cart.antlers.html');
        }

        return File::get($source);
    }

    private function productTemplate(): string
    {
        return <<<'ANTLERS'
<div class="container mx-auto mt-28 px-4 pb-12">
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="/shop" class="inline-flex items-center text-sm font-medium text-slate-600 hover:text-slate-900">
            ← Back to shop
        </a>
        <button
            type="button"
            class="relative inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700"
            onclick="document.querySelector('#daugt-commerce-cart')?.showModal()"
        >
            Cart
            {{ if daugt_commerce:cart_count != "0" }}
                <span class="ml-2 inline-flex size-5 items-center justify-center rounded-full bg-slate-900 text-xs text-white">
                    {{ daugt_commerce:cart_count }}
                </span>
            {{ /if }}
        </button>
    </div>

    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:flex">
        <div class="md:w-1/2">
            {{ if media }}
                <img src="{{ glide :src="media[0]" width="1200" height="800" fit="crop" }}" alt="{{ title }}" class="h-full w-full object-cover" />
            {{ else }}
                <div class="h-96 w-full bg-slate-100"></div>
            {{ /if }}
        </div>

        <div class="flex flex-1 flex-col p-6">
            <div class="mb-3 flex items-start justify-between gap-4">
                <h1 class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
                <div class="text-xl font-semibold text-slate-900">{{ daugt_commerce:money :value="price" }}</div>
            </div>

            {{ if description }}
                <div class="prose prose-slate mb-6 max-w-none">{{ description }}</div>
            {{ /if }}

            <div class="mt-auto flex flex-wrap gap-2">
                {{ if external_product && external_product_url }}
                    <a href="{{ external_product_url }}" target="_blank" rel="noopener" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                        Buy external
                    </a>
                {{ else }}
                    {{ daugt_commerce:add_to_cart }}
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                            Add to cart
                        </button>
                    {{ /daugt_commerce:add_to_cart }}
                {{ /if }}
            </div>
        </div>
    </article>
</div>
ANTLERS;
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), '/');
    }
}
