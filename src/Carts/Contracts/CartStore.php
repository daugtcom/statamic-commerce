<?php

namespace Daugt\Commerce\Carts\Contracts;

interface CartStore
{
    public function get(): array;

    public function put(array $cart): void;

    public function forget(): void;
}
