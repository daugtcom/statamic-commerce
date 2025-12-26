<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Jobs\StripeWebhooks\CustomerUpdated;
use Spatie\WebhookClient\Models\WebhookCall;
use Statamic\Facades\User;

class StripeWebhookCustomerUpdatedTest extends TestCase
{
    public function test_customer_updated_syncs_addresses(): void
    {
        $user = User::make()->email('customer@example.test');
        $user->set('stripe_id', 'cus_789');
        $user->saveQuietly();

        $payload = [
            'type' => 'customer.updated',
            'data' => [
                'object' => [
                    'id' => 'cus_789',
                    'name' => 'Grace Hopper',
                    'phone' => '+444',
                    'address' => [
                        'line1' => '1 Ship St',
                        'city' => 'Arlington',
                        'postal_code' => '22201',
                        'country' => 'US',
                    ],
                    'shipping' => [
                        'name' => 'Grace Hopper',
                        'phone' => '+444',
                        'address' => [
                            'line1' => '2 Harbor St',
                            'city' => 'Arlington',
                            'postal_code' => '22202',
                            'country' => 'US',
                        ],
                    ],
                ],
            ],
        ];

        $job = new CustomerUpdated(new WebhookCall(['payload' => $payload]));
        $job->handle();

        $updated = User::find($user->id());
        $this->assertSame('1 Ship St', $updated->get('billing_address')['line1'] ?? null);
        $this->assertSame('2 Harbor St', $updated->get('shipping_address')['line1'] ?? null);
    }
}
