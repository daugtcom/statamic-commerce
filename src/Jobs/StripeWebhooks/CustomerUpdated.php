<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Support\StripeAddress;
use Statamic\Facades\User;

class CustomerUpdated extends StripeWebhookJob
{
    public function handle(): void
    {
        $payload = $this->payload();
        $customer = $payload['data']['object'] ?? null;

        if (! is_array($customer)) {
            return;
        }

        $customerId = (string) ($customer['id'] ?? '');
        if ($customerId === '') {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();
        if (! $user) {
            return;
        }

        $billing = StripeAddress::fromCustomer($customer);
        $shipping = StripeAddress::fromShipping($customer['shipping'] ?? null);

        if ($billing !== []) {
            $user->set('billing_address', $billing);
        }

        if ($shipping !== []) {
            $user->set('shipping_address', $shipping);
        }

        if ($billing !== [] || $shipping !== []) {
            $user->saveQuietly();
        }
    }
}
