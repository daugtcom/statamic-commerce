<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Facades\Collection as CollectionFacade;

class ProductBlueprintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CollectionFacade::make('courses')->title('Courses')->save();
        CollectionFacade::make('events')->title('Events')->save();
    }

    public function test_blueprint_includes_access_fields_when_enabled(): void
    {
        $blueprint = (new ProductBlueprint())(['courses', 'events'], true);

        $this->assertTrue($blueprint->hasField('external_product'));
        $this->assertTrue($blueprint->hasField('external_product_url'));
        $this->assertTrue($blueprint->hasField('all_access_items'));

        $replicator = $blueprint->field('all_access_items');
        $sets = $replicator->get('sets');

        $this->assertArrayHasKey('access_items', $sets);
        $this->assertArrayHasKey('courses', $sets['access_items']['sets']);
        $this->assertArrayHasKey('events', $sets['access_items']['sets']);

        $courseSet = $sets['access_items']['sets']['courses'];
        $this->assertSame('courses', $courseSet['fields'][0]['handle']);
    }

    public function test_blueprint_skips_access_replicator_when_disabled(): void
    {
        $blueprint = (new ProductBlueprint())([], false);

        $this->assertTrue($blueprint->hasField('external_product'));
        $this->assertTrue($blueprint->hasField('external_product_url'));
        $this->assertFalse($blueprint->hasField('all_access_items'));
    }

    public function test_blueprint_filters_missing_access_collections(): void
    {
        $blueprint = (new ProductBlueprint())(['courses', 'missing'], true);

        $replicator = $blueprint->field('all_access_items');
        $sets = $replicator->get('sets');

        $this->assertArrayHasKey('courses', $sets['access_items']['sets']);
        $this->assertArrayNotHasKey('missing', $sets['access_items']['sets']);
    }
}
