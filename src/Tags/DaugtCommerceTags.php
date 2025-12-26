<?php

namespace Daugt\Commerce\Tags;

use Daugt\Commerce\Carts\CartManager;
use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Daugt\Commerce\Support\AddonSettings;
use Statamic\Tags\Tags;

class DaugtCommerceTags extends Tags
{
    protected static $handle = 'daugt_commerce';

    public function addToCart(): string
    {
        $productId = $this->productId();
        if (! $productId) {
            return '';
        }

        $quantity = (int) ($this->params->get('quantity', 1));
        if ($quantity <= 0) {
            return '';
        }

        $action = route('statamic.daugt-commerce.cart.add');
        $redirect = $this->params->get('redirect');
        $csrf = csrf_field();

        $content = $this->isPair
            ? $this->parse(['product_id' => $productId, 'quantity' => $quantity])
            : '<button type="submit">Add to cart</button>';

        $redirectField = $redirect ? sprintf(
            '<input type="hidden" name="redirect" value="%s">',
            e($redirect)
        ) : '';

        return sprintf(
            '<form method="POST" action="%s">%s<input type="hidden" name="product_id" value="%s"><input type="hidden" name="quantity" value="%d">%s%s</form>',
            e($action),
            $csrf,
            e($productId),
            $quantity,
            $redirectField,
            $content
        );
    }

    public function removeFromCart(): string
    {
        $productId = $this->productId();
        if (! $productId) {
            return '';
        }

        $action = route('statamic.daugt-commerce.cart.remove');
        $redirect = $this->params->get('redirect');
        $csrf = csrf_field();

        $content = $this->isPair
            ? $this->parse(['product_id' => $productId])
            : '<button type="submit">Remove</button>';

        $redirectField = $redirect ? sprintf(
            '<input type="hidden" name="redirect" value="%s">',
            e($redirect)
        ) : '';

        return sprintf(
            '<form method="POST" action="%s">%s<input type="hidden" name="product_id" value="%s">%s%s</form>',
            e($action),
            $csrf,
            e($productId),
            $redirectField,
            $content
        );
    }

    public function cartItems(): string|array
    {
        $manager = app(CartManager::class);
        $includeEntries = $this->params->get('with_entries', true);

        $items = $manager->items((bool) $includeEntries);

        if (! $this->isPair) {
            return $this->aliasedResult($items);
        }

        if ($items === []) {
            return $this->parseNoResults();
        }

        return $this->parseLoop($items);
    }

    public function cartCount(): string|int
    {
        $manager = app(CartManager::class);
        $cart = $manager->get();
        $count = array_sum($cart['items']);

        if (! $this->isPair) {
            return $count;
        }

        return $this->parse(['count' => $count]);
    }

    public function money(): string
    {
        $raw = $this->params->get('value');

        if ($raw === null) {
            $raw = $this->params->get('amount');
        }

        if ($raw === null && $this->isPair) {
            $raw = trim($this->content);
        }

        if ($raw === null || $raw === '') {
            return '';
        }

        if (! is_numeric($raw)) {
            return '';
        }

        $amount = (float) $raw;
        $currency = $this->params->get('currency')
            ?: AddonSettings::firstValue('currency')
            ?: 'EUR';
        $locale = $this->params->get('locale') ?: app()->getLocale();

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($amount, strtoupper($currency));

        if ($formatted === false) {
            throw new \RuntimeException(sprintf('Unable to format currency [%s] for locale [%s].', $currency, $locale));
        }

        if ($this->isPair) {
            return $this->parse(['value' => $formatted]);
        }

        return $formatted;
    }

    public function checkout(): string
    {
        $extension = $this->activeExtension();
        if (! $extension) {
            return '';
        }

        $definition = $extension->checkoutView($this->params->all());
        if (! is_array($definition) || empty($definition['view'])) {
            return '';
        }

        $data = $definition['data'] ?? [];

        return view($definition['view'], $data)->render();
    }

    private function productId(): ?string
    {
        $param = $this->params->get('product_id')
            ?: $this->params->get('id');

        if (is_string($param) && $param !== '') {
            return $param;
        }

        $contextId = $this->context->raw('id');

        return is_string($contextId) && $contextId !== '' ? $contextId : null;
    }

    private function activeExtension(): ?PaymentProviderExtension
    {
        $resolver = app(PaymentProviderResolver::class);
        $handle = $resolver->providerHandle();
        $definition = config("statamic.daugt-commerce.payment.providers.{$handle}");

        if (! is_array($definition)) {
            return null;
        }

        $extensionClass = $definition['extension'] ?? null;
        if (! $extensionClass || ! is_subclass_of($extensionClass, PaymentProviderExtension::class)) {
            return null;
        }

        $extension = app($extensionClass);

        return $extension instanceof PaymentProviderExtension ? $extension : null;
    }
}
