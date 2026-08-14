<?php
/**
 * Plugin Name:       Profit Lens — Profit Analytics for WooCommerce
 * Plugin URI:         https://arc7.dev/profit-lens
 * Description:        Muestra la ganancia real de tu tienda WooCommerce — no solo las
 *                      ventas — restando comisiones de pasarela, envío y reembolsos
 *                      del costo de tus productos. 100% self-hosted, tus datos no
 *                      salen de tu sitio.
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

// ── Constantes ──────────────────────────────────────────────────────────────

define( 'PROFITLENS_VERSION', '0.1.0' );
define( 'PROFITLENS_PLUGIN_FILE', __FILE__ );
define( 'PROFITLENS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROFITLENS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PROFITLENS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PROFITLENS_TEXT_DOMAIN', 'profit-lens' );

// ── Autoload ─────────────────────────────────────────────────────────────────
// Autoloader propio (no Composer en runtime): los archivos de producción de un
// plugin de WordPress.org no pueden depender de que el usuario corra
// `composer install`. Composer solo se usa para dev/PHPUnit (ver composer.json).

require_once PROFITLENS_PLUGIN_DIR . 'includes/class-plugin.php';

// ── Activación / desactivación ─────────────────────────────────────────────

register_activation_hook( __FILE__, array( 'ProfitLens_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ProfitLens_Plugin', 'deactivate' ) );

// ── Arranque ─────────────────────────────────────────────────────────────────
// En 'plugins_loaded' porque el plugin depende de clases de WooCommerce
// (WC_Order, WC_Product) que todavía no existen antes de ese hook.

add_action( 'plugins_loaded', array( 'ProfitLens_Plugin', 'instance' ) );
