<?php

namespace Daugt\Commerce\Listeners;

use Statamic\Events\UserBlueprintFound;

class ExtendUserAddressFields
{
    public function handle(UserBlueprintFound $event): void
    {
        $blueprint = $event->blueprint;

        $blueprint->ensureField('billing_address', $this->addressField('daugt-commerce::users.fields.billing_address'), 'main');
        $blueprint->ensureField('shipping_address', $this->addressField('daugt-commerce::users.fields.shipping_address'), 'main');
    }

    private function addressField(string $display): array
    {
        return [
            'type' => 'group',
            'display' => $display,
            'collapsible' => true,
            'collapsed' => true,
            'fields' => [
                [
                    'import' => 'statamic-commerce::address',
                ],
            ],
        ];
    }
}
