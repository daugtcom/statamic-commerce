<?php

namespace Daugt\Commerce\Payments\Checkout;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Daugt\Commerce\Support\AddonEdition;
use Daugt\Commerce\Support\AddonSettings;
use Daugt\Commerce\Support\CountryCodeMapper;
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
        $cartItems = $this->cartItems(true);
        $lineItems = $this->buildLineItems($cartItems);

        if ($lineItems === []) {
            return null;
        }

        $payload = [
            'ui_mode' => $uiMode,
            'line_items' => $lineItems,
            'mode' => $mode,
        ];
        $data = [];

        if ($mode === 'payment') {
            $payload['invoice_creation'] = [
                'enabled' => $params['invoice_creation'] ?? true,
            ];
        }

        if (! empty($params['return_url'])) {
            $payload['return_url'] = $params['return_url'];
        } else {
            $payload['return_url'] = url()->current();
        }

        if (! empty($params['success_url'])) {
            $payload['success_url'] = $params['success_url'];
        }

        if ($mode === 'payment' && $this->requiresShipping($cartItems)) {
            $allowedCountries = $this->allowedCountries();
            if ($allowedCountries !== []) {
                $payload['shipping_address_collection'] = [
                    'allowed_countries' => $allowedCountries,
                ];
            }

            if (AddonEdition::isPro() && $uiMode === 'embedded' && $allowedCountries !== []) {
                $payload['permissions'] = [
                    'update_shipping_details' => 'server_only',
                ];
                $payload['shipping_options'] = [
                    $this->buildShippingOption(0),
                ];
                $data['stripe_shipping_url'] = route('daugt-commerce.stripe.shipping-options');
            } else {
                $flatRate = $this->flatShippingRate();
                if ($flatRate !== null) {
                    $payload['shipping_options'] = [
                        $this->buildShippingOption((int) round($flatRate * 100)),
                    ];
                }
            }
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
                ...$data,
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

    private function requiresShipping(array $cartItems): bool
    {
        foreach ($cartItems as $item) {
            $entry = $item['entry'] ?? null;
            if (! $entry) {
                continue;
            }

            $shipping = method_exists($entry, 'shipping')
                ? $entry->shipping()
                : (bool) $entry->get(ProductEntry::SHIPPING);

            if ($shipping) {
                return true;
            }
        }

        return false;
    }

    private function allowedCountries(): array
    {
        $allowed = AddonSettings::get('shipping_allowed_countries');

        if (! is_array($allowed)) {
            return [];
        }

        return CountryCodeMapper::normalizeList($allowed);
    }

    private function flatShippingRate(): ?float
    {
        $rate = AddonSettings::get('shipping_flat_rate');

        if (is_numeric($rate)) {
            return (float) $rate;
        }

        return null;
    }

    private function buildShippingOption(int $amount): array
    {
        $currency = strtolower(AddonSettings::firstValue('currency') ?? 'EUR');

        return [
            'shipping_rate_data' => [
                'display_name' => __('daugt-commerce::products.fields.shipping'),
                'type' => 'fixed_amount',
                'fixed_amount' => [
                    'amount' => max(0, $amount),
                    'currency' => $currency,
                ],
            ],
        ];
    }
}
