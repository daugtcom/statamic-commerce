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

### Scaffold optional storefront (listing/detail/search/filter)

```bash
php please statamic:daugt-commerce:scaffold-storefront
```

This scaffolds:
- `resources/views/shop/index.antlers.html`
- `resources/views/products/product.antlers.html` (if missing)
- `routes/web.php` entry for `/shop`

Use `--force` to overwrite scaffolded templates.

## Storefront Tags

- `{{ daugt_commerce:storefront_products }}`:
  filtered product loop (supports `search`, `category`, `sort`, `limit` via params or query string)
- `{{ daugt_commerce:storefront_categories }}`:
  category chips with counts and active state
