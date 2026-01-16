<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Support\StripeAddress;
use Daugt\Commerce\Support\StripePayload;
use Statamic\Facades\User;

class CustomerUpdated extends StripeWebhookJob
{
    public function handle(): void
    {
        $payload = StripePayload::array($this->payload());
        $customer = StripePayload::array($payload, 'data.object');
        $customerId = StripePayload::string($customer, 'id');
        if ($customerId === '') {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();
        if (! $user) {
            return;
        }

        $billing = StripeAddress::fromCustomer($customer);
        $shipping = StripeAddress::fromShipping(StripePayload::array($customer, 'shipping'));
        $fullName = StripePayload::string($customer, 'name');

        if ($billing !== []) {
            $user->set('billing_address', $billing);
        }

        if ($shipping !== []) {
            $user->set('shipping_address', $shipping);
        }

        if ($fullName !== '') {
            $user->set('full_name', $fullName);
        }

        if ($billing !== [] || $shipping !== [] || $fullName !== '') {
            $user->saveQuietly();
        }
    }
}
