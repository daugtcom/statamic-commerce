# Commerce

Statamic commerce addon with product sync, cart/checkout tags, Stripe webhooks, and optional shop scaffolding.

## Install

```bash
composer require daugtcom/statamic-commerce
```

## Commands

### Install collections/blueprints/taxonomies

```bash
php please daugt-commerce:install
```

### Make optional shop (listing/detail/search/filter/cart)

```bash
php please daugt-commerce:make-shop
```

This creates:
- `resources/views/shop/index.antlers.html`
- `resources/views/shop/_cart.antlers.html`
- `resources/views/products/product.antlers.html`
- `resources/views/checkout.antlers.html`
- optional `pages/shop` entry using template `shop/index` (unless `--without-shop-page`)
- optional `pages/checkout` entry using template `checkout` (unless `--without-checkout-page`)

Add `{{ partial:shop/cart }}` once in your main layout to mount the cart drawer globally.
Shop templates are copied from addon source views in `resources/views/...` (shop index, cart partial, product detail).

Use `--force` to overwrite existing shop files or to re-ensure the generated shop page entries.

Note: product detail routing stays collection-driven via the products collection route (`/shop/product/{slug}`), not by writing to `routes/web.php`.

## Shop Tags

- `{{ daugt_commerce:shop_products }}`:
  filtered product loop (supports `search`, `category`, `sort`, `limit` via params or query string)
- `{{ daugt_commerce:shop_categories }}`:
  category chips with counts and active state

Backward compatibility:
- `statamic:daugt-commerce:make-store` remains available as an alias.
- `statamic:daugt-commerce:make-storefront` remains available as an alias.
- `store_products` and `store_categories` tags remain available as aliases.
- `storefront_products` and `storefront_categories` tags remain available as aliases.
