---
name: wp-woocommerce
description: "Use when developing WooCommerce plugins or extensions: WooCommerce architecture, hooks (actions/filters), product types, cart/checkout flow, order management, and WooCommerce REST API."
compatibility: "Targets WooCommerce 8.0+ (HPOS compatible). Requires WordPress 6.0+."
---

# WooCommerce Development

## When to use

Use this skill for WooCommerce development tasks such as:

- creating WooCommerce plugins or extensions
- adding custom product types or data stores
- modifying cart, checkout, or order flows
- integrating with WooCommerce REST API (v3)
- handling WooCommerce webhooks
- working with HPOS (High-Performance Order Storage)

## Key WooCommerce Concepts

### Architecture

- WooCommerce uses its own post types: `product`, `shop_order`, `shop_coupon`
- Data stored in `wp_wc_orders` (HPOS) or post meta (legacy)
- Core classes: `WC_Product`, `WC_Cart`, `WC_Checkout`, `WC_Order`
- Hooks follow `woocommerce_` and `wc_` prefixes

### Product Types

- Simple, Variable, Grouped, External
- Custom product types extend `WC_Product` class
- Product data stored via `WC_Product::read_props()` and getters

### Cart & Checkout

- `WC_Cart` handles cart calculations and session
- `WC_Checkout` manages checkout flow and order creation
- `wc_add_cart_item` / `wc_remove_cart_item` filters
- `woocommerce_checkout_create_order` action

### HPOS (High-Performance Order Storage)

- WooCommerce 7.1+ uses custom tables by default
- `wc_get_orders()` uses new query engine
- Order props stored in `wp_wc_orders` table
- Use `wc_is_order_attribute` functions for meta queries

### REST API

- WooCommerce REST API v3 (prefixed `wc/v3/`)
- Authentication via REST API keys (Consumer Key/Secret)
- Use `woocommerce_rest_prepare_<object>` filter for responses
- Schema defined via `register_rest_field`

### Key Hooks

```php
// Product
add_filter('woocommerce_product_is_visible', 'callback', 10, 2);
add_filter('woocommerce_product_get_price', 'callback', 10, 2);
add_action('woocommerce_process_product_meta', 'callback', 10, 1);

// Cart
add_filter('woocommerce_add_cart_item', 'callback', 10, 2);
add_filter('woocommerce_cart_item_price', 'callback', 10, 3);
add_action('woocommerce_before_cart', 'callback');

// Checkout
add_action('woocommerce_checkout_create_order', 'callback', 10, 3);
add_filter('woocommerce_checkout_fields', 'callback', 10, 1);

// Orders
add_action('woocommerce_order_status_completed', 'callback', 10, 1);
add_filter('woocommerce_order_get_items', 'callback', 10, 3);
```

## Common Patterns

### Check if WooCommerce is Active

```php
if (!class_exists('WooCommerce')) {
    return;
}
```

### Get WooCommerce Objects

```php
$product = wc_get_product($product_id);
$order = wc_get_order($order_id);
$cart = WC()->cart;
```

### Add Settings Tab

```php
add_filter('woocommerce_settings_tabs_array', 'add_settings_tab');
add_action('woocommerce_settings_tabs_mytab', 'settings_tab_content');
add_action('woocommerce_update_options_mytab', 'update_settings');
```

## Verification

- Plugin activates with WooCommerce active and no fatals
- Cart calculations work correctly (including with other shipping plugins)
- Checkout flow completes without errors
- HPOS table queries return correct data

## See also

- wp-woocommerce-shipping (for shipping method development)
- wp-rest-api (for WooCommerce REST API integration)
