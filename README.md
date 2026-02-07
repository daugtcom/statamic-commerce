# Commerce

Statamic commerce addon with product sync, cart/checkout tags, Stripe webhooks, and optional storefront scaffolding.

## Install

```bash
composer require daugtcom/statamic-commerce
```

## Commands

### Install collections/blueprints/taxonomies

```bash
php please statamic:daugt-commerce:install
```

### Make optional storefront (listing/detail/search/filter/cart)

```bash
php please statamic:daugt-commerce:make-storefront
```

This creates:
- `resources/views/shop/index.antlers.html`
- `resources/views/shop/_cart.antlers.html`
- `resources/views/products/product.antlers.html`
- optional `pages/shop` entry using template `shop/index` (unless `--without-shop-page`)

Add `{{ partial:shop/cart }}` once in your main layout to mount the cart drawer globally.
Storefront templates are copied from addon source views in `resources/views/...` (shop index, cart partial, product detail).

Use `--force` to overwrite existing storefront files or to re-ensure the generated shop page entry.

Note: product detail routing stays collection-driven via the products collection route (`/shop/product/{slug}`), not by writing to `routes/web.php`.

## Storefront Tags

- `{{ daugt_commerce:storefront_products }}`:
  filtered product loop (supports `search`, `category`, `sort`, `limit` via params or query string)
- `{{ daugt_commerce:storefront_categories }}`:
  category chips with counts and active state
