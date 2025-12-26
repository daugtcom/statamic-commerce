<?php

namespace Daugt\Commerce\Controllers;

use Daugt\Commerce\Carts\CartManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartController
{
    public function add(Request $request, CartManager $manager): Response
    {
        $productId = (string) $request->input('product_id', '');
        if ($productId === '') {
            return response('Missing product_id.', 400);
        }

        $quantity = (int) $request->input('quantity', 1);
        $manager->add($productId, $quantity);

        if ($request->expectsJson()) {
            return response()->json([
                'cart' => $manager->get(),
            ]);
        }

        $redirect = $request->input('redirect');

        return $redirect ? redirect($redirect) : back();
    }

    public function remove(Request $request, CartManager $manager): Response
    {
        $productId = (string) $request->input('product_id', '');
        if ($productId === '') {
            return response('Missing product_id.', 400);
        }

        $manager->remove($productId);

        if ($request->expectsJson()) {
            return response()->json([
                'cart' => $manager->get(),
            ]);
        }

        $redirect = $request->input('redirect');

        return $redirect ? redirect($redirect) : back();
    }
}
