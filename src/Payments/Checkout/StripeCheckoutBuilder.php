<?php

namespace Daugt\Commerce\Payments\Checkout;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Entry as EntryFacade;
use Stripe\StripeClient;

class StripeCheckoutBuilder extends AbstractCheckoutBuilder
{
    public function __construct(private StripeClient $stripeClient)
    {
    }

    public function build(array $params): ?array
    {
        $mode = $params['mode'] ?? $this->defaultMode();
        $uiMode = $params['ui_mode'] ?? 'embedded';
        $lineItems = $this->buildLineItems($this->cartItems(true));

        if ($lineItems === []) {
            return null;
        }

        $payload = [
            'ui_mode' => $uiMode,
            'line_items' => $lineItems,
            'mode' => $mode,
        ];

        if (! empty($params['return_url'])) {
            $payload['return_url'] = $params['return_url'];
        } else {
            $payload['return_url'] = url()->current();
        }

        if (! empty($params['success_url'])) {
            $payload['success_url'] = $params['success_url'];
        }

        $user = auth(config('statamic.users.guards.web', 'web'))->user();
        if ($user) {
            $customerId = app(PaymentProviderResolver::class)->store()->getCustomerId($user);
            if ($customerId) {
                $payload['customer'] = $customerId;
            }
        }

        $session = $this->stripeClient->checkout->sessions->create($payload);

        $key = config('statamic.daugt-commerce.payment.providers.stripe.config.key');

        return [
            'view' => 'statamic-commerce::stripe-checkout',
            'data' => [
                'stripe_key' => $key,
                'stripe_client_secret' => $session->client_secret,
            ],
        ];
    }

    private function buildLineItems(array $cartItems): array
    {
        $items = [];

        foreach ($cartItems as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId === '' || $quantity <= 0) {
                continue;
            }

            $entry = $item['entry'] ?? EntryFacade::find($productId);
            if (! $entry) {
                continue;
            }

            $priceId = method_exists($entry, 'stripePriceId')
                ? $entry->stripePriceId()
                : (string) $entry->get(ProductEntry::STRIPE_PRICE_ID);

            if (! $priceId) {
                continue;
            }

            $items[] = [
                'price' => $priceId,
                'quantity' => $quantity,
            ];
        }

        return $items;
    }

    private function defaultMode(): string
    {
        $cartItems = $this->cartItems(true);

        foreach ($cartItems as $item) {
            $entry = $item['entry'] ?? null;
            if (! $entry) {
                continue;
            }

            $billingType = method_exists($entry, 'billingType')
                ? $entry->billingType()
                : $entry->get(ProductEntry::BILLING_TYPE);

            if ((string) $billingType === 'recurring') {
                return 'subscription';
            }
        }

        return 'payment';
    }
}
