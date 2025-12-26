<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Listeners\ApplyPaymentProviderExtensions;
use Statamic\Events\UserBlueprintFound;
use Statamic\Facades\Blueprint as BlueprintFacade;

class UserPaymentBlueprintListenerTest extends TestCase
{
    public function test_listener_adds_stripe_field_for_stripe_provider(): void
    {
        config()->set('statamic.daugt-commerce.payment.provider', 'stripe');

        $blueprint = BlueprintFacade::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        ['fields' => []],
                    ],
                ],
            ],
        ]);

        $listener = $this->app->make(ApplyPaymentProviderExtensions::class);
        $listener->handleUser(new UserBlueprintFound($blueprint));

        $this->assertTrue($blueprint->hasField('stripe_id'));
    }

    public function test_listener_removes_stripe_field_for_non_stripe_provider(): void
    {
        $blueprint = BlueprintFacade::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'stripe_id',
                                    'field' => ['type' => 'text'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $listener = $this->app->make(ApplyPaymentProviderExtensions::class);
        $listener->handleUser(new UserBlueprintFound($blueprint));

        $this->assertFalse($blueprint->hasField('stripe_id'));
    }
}
