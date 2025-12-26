<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Enums\AccessType;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class ProductBlueprint
{
    public function __invoke(
        array $accessCollections = [],
        bool $includeAccess = false
    ): StatamicBlueprint
    {
        $blueprint = BlueprintFacade::make();
        $tabs = [
            'title' => 'daugt-commerce::products.blueprint.title',
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'title',
                                    'field' => [
                                        'type' => 'text',
                                        'required' => true,
                                        'validate' => ['required'],
                                        'display' => 'daugt-commerce::products.fields.title',
                                    ],
                                ],
                                [
                                    'handle' => 'description',
                                    'field' => [
                                        'smart_typography' => true,
                                        'link_noopener' => true,
                                        'link_noreferrer' => true,
                                        'target_blank' => true,
                                        'container' => 'assets',
                                        'remove_empty_nodes' => false,
                                        'type' => 'bard',
                                        'display' => 'daugt-commerce::products.fields.description',
                                        'sets' => [
                                            'new_set_group' => [
                                                'display' => 'daugt-commerce::products.fields.description_set_group',
                                                'sets' => [
                                                    'new_set' => [
                                                        'display' => 'daugt-commerce::products.fields.description_set',
                                                        'image' => null,
                                                        'fields' => [],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'handle' => 'categories',
                                    'field' => [
                                        'taxonomies' => ['categories'],
                                        'mode' => 'typeahead',
                                        'type' => 'terms',
                                        'display' => 'daugt-commerce::products.fields.categories',
                                        'instructions' => 'daugt-commerce::products.fields.categories_instructions',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => 'daugt-commerce::products.sections.pricing',
                            'fields' => [
                                [
                                    'handle' => 'price',
                                    'field' => [
                                        'min' => 0,
                                        'step' => 0.01,
                                        'type' => 'float',
                                        'display' => 'daugt-commerce::products.fields.price',
                                        'width' => 50,
                                        'prepend' => '€',
                                    ],
                                ],
                                [
                                    'handle' => 'billing_type',
                                    'field' => [
                                        'dictionary' => 'billing_types',
                                        'default' => 'one_time',
                                        'type' => 'dictionary',
                                        'display' => 'daugt-commerce::products.fields.billing_type',
                                        'width' => 50,
                                        'max_items' => 1,
                                    ],
                                ],
                                [
                                    'handle' => 'subscription_interval',
                                    'field' => [
                                        'min' => 1,
                                        'step' => 1,
                                        'default' => '1',
                                        'type' => 'integer',
                                        'display' => 'daugt-commerce::products.fields.subscription_interval',
                                        'width' => 33,
                                        'if' => [
                                            'billing_type' => 'equals recurring',
                                        ],
                                    ],
                                ],
                                [
                                    'handle' => 'subscription_interval_unit',
                                    'field' => [
                                        'dictionary' => 'billing_units',
                                        'max_items' => 1,
                                        'default' => 'month',
                                        'type' => 'dictionary',
                                        'display' => 'daugt-commerce::products.fields.subscription_interval_unit',
                                        'width' => 66,
                                        'if' => [
                                            'billing_type' => 'equals recurring',
                                        ],
                                    ],
                                ],
                                [
                                    'handle' => 'subscription_duration',
                                    'field' => [
                                        'default' => 'permanent',
                                        'type' => 'dictionary',
                                        'dictionary' => 'billing_duration_types',
                                        'display' => 'daugt-commerce::products.fields.subscription_duration',
                                        'width' => 50,
                                        'if' => [
                                            'billing_type' => 'equals recurring',
                                        ],
                                        'max_items' => 1,
                                    ],
                                ],
                                [
                                    'handle' => 'subscription_duration_iterations',
                                    'field' => [
                                        'min' => 1,
                                        'step' => 1,
                                        'type' => 'integer',
                                        'display' => 'daugt-commerce::products.fields.iterations',
                                        'width' => 50,
                                        'if' => [
                                            'subscription_duration' => 'equals limited',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => 'daugt-commerce::products.sections.media',
                            'fields' => [
                                [
                                    'handle' => 'media',
                                    'field' => [
                                        'container' => 'assets',
                                        'type' => 'assets',
                                        'display' => 'daugt-commerce::products.fields.media',
                                        'instructions' => 'daugt-commerce::products.fields.media_instructions',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => 'daugt-commerce::products.sections.access',
                            'collapsible' => true,
                            'fields' => $this->accessFields($accessCollections, $includeAccess),
                        ],
                    ],
                ],
                'shipping' => [
                    'sections' => [
                        [
                            'display' => 'daugt-commerce::products.sections.shipping',
                            'fields' => [
                                [
                                    'handle' => 'shipping',
                                    'field' => [
                                        'type' => 'toggle',
                                        'display' => 'daugt-commerce::products.fields.shipping',
                                        'instructions' => 'daugt-commerce::products.fields.shipping_instructions',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'sidebar' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'slug',
                                    'field' => [
                                        'type' => 'slug',
                                        'localizable' => true,
                                        'validate' => 'max:200',
                                        'display' => 'daugt-commerce::products.fields.slug',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $blueprint->setContents($tabs);

        return $blueprint;
    }

    private function accessFields(array $accessCollections, bool $includeAccess): array
    {
        $fields = [
            [
                'handle' => 'external_product',
                'field' => [
                    'type' => 'toggle',
                    'display' => 'daugt-commerce::products.fields.external_product',
                    'instructions' => 'daugt-commerce::products.fields.external_product_instructions',
                ],
            ],
            [
                'handle' => 'external_product_url',
                'field' => [
                    'type' => 'link',
                    'display' => 'daugt-commerce::products.fields.external_product_url',
                    'instructions' => 'daugt-commerce::products.fields.external_product_url_instructions',
                    'actions' => false,
                    'if' => [
                        'external_product' => 'equals true',
                    ],
                ],
            ],
        ];

        if (! $includeAccess || empty($accessCollections)) {
            return $fields;
        }

        $fields[] = [
            'handle' => 'all_access_items',
            'field' => [
                'type' => 'replicator',
                'display' => 'daugt-commerce::products.fields.all_access_items',
                'button_label' => 'daugt-commerce::products.fields.all_access_items_button',
                'sets' => [
                    'access_items' => [
                        'display' => 'daugt-commerce::products.fields.access_items_group',
                        'sets' => $this->accessSets($accessCollections),
                    ],
                ],
                'if' => [
                    'external_product' => 'equals false',
                ],
            ],
        ];

        return $fields;
    }

    private function accessSets(array $accessCollections): array
    {
        $sets = [];

        foreach ($accessCollections as $collectionHandle) {
            $collectionHandle = (string) $collectionHandle;
            if ($collectionHandle === '') {
                continue;
            }

            $collection = CollectionFacade::find($collectionHandle);
            if (! $collection) {
                continue;
            }

            $collectionTitle = $collection->title();
            $display = $collectionTitle ?: $collectionHandle;

            $sets[$collectionHandle] = [
                'display' => $display,
                'image' => null,
                'fields' => [
                    [
                        'handle' => $collectionHandle,
                        'field' => [
                            'collections' => [$collectionHandle],
                            'mode' => 'select',
                            'max_items' => 1,
                            'type' => 'entries',
                            'display' => 'daugt-commerce::products.fields.access_item_entry',
                        ],
                    ],
                    [
                        'handle' => 'access_type',
                        'field' => [
                            'dictionary' => 'access_types',
                            'default' => AccessType::PERMANENT->value,
                            'type' => 'dictionary',
                            'display' => 'daugt-commerce::products.fields.access_type',
                            'max_items' => 1,
                        ],
                    ],
                    [
                        'handle' => 'date_range',
                        'field' => [
                            'mode' => 'range',
                            'time_enabled' => true,
                            'type' => 'date',
                            'display' => 'daugt-commerce::products.fields.access_date_range',
                            'if' => [
                                'access_type' => 'equals date_range',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'access_duration_iterations',
                        'field' => [
                            'min' => 1,
                            'step' => 1,
                            'default' => '1',
                            'type' => 'integer',
                            'display' => 'daugt-commerce::products.fields.access_duration_iterations',
                            'if' => [
                                'access_type' => 'equals duration',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'access_duration_unit',
                        'field' => [
                            'dictionary' => 'billing_units',
                            'max_items' => 1,
                            'default' => 'month',
                            'type' => 'dictionary',
                            'display' => 'daugt-commerce::products.fields.access_duration_unit',
                            'if' => [
                                'access_type' => 'equals duration',
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $sets;
    }
}
