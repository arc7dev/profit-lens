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
