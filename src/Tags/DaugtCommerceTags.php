<?php

namespace Daugt\Commerce\Tags;

use Daugt\Commerce\Carts\CartManager;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Daugt\Commerce\Support\AddonSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Tags\Tags;

class DaugtCommerceTags extends Tags
{
    protected static $handle = 'daugt_commerce';

    public function addToCart(): string
    {
        $productId = $this->productId();
        if (! $productId) {
            return '';
        }

        $quantity = (int) ($this->params->get('quantity', 1));
        if ($quantity <= 0) {
            return '';
        }

        $action = route('statamic.daugt-commerce.cart.add');
        $redirect = $this->params->get('redirect');
        $csrf = csrf_field();

        $content = $this->isPair
            ? $this->parse(['product_id' => $productId, 'quantity' => $quantity])
            : '<button type="submit">Add to cart</button>';

        $redirectField = $redirect ? sprintf(
            '<input type="hidden" name="redirect" value="%s">',
            e($redirect)
        ) : '';

        return sprintf(
            '<form method="POST" action="%s">%s<input type="hidden" name="product_id" value="%s"><input type="hidden" name="quantity" value="%d">%s%s</form>',
            e($action),
            $csrf,
            e($productId),
            $quantity,
            $redirectField,
            $content
        );
    }

    public function removeFromCart(): string
    {
        $productId = $this->productId();
        if (! $productId) {
            return '';
        }

        $action = route('statamic.daugt-commerce.cart.remove');
        $redirect = $this->params->get('redirect');
        $csrf = csrf_field();

        $content = $this->isPair
            ? $this->parse(['product_id' => $productId])
            : '<button type="submit">Remove</button>';

        $redirectField = $redirect ? sprintf(
            '<input type="hidden" name="redirect" value="%s">',
            e($redirect)
        ) : '';

        return sprintf(
            '<form method="POST" action="%s">%s<input type="hidden" name="product_id" value="%s">%s%s</form>',
            e($action),
            $csrf,
            e($productId),
            $redirectField,
            $content
        );
    }

    public function cartItems(): string|array
    {
        $manager = app(CartManager::class);
        $includeEntries = $this->params->get('with_entries', true);

        $items = $manager->items((bool) $includeEntries);

        if (! $this->isPair) {
            return $this->aliasedResult($items);
        }

        if ($items === []) {
            return $this->parseNoResults();
        }

        return $this->parseLoop($items);
    }

    public function cartCount(): string|int
    {
        $manager = app(CartManager::class);
        $cart = $manager->get();
        $count = array_sum($cart['items']);

        if (! $this->isPair) {
            return $count;
        }

        return $this->parse(['count' => $count]);
    }

    public function money(): string
    {
        $raw = $this->params->get('value');

        if ($raw === null) {
            $raw = $this->params->get('amount');
        }

        if ($raw === null && $this->isPair) {
            $raw = trim($this->content);
        }

        if ($raw === null || $raw === '') {
            return '';
        }

        if (! is_numeric($raw)) {
            return '';
        }

        $amount = (float) $raw;
        $currency = $this->params->get('currency')
            ?: AddonSettings::firstValue('currency')
            ?: 'EUR';
        $locale = $this->params->get('locale') ?: app()->getLocale();

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, strtoupper($currency));

        if ($formatted === false) {
            throw new \RuntimeException(sprintf('Unable to format currency [%s] for locale [%s].', $currency, $locale));
        }

        if ($this->isPair) {
            return $this->parse(['value' => $formatted]);
        }

        return $formatted;
    }

    public function checkout(): string
    {
        $extension = $this->activeExtension();
        if (! $extension) {
            return '';
        }

        $definition = $extension->checkoutView($this->params->all());
        if (! is_array($definition) || empty($definition['view'])) {
            return '';
        }

        $data = $definition['data'] ?? [];

        return view($definition['view'], $data)->render();
    }

    public function shopProducts(): string|array
    {
        $products = $this->queryShopProducts(
            $this->stringParam('search'),
            $this->stringParam('category'),
            $this->stringParam('sort') ?: 'updated_desc',
            $this->intParam('limit')
        );

        $items = $products->values()->all();

        if (! $this->isPair) {
            return $this->aliasedResult($items);
        }

        if ($items === []) {
            return $this->parseNoResults();
        }

        return (string) $this->parseLoop(
            array_map(
                fn (EntryContract $entry) => $this->shopProductRow($entry),
                $items
            )
        );
    }

    public function storefrontProducts(): string|array
    {
        return $this->shopProducts();
    }

    public function storeProducts(): string|array
    {
        return $this->shopProducts();
    }

    public function shopCategories(): string|array
    {
        $taxonomy = $this->stringParam('taxonomy') ?: ProductEntry::CATEGORIES;
        $activeCategory = $this->stringParam('category');
        $products = $this->queryShopProducts();
        $categoryCounts = $this->categoryCounts($products, $taxonomy);
        $terms = $this->termsForTaxonomy($taxonomy);

        $items = $terms->map(function (TermContract $term) use ($categoryCounts, $activeCategory) {
            $slug = (string) $term->slug();

            return [
                'slug' => $slug,
                'title' => (string) ($term->title() ?: $slug),
                'count' => (int) ($categoryCounts[$slug] ?? 0),
                'active' => $activeCategory !== '' && $activeCategory === $slug,
                'url' => $this->urlWithQuery(['category' => $slug]),
            ];
        })->values()->all();

        if (! $this->isPair) {
            return $this->aliasedResult($items);
        }

        if ($items === []) {
            return $this->parseNoResults();
        }

        return $this->parseLoop($items);
    }

    public function storefrontCategories(): string|array
    {
        return $this->shopCategories();
    }

    public function storeCategories(): string|array
    {
        return $this->shopCategories();
    }

    private function productId(): ?string
    {
        $param = $this->params->get('product_id')
            ?: $this->params->get('id');

        if (is_string($param) && $param !== '') {
            return $param;
        }

        $contextId = $this->context->raw('id');

        return is_string($contextId) && $contextId !== '' ? $contextId : null;
    }

    private function queryShopProducts(
        ?string $search = null,
        ?string $category = null,
        string $sort = 'updated_desc',
        ?int $limit = null
    ): Collection {
        $products = Entry::query()
            ->where('collection', ProductEntry::COLLECTION)
            ->whereStatus('published')
            ->get()
            ->filter(fn ($entry) => $entry instanceof EntryContract);

        if ($search !== null && $search !== '') {
            $needle = Str::lower($search);
            $products = $products->filter(function (EntryContract $entry) use ($needle) {
                $title = Str::lower((string) ($entry->get(ProductEntry::TITLE) ?: ''));
                $description = Str::lower($this->searchableDescription($entry->get(ProductEntry::DESCRIPTION)));

                return Str::contains($title, $needle) || Str::contains($description, $needle);
            });
        }

        if ($category !== null && $category !== '') {
            $products = $products->filter(
                fn (EntryContract $entry) => $this->entryHasCategory($entry, $category)
            );
        }

        $products = $this->sortProducts($products, $sort);

        if ($limit !== null && $limit > 0) {
            $products = $products->take($limit);
        }

        return $products->values();
    }

    private function sortProducts(Collection $products, string $sort): Collection
    {
        return match ($sort) {
            'price_asc' => $products->sortBy(fn (EntryContract $entry) => $this->entryPrice($entry) ?? INF),
            'price_desc' => $products->sortByDesc(fn (EntryContract $entry) => $this->entryPrice($entry) ?? -INF),
            'title_asc' => $products->sortBy(fn (EntryContract $entry) => Str::lower((string) ($entry->get(ProductEntry::TITLE) ?: ''))),
            'title_desc' => $products->sortByDesc(fn (EntryContract $entry) => Str::lower((string) ($entry->get(ProductEntry::TITLE) ?: ''))),
            'updated_asc' => $products->sortBy(fn (EntryContract $entry) => $this->entryTimestamp($entry)),
            default => $products->sortByDesc(fn (EntryContract $entry) => $this->entryTimestamp($entry)),
        };
    }

    private function entryTimestamp(EntryContract $entry): int
    {
        $candidates = [
            $entry->get('updated_at'),
            $entry->get('created_at'),
            $entry->get('date'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \DateTimeInterface) {
                return $candidate->getTimestamp();
            }

            if (is_numeric($candidate)) {
                return (int) $candidate;
            }

            if (is_string($candidate) && $candidate !== '') {
                $timestamp = strtotime($candidate);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        return 0;
    }

    private function searchableDescription(mixed $description): string
    {
        if (is_string($description)) {
            return $description;
        }

        if (is_array($description)) {
            return (string) json_encode($description);
        }

        return '';
    }

    private function shopProductRow(EntryContract $entry): array
    {
        $media = $this->shopProductMedia($entry);
        $descriptionPreview = trim(strip_tags($this->searchableDescription($entry->get(ProductEntry::DESCRIPTION))));

        return [
            'id' => (string) $entry->id(),
            'slug' => (string) $entry->slug(),
            'url' => (string) $entry->url(),
            'title' => (string) ($entry->get(ProductEntry::TITLE) ?? ''),
            'description' => $entry->get(ProductEntry::DESCRIPTION),
            'description_preview' => Str::limit($descriptionPreview, 120),
            'price' => $this->entryPrice($entry),
            'media' => $media,
            'image' => $media[0] ?? null,
            'external_product' => $this->entryExternalProduct($entry),
            'external_product_url' => $this->entryExternalProductUrl($entry),
        ];
    }

    private function shopProductMedia(EntryContract $entry): array
    {
        try {
            $augmented = $entry->augmentedValue(ProductEntry::MEDIA);
            if ($augmented !== null) {
                $value = $augmented->value();

                if ($value instanceof \Statamic\Assets\OrderedQueryBuilder) {
                    $value = $value->get();
                }

                if ($value instanceof Collection) {
                    return $value->values()->all();
                }

                return Arr::wrap($value);
            }
        } catch (\Throwable) {
            // Some test environments do not provide an assets container.
        }

        return Arr::wrap($entry->get(ProductEntry::MEDIA));
    }

    private function entryHasCategory(EntryContract $entry, string $category): bool
    {
        $normalizedCategory = Str::lower($category);
        $terms = $this->normalizeCategoryValues($entry->get(ProductEntry::CATEGORIES), ProductEntry::CATEGORIES);

        return in_array($normalizedCategory, $terms, true);
    }

    private function categoryCounts(Collection $products, string $taxonomy): array
    {
        $counts = [];

        foreach ($products as $entry) {
            if (! $entry instanceof EntryContract) {
                continue;
            }

            foreach ($this->normalizeCategoryValues($entry->get(ProductEntry::CATEGORIES), $taxonomy) as $slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function normalizeCategoryValues(mixed $value, string $taxonomy): array
    {
        $slugs = [];

        foreach (Arr::wrap($value) as $item) {
            if ($item instanceof TermContract) {
                $slug = (string) $item->slug();
                if ($slug !== '') {
                    $slugs[] = Str::lower($slug);
                }
                continue;
            }

            if (! is_string($item) || $item === '') {
                continue;
            }

            $parts = explode('::', $item, 2);
            $slug = count($parts) === 2 ? $parts[1] : $parts[0];
            if ($slug === '') {
                continue;
            }

            if (count($parts) === 2 && $parts[0] !== $taxonomy) {
                continue;
            }

            $slugs[] = Str::lower($slug);
        }

        return array_values(array_unique($slugs));
    }

    private function entryPrice(EntryContract $entry): ?float
    {
        if (method_exists($entry, 'price')) {
            $value = $entry->price();

            return $value !== null ? (float) $value : null;
        }

        $value = $entry->get(ProductEntry::PRICE);

        return $value !== null ? (float) $value : null;
    }

    private function entryExternalProduct(EntryContract $entry): bool
    {
        if (method_exists($entry, 'externalProduct')) {
            return (bool) $entry->externalProduct();
        }

        return (bool) $entry->get(ProductEntry::EXTERNAL_PRODUCT);
    }

    private function entryExternalProductUrl(EntryContract $entry): ?string
    {
        if (method_exists($entry, 'externalProductUrl')) {
            $value = $entry->externalProductUrl();

            return $value !== null ? (string) $value : null;
        }

        $value = $entry->get(ProductEntry::EXTERNAL_PRODUCT_URL);

        return $value !== null ? (string) $value : null;
    }

    private function termsForTaxonomy(string $taxonomy): Collection
    {
        $terms = Term::query()
            ->where('taxonomy', $taxonomy)
            ->get()
            ->filter(fn ($term) => $term instanceof TermContract)
            ->sortBy(fn (TermContract $term) => Str::lower((string) ($term->title() ?: $term->slug())))
            ->values();

        return $terms;
    }

    private function urlWithQuery(array $query): string
    {
        if (! request()) {
            $category = $query['category'] ?? null;
            return is_string($category) && $category !== '' ? '?category=' . $category : '#';
        }

        return request()->fullUrlWithQuery($query);
    }

    private function stringParam(string $key): ?string
    {
        $value = $this->params->get($key);
        if (! is_string($value) || trim($value) === '') {
            $queryValue = request()->query($key);
            if (is_string($queryValue) && trim($queryValue) !== '') {
                return trim($queryValue);
            }

            return null;
        }

        return trim($value);
    }

    private function intParam(string $key): ?int
    {
        $value = $this->params->get($key);
        if ($value === null || $value === '') {
            $value = request()->query($key);
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function activeExtension(): ?PaymentProviderExtension
    {
        $resolver = app(PaymentProviderResolver::class);
        $handle = $resolver->providerHandle();
        $definition = config("statamic.daugt-commerce.payment.providers.{$handle}");

        if (! is_array($definition)) {
            return null;
        }

        $extensionClass = $definition['extension'] ?? null;
        if (! $extensionClass || ! is_subclass_of($extensionClass, PaymentProviderExtension::class)) {
            return null;
        }

        $extension = app($extensionClass);

        return $extension instanceof PaymentProviderExtension ? $extension : null;
    }
}
