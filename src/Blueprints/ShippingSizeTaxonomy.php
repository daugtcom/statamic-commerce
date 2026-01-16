<?php

namespace Daugt\Commerce\Blueprints;

use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Taxonomies\Taxonomy;

class ShippingSizeTaxonomy
{
    public function __invoke(): Taxonomy
    {
        $taxonomy = TaxonomyFacade::make('shipping_sizes');
        $taxonomy->title('daugt-commerce::taxonomies.shipping_sizes.title');

        return $taxonomy;
    }
}
