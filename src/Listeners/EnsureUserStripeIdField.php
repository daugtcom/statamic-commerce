<?php

namespace Daugt\Commerce\Listeners;

use Statamic\Events\UserBlueprintFound;

class EnsureUserStripeIdField
{
    public function handle(UserBlueprintFound $event): void
    {
        $blueprint = $event->blueprint;

        $blueprint->ensureField('stripe_id', [
            'type' => 'text',
            'display' => 'daugt-commerce::users.fields.stripe_id',
            'read_only' => true,
        ], 'sidebar');
    }
}
