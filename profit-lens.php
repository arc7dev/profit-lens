<?php
/**
 * Plugin Name:       Profit Lens — Profit Analytics for WooCommerce
 * Plugin URI:         https://arc7.dev/profit-lens
 * Description:        Shows the real profit of your WooCommerce store — not just
 *                      sales — by subtracting gateway fees, shipping, and refunds
 *                      from your product cost. 100% self-hosted, your data never
 *                      leaves your site.
 * Version:            0.1.0
 * Requires at least:  6.4
 * Requires PHP:       7.4
 * Author:             Arc7
 * Author URI:         https://arc7.dev
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        profit-lens
 * Domain Path:        /languages
 * WC requires at least: 8.0
 * WC tested up to:      10.3
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

// ── WooCommerce feature compatibility ───────────────────────────────────────
// custom_order_tables (HPOS): true. The calculation engine (includes/
// calculation/) is all documented stubs at this stage — nothing in this
// plugin queries wp_posts/wp_postmeta for order data or assumes orders live
// there. The one $wpdb query in uninstall.php targets wp_options, not
// orders. When the engine gets real logic, it must keep going exclusively
// through the WooCommerce CRUD API (wc_get_orders(), $order->get_*()) —
// this declaration is a promise, not a formality, and has to stay true.
//
// cart_checkout_blocks: true. Profit Lens is read-only analytics; it has no
// cart or checkout code at all (verified: the plugin doesn't register any
// cart/checkout hooks, shortcodes, or blocks).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

// ── Constants ────────────────────────────────────────────────────────────────

define( 'PROFITLENS_VERSION', '0.1.0' );
define( 'PROFITLENS_PLUGIN_FILE', __FILE__ );
define( 'PROFITLENS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROFITLENS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PROFITLENS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PROFITLENS_TEXT_DOMAIN', 'profit-lens' );

// ── Autoload ─────────────────────────────────────────────────────────────────
// Own autoloader (no Composer at runtime): a WordPress.org plugin's production
// files can't depend on the user having run `composer install`. Composer is
// only used for dev/PHPUnit (see composer.json).

require_once PROFITLENS_PLUGIN_DIR . 'includes/class-plugin.php';

// ── Activation / deactivation ─────────────────────────────────────────────────

register_activation_hook( __FILE__, array( 'ProfitLens_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ProfitLens_Plugin', 'deactivate' ) );

// ── Bootstrap ────────────────────────────────────────────────────────────────
// On 'plugins_loaded' because the plugin depends on WooCommerce classes
// (WC_Order, WC_Product) that don't exist yet before that hook.

add_action( 'plugins_loaded', array( 'ProfitLens_Plugin', 'instance' ) );
