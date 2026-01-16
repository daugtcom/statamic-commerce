<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Controllers\StripeShippingOptionsController;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingCalculationMethod;
use Daugt\Commerce\Services\ShippingRateCalculator;
use Daugt\Commerce\Services\StripeLineItemProductResolver;
use Daugt\Commerce\Support\AddonSettings;
use Illuminate\Http\Request;
use Statamic\Addons\Addon as StatamicAddon;
use Statamic\Facades\Addon;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Stripe\StripeClient;

class StripeShippingOptionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();

        $addon = StatamicAddon::make('daugtcom/statamic-commerce');
        $addon->editions(['pro']);
        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-commerce')
            ->andReturn($addon);
        Addon::shouldReceive('all')
            ->andReturn(collect([$addon]));
    }

    public function test_shipping_options_updates_checkout_with_calculated_rate(): void
    {
        AddonSettings::set('shipping_flat_rate', 5);
        AddonSettings::set('shipping_calculation_method', ShippingCalculationMethod::SUM->value);
        AddonSettings::set('shipping_allowed_countries', ['GB']);
        AddonSettings::set('currency', 'USD');

        $this->makeProduct('price_ship', true);

        $lineItems = [
            [
                'price' => [
                    'id' => 'price_ship',
                    'product' => 'prod_ship',
                ],
                'quantity' => 2,
            ],
        ];

        $stripeClient = new ShippingOptionsFakeStripeClient([
            'checkout' => new ShippingOptionsFakeCheckoutService($lineItems),
        ]);

        $request = Request::create('/stripe/shipping', 'POST', [
            'checkout_session_id' => 'cs_test_123',
            'shipping_details' => [
                'address' => [
                    'country' => 'GB',
                ],
            ],
        ]);

        $controller = new StripeShippingOptionsController();
        $response = $controller(
            $request,
            $stripeClient,
            new ShippingRateCalculator(new StripeLineItemProductResolver())
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->status());
        $this->assertTrue($payload['value']['succeeded'] ?? false);

        $updates = $stripeClient->checkout->sessions->updated;
        $this->assertCount(1, $updates);
        $this->assertSame('cs_test_123', $updates[0]['id']);
        $this->assertSame(1000, $updates[0]['params']['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] ?? null);
        $this->assertSame('usd', $updates[0]['params']['shipping_options'][0]['shipping_rate_data']['fixed_amount']['currency'] ?? null);
    }

    public function test_shipping_options_rejects_unavailable_countries(): void
    {
        AddonSettings::set('shipping_allowed_countries', ['GB']);

        $stripeClient = new ShippingOptionsFakeStripeClient([
            'checkout' => new ShippingOptionsFakeCheckoutService([]),
        ]);

        $request = Request::create('/stripe/shipping', 'POST', [
            'checkout_session_id' => 'cs_test_123',
            'shipping_details' => [
                'address' => [
                    'country' => 'US',
                ],
            ],
        ]);

        $controller = new StripeShippingOptionsController();
        $response = $controller(
            $request,
            $stripeClient,
            new ShippingRateCalculator(new StripeLineItemProductResolver())
        );

        $payload = $response->getData(true);

        $this->assertSame(400, $response->status());
        $this->assertSame('error', $payload['type'] ?? null);
    }

    private function makeProduct(string $priceId, bool $shipping): ProductEntry
    {
        $entry = Entry::make()->collection(ProductEntry::COLLECTION);
        $entry->set(ProductEntry::TITLE, 'Test Product');
        $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
        $entry->set(ProductEntry::STRIPE_PRODUCT_ID, 'prod_' . $priceId);
        $entry->set(ProductEntry::SHIPPING, $shipping);
        $entry->set(ProductEntry::EXTERNAL_PRODUCT, false);
        $entry->saveQuietly();

        return $entry;
    }
}

class ShippingOptionsFakeStripeClient extends StripeClient
{
    public function __construct(private array $services)
    {
        parent::__construct('sk_test');
    }

    public function getService($name)
    {
        return $this->services[$name] ?? parent::getService($name);
    }
}

class ShippingOptionsFakeCheckoutService
{
    public function __construct(array $lineItems)
    {
        $this->sessions = new ShippingOptionsFakeCheckoutSessionsService($lineItems);
    }

    public ShippingOptionsFakeCheckoutSessionsService $sessions;
}

class ShippingOptionsFakeCheckoutSessionsService
{
    public array $updated = [];

    public function __construct(private array $lineItems)
    {
    }

    public function allLineItems(string $sessionId, array $params = []): array
    {
        return ['data' => $this->lineItems];
    }

    public function update(string $sessionId, array $params = []): array
    {
        $this->updated[] = ['id' => $sessionId, 'params' => $params];

        return ['id' => $sessionId];
    }
}
