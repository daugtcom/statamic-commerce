<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Illuminate\Support\Facades\File;
use Statamic\Addons\Addon as StatamicAddon;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy;

class InstallCommandTest extends TestCase
{
    public function test_install_command_creates_products_and_categories_without_access(): void
    {
        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-access')
            ->andReturn(null);
        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-commerce')
            ->andReturn(null);

        $this->ensureBlueprintDirectories();

        $this->artisan('statamic:daugt-commerce:install')->assertExitCode(0);

        $collection = CollectionFacade::find(ProductEntry::COLLECTION);
        $this->assertNotNull($collection);
        $this->assertSame(ProductEntry::class, $collection->entryClass());

        $blueprint = Blueprint::find('collections/products/product');
        $this->assertNotNull($blueprint);
        $this->assertTrue($blueprint->hasField('title'));
        $this->assertTrue($blueprint->hasField('billing_type'));
        $this->assertFalse($blueprint->hasField('all_access_items'));

        $taxonomy = Taxonomy::find('categories');
        $this->assertNotNull($taxonomy);

        $taxonomyBlueprint = Blueprint::find('taxonomies/categories/category');
        $this->assertNotNull($taxonomyBlueprint);
        $this->assertTrue($taxonomyBlueprint->hasField('description'));
        $this->assertTrue($taxonomyBlueprint->hasField('image'));
    }

    public function test_install_command_includes_access_sets_when_access_is_ready(): void
    {
        config()->set('statamic.daugt-access.entitlements.target_collections', ['courses', 'events', 'missing']);

        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-access')
            ->andReturn(StatamicAddon::make('daugtcom/statamic-access'));
        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-commerce')
            ->andReturn(null);

        $this->ensureBlueprintDirectories();

        CollectionFacade::make('entitlements')->save();
        CollectionFacade::make('courses')->save();
        CollectionFacade::make('events')->save();

        $this->artisan('statamic:daugt-commerce:install')->assertExitCode(0);

        $blueprint = Blueprint::find('collections/products/product');
        $this->assertNotNull($blueprint);
        $this->assertTrue($blueprint->hasField('all_access_items'));

        $replicator = $blueprint->field('all_access_items');
        $this->assertSame('replicator', $replicator->type());

        $sets = $replicator->get('sets');
        $this->assertArrayHasKey('access_items', $sets);
        $this->assertArrayHasKey('courses', $sets['access_items']['sets']);
        $this->assertArrayHasKey('events', $sets['access_items']['sets']);
        $this->assertArrayNotHasKey('missing', $sets['access_items']['sets']);
    }

    private function ensureBlueprintDirectories(): void
    {
        $blueprintsPath = config('statamic.system.blueprints_path');
        $paths = [
            $blueprintsPath . '/collections/' . ProductEntry::COLLECTION,
            $blueprintsPath . '/taxonomies/categories',
        ];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }
}
