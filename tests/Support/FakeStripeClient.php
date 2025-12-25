<?php

namespace Daugt\Commerce\Tests\Support;

use Stripe\StripeClient;

class FakeStripeClient extends StripeClient
{
    public FakeStripeProductsService $products;
    public FakeStripePricesService $prices;
    public FakeStripeCustomersService $customers;

    public function __construct()
    {
        parent::__construct('test');

        $this->products = new FakeStripeProductsService();
        $this->prices = new FakeStripePricesService();
        $this->customers = new FakeStripeCustomersService();
    }
}

class FakeStripeProductsService
{
    public array $created = [];
    public array $updated = [];

    public function create(array $payload)
    {
        $id = 'prod_' . (count($this->created) + 1);
        $this->created[] = $payload;

        return (object) [
            'id' => $id,
            'active' => $payload['active'] ?? true,
            'name' => $payload['name'] ?? null,
        ];
    }

    public function update(string $id, array $payload)
    {
        $this->updated[] = [
            'id' => $id,
            'payload' => $payload,
        ];

        return (object) [
            'id' => $id,
            'active' => $payload['active'] ?? true,
        ];
    }
}

class FakeStripePricesService
{
    public array $created = [];
    public array $updated = [];
    public array $retrieved = [];

    private array $store = [];

    public function seed(string $id, array $payload): void
    {
        $this->store[$id] = $this->makePrice($id, $payload);
    }

    public function retrieve(string $id, array $params = [])
    {
        $this->retrieved[] = $id;

        return $this->store[$id] ?? $this->makePrice($id, [
            'currency' => 'eur',
            'unit_amount' => 0,
            'recurring' => null,
            'active' => true,
        ]);
    }

    public function create(array $payload)
    {
        $id = 'price_' . (count($this->store) + 1);
        $this->created[] = $payload;

        $price = $this->makePrice($id, $payload);
        $this->store[$id] = $price;

        return $price;
    }

    public function update(string $id, array $payload)
    {
        $this->updated[] = [
            'id' => $id,
            'payload' => $payload,
        ];

        $price = $this->store[$id] ?? $this->makePrice($id, [
            'currency' => 'eur',
            'unit_amount' => 0,
            'recurring' => null,
            'active' => true,
        ]);

        foreach ($payload as $key => $value) {
            $price->$key = $value;
        }

        $this->store[$id] = $price;

        return $price;
    }

    private function makePrice(string $id, array $payload)
    {
        $recurring = $payload['recurring'] ?? null;
        if (is_array($recurring)) {
            $recurring = (object) $recurring;
        }

        $price = new \stdClass();
        $price->id = $id;
        $price->currency = $payload['currency'] ?? 'eur';
        $price->unit_amount = $payload['unit_amount'] ?? null;
        $price->recurring = $recurring;
        $price->active = $payload['active'] ?? true;

        return $price;
    }
}

class FakeStripeCustomersService
{
    public array $created = [];
    public array $updated = [];

    public function create(array $payload)
    {
        $id = 'cus_' . (count($this->created) + 1);
        $this->created[] = $payload;

        return (object) [
            'id' => $id,
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? null,
        ];
    }

    public function update(string $id, array $payload)
    {
        $this->updated[] = [
            'id' => $id,
            'payload' => $payload,
        ];

        return (object) [
            'id' => $id,
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? null,
        ];
    }
}
