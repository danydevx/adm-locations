---
name: wp-woocommerce-shipping
description: "Use when developing WooCommerce shipping methods or extensions: shipping zones, shipping methods, shipping rates, shipping calculator, and WooCommerce Shipping Classes API."
compatibility: "Targets WooCommerce 8.0+ with WordPress 6.0+. HPOS compatible."
---

# WooCommerce Shipping Development

## When to use

Use this skill when:

- creating custom WooCommerce shipping methods
- modifying shipping rates or zone logic
- adding shipping calculator functionality
- integrating state/municipality/postal code shipping rules
- working with WooCommerce Shipping Classes

## WooCommerce Shipping Architecture

### Core Components

1. **Shipping Zones** (`WC_Shipping_Zone`)
   - Geographic regions with assigned shipping methods
   - Stores zone locations: countries, states, postcodes
   - Zone ID 0 = "Worldwide" or "Locations not covered"

2. **Shipping Methods** (`WC_Shipping_Method`)
   - Individual methods within a zone (e.g., Flat Rate, Free Shipping)
   - Each method calculates rates via `calculate_shipping()`
   - Methods registered via `woocommerce_shipping_methods` filter

3. **Shipping Rates** (`WC_Shipping_Rate`)
   - Calculated cost for a method
   - Contains: label, cost, taxes, option
   - Multiple rates can be returned per method

4. **Shipping Packages**
   - Cart items grouped for shipping calculation
   - Each package has destination address

### Class Hierarchy

```
WC_Shipping_Method (abstract base)
├── WC_Shipping_Flat_Rate
├── WC_Shipping_Free_Shipping
├── WC_Shipping_Local_Pickup
└── [Custom Methods extend this]
```

## Creating a Shipping Method

### Basic Structure

```php
class WC_My_Shipping_Method extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'my_shipping';
        $this->method_title = 'My Shipping';
        $this->method_description = 'Description';
        $this->init();
    }

    public function init() {
        $this->form_fields = [...];
        $this->instance_form_fields = [...];
        $this->title = $this->get_option('title');

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function calculate_shipping($package = []) {
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->get_option('cost'),
            'calc_tax' => 'per_item',
        ];
        $this->add_rate($rate);
    }
}
```

### Register the Method

```php
add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['my_shipping'] = 'WC_My_Shipping_Method';
    return $methods;
});
```

## Shipping Zones and Locations

### Zone Data Structure

```php
// Get all zones
$zones = WC_Shipping_Zones::get_zones();

// Get zone by ID
$zone = WC_Shipping_Zones::get_zone($zone_id);

// Zone locations are stored as:
$locations = [
    (object)['type' => 'country', 'code' => 'MX'],
    (object)['type' => 'state', 'code' => 'MX-TAM'],
    (object)['type' => 'postcode', 'code' => '12345'],
];
```

### Location Matching

WooCommerce matches destinations using:
1. Country first
2. State/province second
3. Postcode last (supports wildcards like `123*`)

### For State → Municipality → Postal Code

Since WooCommerce zones don't natively support municipality-level shipping, implement a custom matching layer:

```php
// In your shipping method's calculate_shipping()
public function calculate_shipping($package = []) {
    $destination = $package['destination'];

    $state = $destination['state'];
    $city = $destination['city']; // May be empty depending on checkout
    $postcode = $destination['postcode'];

    // Query your custom shipping coverage table
    $coverage = $this->check_coverage($state, $city, $postcode);

    if (!$coverage->is_covered()) {
        return; // Don't add rate - location not covered
    }

    $rate = [
        'id' => $this->id . ':' . $coverage->method_id,
        'label' => $coverage->label,
        'cost' => $coverage->cost,
    ];
    $this->add_rate($rate);
}
```

## Shipping Calculator

### Hook into Calculator

```php
// Add custom fields to shipping calculator
add_filter('woocommerce_shipping_calculator_enable_state', '__return_true');
add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_true');

// Custom city handling
add_filter('woocommerce_shipping_calculator_custom_city_value', function($value, $key, $package) {
    return $_POST['calc_shipping_city'] ?? '';
}, 10, 3);
```

## Shipping Classes

### Working with Shipping Classes

```php
// Get shipping classes
$classes = WC()->shipping()->get_shipping_classes();

// Get class for a product
$product = wc_get_product($product_id);
$class_id = $product->get_shipping_class_id();

// Calculate costs per class
$class_costs = get_option('woocommerce_shipping_classes');
```

## Key Hooks

```php
// Shipping method hooks
add_filter('woocommerce_shipping_methods', 'register_my_method');
add_action('woocommerce_before_shipping_calculator', 'before_calculator');
add_action('woocommerce_after_shipping_calculator', 'after_calculator');

// Rate hooks
add_filter('woocommerce_package_rates', 'modify_rates', 10, 2);
add_filter('woocommerce_shipping_rate_label', 'custom_rate_label', 10, 2);

// Shipping zone hooks
add_action('woocommerce_after_shipping_zone', 'after_zone');
add_action('woocommerce_init', 'init_shipping_zones');
```

## Admin Settings

### Add Settings to Shipping Method

```php
public function init_form_fields() {
    $this->instance_form_fields = [
        'title' => [
            'title' => __('Title', 'woocommerce'),
            'type' => 'text',
            'description' => __('Title shown at checkout.', 'woocommerce'),
            'default' => __('My Shipping', 'woocommerce'),
        ],
        'cost' => [
            'title' => __('Cost', 'woocommerce'),
            'type' => 'number',
            'description' => __('Shipping cost.', 'woocommerce'),
        ],
    ];
}
```

## Debugging

```php
// Enable debug mode
add_filter('woocommerce_shipping_debug_mode', '__return_true');

// Log shipping rates
add_filter('woocommerce_package_rates', function($rates, $package) {
    error_log(print_r(['rates' => $rates, 'package' => $package], true));
    return $rates;
}, 10, 2);
```

## Verification

- Shipping method appears in WooCommerce > Settings > Shipping
- Method correctly calculates rates for covered locations
- Method returns no rates for uncovered locations
- Settings save and display correctly
- Works with both legacy and HPOS order storage

## See also

- wp-woocommerce (general WooCommerce development)
- wp-rest-api (for exposing shipping rates via REST)
