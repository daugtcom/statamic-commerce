<?php

namespace Daugt\Commerce\Carts\Stores;

use Daugt\Commerce\Carts\Contracts\CartStore;
use Illuminate\Contracts\Session\Session;

class SessionCartStore implements CartStore
{
    public const SESSION_KEY = 'daugt_commerce.cart';

    public function __construct(private Session $session)
    {
    }

    public function get(): array
    {
        return (array) $this->session->get(self::SESSION_KEY, []);
    }

    public function put(array $cart): void
    {
        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
