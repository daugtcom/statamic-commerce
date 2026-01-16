<?php

namespace Daugt\Commerce\Blueprints;

use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class ShippingSizeBlueprint
{
    public function __invoke(): StatamicBlueprint
    {
        $blueprint = BlueprintFacade::makeFromFields([
            'shipping_rates' => [
                'type' => 'grid',
                'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_rates',
                'instructions' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_rates_instructions',
                'fields' => [
                    [
                        'handle' => 'country',
                        'field' => [
                            'type' => 'dictionary',
                            'dictionary' => 'countries',
                            'max_items' => 1,
                            'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_rate_country',
                            'width' => 50,
                        ],
                    ],
                    [
                        'handle' => 'amount',
                        'field' => [
                            'type' => 'float',
                            'min' => 0,
                            'step' => 0.01,
                            'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_rate_amount',
                            'width' => 50,
                            'prepend' => '€',
                        ],
                    ],
                ],
            ],
            'shipping_fallback_rate' => [
                'type' => 'float',
                'min' => 0,
                'step' => 0.01,
                'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_fallback_rate',
                'instructions' => 'daugt-commerce::taxonomies.shipping_sizes.fields.shipping_fallback_rate_instructions',
                'prepend' => '€',
            ],
        ]);

        $blueprint->ensureFieldPrepended('title', [
            'type' => 'text',
            'required' => true,
            'validate' => ['required'],
            'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.title',
        ]);

        $blueprint->ensureField('slug', [
            'type' => 'slug',
            'required' => true,
            'validate' => ['required', 'max:200'],
            'display' => 'daugt-commerce::taxonomies.shipping_sizes.fields.slug',
        ], 'sidebar');

        $contents = $blueprint->contents();
        $contents['title'] = 'daugt-commerce::taxonomies.shipping_sizes.blueprint.title';
        $blueprint->setContents($contents);

        return $blueprint;
    }
}
