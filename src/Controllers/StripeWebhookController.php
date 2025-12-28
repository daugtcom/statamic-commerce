<?php

namespace Daugt\Commerce\Controllers;

use Daugt\Commerce\Jobs\StripeWebhooks\CheckoutSessionCompleted;
use Daugt\Commerce\Jobs\StripeWebhooks\CustomerSubscriptionUpdated;
use Daugt\Commerce\Jobs\StripeWebhooks\CustomerUpdated;
use Daugt\Commerce\Jobs\StripeWebhooks\InvoiceEvent;
use Illuminate\Http\Request;
use Stripe\Webhook;

class StripeWebhookController
{
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        if ($payload === '') {
            return response('Missing payload', 400);
        }

        $secret = config('statamic.daugt-commerce.payment.providers.stripe.config.webhook_secret');

        if ($secret) {
            $signature = $request->header('Stripe-Signature', '');

            try {
                $event = Webhook::constructEvent($payload, $signature, $secret);
            } catch (\Throwable) {
                return response('Invalid signature', 400);
            }

            $data = $event->toArray();
        } else {
            if (! app()->environment('local')) {
                return response('Webhook secret required', 400);
            }

            $data = json_decode($payload, true);
            if (! is_array($data)) {
                return response('Invalid payload', 400);
            }
        }

        $type = $data['type'] ?? null;
        if (! is_string($type) || $type === '') {
            return response('Missing event type', 400);
        }

        $this->dispatchForType($type, $data);

        return response()->json(['received' => true]);
    }

    private function dispatchForType(string $type, array $payload): void
    {
        if (str_starts_with($type, 'checkout.session.')) {
            CheckoutSessionCompleted::dispatch($payload);
            return;
        }

        if ($type === 'customer.updated') {
            CustomerUpdated::dispatch($payload);
            return;
        }

        if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted') {
            CustomerSubscriptionUpdated::dispatch($payload);
            return;
        }

        if (str_starts_with($type, 'invoice.')) {
            InvoiceEvent::dispatch($payload);
        }
    }
}
