<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Enums\InvoiceStatus;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class InvoiceBlueprint
{
    public function __invoke(): StatamicBlueprint
    {
        $blueprint = BlueprintFacade::make();
        $blueprint->setHidden(true);

        $tabs = [
            'title' => 'daugt-commerce::invoices.blueprint.title',
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'order',
                                    'field' => [
                                        'collections' => [OrderEntry::COLLECTION],
                                        'mode' => 'select',
                                        'max_items' => 1,
                                        'type' => 'entries',
                                        'display' => 'daugt-commerce::invoices.fields.order',
                                        'read_only' => true,
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'user',
                                    'field' => [
                                        'type' => 'users',
                                        'max_items' => 1,
                                        'display' => 'daugt-commerce::invoices.fields.user',
                                        'read_only' => true,
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'status',
                                    'field' => [
                                        'dictionary' => 'invoice_statuses',
                                        'default' => InvoiceStatus::PENDING->value,
                                        'type' => 'dictionary',
                                        'display' => 'daugt-commerce::invoices.fields.status',
                                        'max_items' => 1,
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
}
