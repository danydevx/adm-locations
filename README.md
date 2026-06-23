# ADM Bike Woo Locations

**Contributors:** admbike
**Tags:** woocommerce, shipping, locations, states, municipalities, postcodes
**Requires at least:** 6.0
**Tested up to:** 6.4
**Requires PHP:** 8.2
**License:** GPL v2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Advanced WooCommerce shipping coverage management by State, Municipality and Postal Code.

## Description

ADM Bike Woo Locations allows you to define shipping coverage areas using a hierarchical structure: **State → Municipality → Postal Code**.

Instead of relying solely on WooCommerce zones and postcodes, this plugin provides:

* Guided checkout selectors (cascading dropdowns: State → Municipality → Postal Code)
* Shipping rules with different types: Free, Paid, Blocked
* Rule matching by: exact postcode, postcode range, municipality, or state
* Conflict detection when rules overlap
* A coverage information banner for cart and checkout pages

## Installation

### Requirements

* WordPress 6.0+
* WooCommerce 8.0+
* PHP 8.2+

### Steps

1. Upload the plugin folder to `/wp-content/plugins/admbike-woo-locations/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Go to **ADM Bike Locations** in the admin menu
4. Add your states, municipalities, and postal codes
5. Create shipping rules to define coverage, costs, and blocked areas
6. Enable the shipping method in **WooCommerce → Settings → Shipping**

### Activating the Shipping Method

1. Go to **WooCommerce → Settings → Shipping**
2. Click **Add shipping method**
3. Select **ADM Bike Locations** from the list
4. Click **Add shipping method** to confirm
5. Configure the method title and tax status
6. Drag the method to the desired zone or create a new zone

## Configuration

### Adding States

1. Go to **ADM Bike Locations → States**
2. Click **Add New**
3. Enter the state code (e.g., `JAL`) and name (e.g., `Jalisco`)
4. Enable the state and click **Save**

### Adding Municipalities

1. Go to **ADM Bike Locations → Municipalities**
2. Click **Add New**
3. Select the parent state
4. Enter the municipality name
5. Enable and **Save**

### Adding Postal Codes

1. Go to **ADM Bike Locations → Postal Codes**
2. Click **Add New**
3. Select the parent state and municipality
4. Enter the 5-digit postcode
5. Enable and **Save**

### Creating Shipping Rules

Rules determine shipping cost and availability. Rules are evaluated in priority order (lower number = higher priority).

1. Go to **ADM Bike Locations → Shipping Rules**
2. Click **Add New**
3. Select a **Match Type**:
   * **State**: Covers an entire state
   * **Municipality**: Covers a specific municipality within a state
   * **Postcode**: Covers an exact postcode
   * **Postcode Range**: Covers a range of postcodes (from/to)
4. Select the location (state, municipality, or postcode based on match type)
5. Select **Rule Type**:
   * **Free Shipping**: No shipping cost
   * **Paid Shipping**: Specify the cost and currency
   * **Blocked**: Prevents shipping to this location
6. Set a **Priority** (lower number wins)
7. Add optional notes
8. **Save**

### Rule Priority Example

If you have:
* State `Jalisco` → Free (priority 50)
* Municipality `Guadalajara` (in Jalisco) → Free (priority 10)

The municipality rule wins for Guadalajara postcodes because priority `10` is lower than `50`.

## Admin Usage

### Cascading Dropdowns in Checkout

When a customer reaches checkout:
1. They select their **State** from a dropdown
2. The **Municipality** dropdown populates with only municipalities in that state
3. The **Postal Code** dropdown populates with only postcodes in that municipality

This guides customers who don't know their exact postcode.

### Coverage Preview Tool

When editing a shipping rule, use the **Preview** section to test which rules match a specific location before saving.

### Conflict Detection

When saving a shipping rule, the system warns if another rule with higher priority covers the same area, so you can avoid unintended overlaps.

## Troubleshooting

### Shipping method not appearing at checkout

1. Verify the shipping method is enabled in **WooCommerce → Settings → Shipping**
2. Ensure at least one active shipping zone exists with the `admbike_locations` method added
3. Check that locations (states, municipalities, postcodes) exist in the database
4. Check that at least one shipping rule is active and matches the customer's location

### "No coverage" message shown incorrectly

1. Verify the postcode exists under **ADM Bike Locations → Postal Codes**
2. Check that the municipality and state for that postcode are both enabled
3. Ensure a shipping rule exists that matches the postcode, municipality, or state (in that priority order)
4. Check the rule is enabled and not set to "Blocked"

### Dropdowns not populating at checkout

1. Verify WooCommerce is installed and active
2. Check browser console for JavaScript errors
3. Ensure the inline location data is being printed: look for `<script id="admbike-location-data">` in the page source
4. Try disabling other plugins that may conflict with checkout JS

### Debug logs

Enable debug logging in `wp-config.php`:

```php
define( 'ADMBIKE_WOO_LOCATIONS_DEBUG', true );
```

Logs are written to `wp-content/uploads/admbike-woo-locations.log`.

## Database Tables

The plugin creates 4 custom tables:

* `admbike_states` — States with code, name, and status
* `admbike_municipalities` — Municipalities linked to states
* `admbike_postcodes` — Postcodes linked to municipalities
* `admbike_shipping_rules` — Shipping coverage rules

## REST API

The plugin exposes a REST API under `/wp-json/admbike-woo-locations/v1/`:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/states` | GET | List all active states |
| `/municipalities?state_id={id}` | GET | List municipalities by state |
| `/postcodes?municipality_id={id}` | GET | List postcodes by municipality |
| `/coverage?postcode={code}` | GET | Check coverage for a postcode |

## Hooks & Filters

### Actions

* `admbike_woo_locations_activated` — Runs after plugin activation
* `admbike_shipping_rule_applied` — Runs when a shipping rule is applied at checkout

### Filters

* `admbike_woo_locations_checkout_fields` — Modify checkout field labels
* `admbike_woo_locations_shipping_rate` — Modify the shipping rate before it's added

## Changelog

### 0.1.0
* Initial release
* State, Municipality, and Postal Code management
* Shipping Rules CRUD with priority and conflict detection
* Guided checkout selectors with cascading dropdowns
* Coverage information banner
* REST API for coverage checking
* Debug logging system
