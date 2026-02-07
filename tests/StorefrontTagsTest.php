<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Tags\DaugtCommerceTags;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class StorefrontTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();

        $taxonomy = Taxonomy::make('categories');
        $taxonomy->title('Categories');
        $taxonomy->save();

        $this->makeTerm('wellness', 'Wellness');
        $this->makeTerm('tech', 'Tech');
    }

    public function test_storefront_products_filters_by_search_and_category(): void
    {
        $this->makeProduct('prod-1', 'Breath Course', ['categories::wellness'], true, 'Breathwork basics');
        $this->makeProduct('prod-2', 'Coding Mastery', ['categories::tech'], true, 'Advanced coding');
        $this->makeProduct('prod-3', 'Draft Product', ['categories::wellness'], false, 'Not visible');

        request()->query->replace([
            'search' => 'breath',
            'category' => 'wellness',
        ]);

        $result = $this->makeTags()->storefrontProducts();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductEntry::class, $result[0]);
        $this->assertSame('prod-1', (string) $result[0]->id());
    }

    public function test_storefront_categories_returns_counts_and_active_state(): void
    {
        $this->makeProduct('prod-4', 'Morning Practice', ['categories::wellness'], true);
        $this->makeProduct('prod-5', 'Code Lab', ['categories::tech'], true);
        $this->makeProduct('prod-6', 'Wellness Plus', ['categories::wellness'], true);

        request()->query->replace([
            'category' => 'wellness',
        ]);

        $rows = $this->makeTags()->storefrontCategories();

        $this->assertIsArray($rows);

        $wellness = collect($rows)->firstWhere('slug', 'wellness');
        $tech = collect($rows)->firstWhere('slug', 'tech');

        $this->assertNotNull($wellness);
        $this->assertNotNull($tech);

        $this->assertSame(2, $wellness['count']);
        $this->assertTrue($wellness['active']);
        $this->assertSame(1, $tech['count']);
        $this->assertFalse($tech['active']);
    }

    public function test_storefront_products_tag_pair_renders_product_context(): void
    {
        $this->makeProduct('prod-pair-1', 'Pair Product', ['categories::wellness'], true, 'Pair description');

        $output = Antlers::parse(
            '{{ daugt_commerce:storefront_products }}{{ id }}|{{ title }}{{ /daugt_commerce:storefront_products }}'
        );

        $this->assertSame('prod-pair-1|Pair Product', trim($output));
    }

    public function test_storefront_products_tag_pair_renders_no_results_block(): void
    {
        $output = Antlers::parse(
            '{{ daugt_commerce:storefront_products search="does-not-exist" }}{{ if no_results }}No results{{ else }}{{ title }}{{ /if }}{{ /daugt_commerce:storefront_products }}'
        );

        $this->assertSame('No results', trim($output));
    }

    private function makeTags(array $params = []): DaugtCommerceTags
    {
        $tags = new DaugtCommerceTags();
        $tags->setProperties([
            'parser' => null,
            'content' => '',
            'context' => [],
            'params' => $params,
            'tag' => 'daugt_commerce:storefront_products',
            'tag_method' => 'storefront_products',
        ]);

        return $tags;
    }

    private function makeProduct(
        string $id,
        string $title,
        array $categories = [],
        bool $published = true,
        string $description = ''
    ): ProductEntry {
        $entry = Entry::make()
            ->collection(ProductEntry::COLLECTION)
            ->id($id)
            ->slug($id)
            ->published($published);

        $entry->data([
            ProductEntry::TITLE => $title,
            ProductEntry::BILLING_TYPE => 'one_time',
            ProductEntry::PRICE => 10,
            ProductEntry::CATEGORIES => $categories,
            ProductEntry::DESCRIPTION => $description,
        ]);

        $entry->saveQuietly();

        $entry = Entry::find($id);
        $this->assertInstanceOf(ProductEntry::class, $entry);

        return $entry;
    }

    private function makeTerm(string $slug, string $title): TermContract
    {
        $term = Term::make();
        $term->taxonomy('categories');
        $term->slug($slug);
        $term->data(['title' => $title]);
        $term->save();

        return $term;
    }
}
