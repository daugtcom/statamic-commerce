<?php

namespace Daugt\Commerce\Blueprints;

use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Taxonomies\Taxonomy;

class CategoryTaxonomy
{
    public function __invoke(): Taxonomy
    {
        $taxonomy = TaxonomyFacade::make('categories');
        $taxonomy->title('daugt-commerce::taxonomies.categories.title');

        return $taxonomy;
    }
}
