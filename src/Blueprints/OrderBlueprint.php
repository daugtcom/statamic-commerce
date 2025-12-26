<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Commerce\Enums\ShippingStatus;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class OrderBlueprint
{
    public function __invoke(): StatamicBlueprint
    {
        $blueprint = BlueprintFacade::make();
        $blueprint->setHidden(true);

        $tabs = [
            'title' => 'daugt-commerce::orders.blueprint.title',
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'order_number',
                                    'field' => [
                                        'type' => 'integer',
                                        'display' => 'daugt-commerce::orders.fields.order_number',
                                        'read_only' => true,
                                        'width' => 33,
                                    ],
                                ],
                                [
                                    'handle' => 'status',
                                    'field' => [
                                        'dictionary' => 'order_statuses',
                                        'default' => OrderStatus::PENDING->value,
                                        'type' => 'dictionary',
                                        'display' => 'daugt-commerce::orders.fields.status',
                                        'width' => 33,
                                        'max_items' => 1,
                                    ],
                                ],
                                [
                                    'handle' => 'user',
                                    'field' => [
                                        'type' => 'users',
                                        'max_items' => 1,
                                        'display' => 'daugt-commerce::orders.fields.user',
                                        'read_only' => true,
                                        'width' => 33,
                                    ],
                                ],
                                [
                                    'handle' => 'succeeded_at',
                                    'field' => [
                                        'type' => 'date',
                                        'mode' => 'single',
                                        'time_enabled' => true,
                                        'display' => 'daugt-commerce::orders.fields.succeeded_at',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => 'daugt-commerce::orders.sections.customer',
                            'fields' => [
                                [
                                    'handle' => 'billing_address',
                                    'field' => $this->addressField('daugt-commerce::orders.fields.billing_address'),
                                ],
                                [
                                    'handle' => 'shipping_address',
                                    'field' => $this->addressField('daugt-commerce::orders.fields.shipping_address'),
                                ],
                            ],
                        ],
                        [
                            'display' => 'daugt-commerce::orders.sections.items',
                            'fields' => [
                                [
                                    'handle' => 'items',
                                    'field' => [
                                        'type' => 'replicator',
                                        'display' => 'daugt-commerce::orders.fields.items',
                                        'button_label' => 'daugt-commerce::orders.fields.items_button',
                                        'sets' => [
                                            'item' => [
                                                'display' => 'daugt-commerce::orders.fields.item',
                                                'fields' => [
                                                    [
                                                        'handle' => 'product',
                                                        'field' => [
                                                            'collections' => [ProductEntry::COLLECTION],
                                                            'mode' => 'select',
                                                            'max_items' => 1,
                                                            'type' => 'entries',
                                                            'display' => 'daugt-commerce::orders.fields.item_product',
                                                        ],
                                                    ],
                                                    [
                                                        'handle' => 'quantity',
                                                        'field' => [
                                                            'min' => 1,
                                                            'step' => 1,
                                                            'default' => 1,
                                                            'type' => 'integer',
                                                            'display' => 'daugt-commerce::orders.fields.item_quantity',
                                                            'width' => 33,
                                                        ],
                                                    ],
                                                    [
                                                        'handle' => 'shipping_status',
                                                        'field' => [
                                                            'dictionary' => 'shipping_statuses',
                                                            'default' => ShippingStatus::PENDING->value,
                                                            'type' => 'dictionary',
                                                            'display' => 'daugt-commerce::orders.fields.item_shipping_status',
                                                            'width' => 33,
                                                            'max_items' => 1,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
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

    private function addressField(string $display, bool $readOnly = false): array
    {
        return [
            'type' => 'group',
            'display' => $display,
            'fields' => [
                [
                    'import' => 'statamic-commerce::address',
                    'config' => $readOnly ? $this->addressReadOnlyOverrides() : [],
                ],
            ],
        ];
    }

    private function addressReadOnlyOverrides(): array
    {
        return [
            'name' => ['read_only' => true],
            'phone' => ['read_only' => true],
            'line1' => ['read_only' => true],
            'line2' => ['read_only' => true],
            'city' => ['read_only' => true],
            'state' => ['read_only' => true],
            'postal_code' => ['read_only' => true],
            'country' => ['read_only' => true],
        ];
    }
}
