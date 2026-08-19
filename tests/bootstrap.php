<?php
/**
 * PHPUnit bootstrap. Follows the standard WordPress test suite pattern
 * (WP_TESTS_DIR) plus Composer's autoload for the plugin's own classes
 * (classmap — same reason as in profit-lens.php: files under includes/
 * don't follow PSR-4 naming).
 *
 * @package ProfitLens
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// tests/wp-tests-config.php (gitignored — this environment's DB creds
// only). WP_TESTS_CONFIG_FILE_PATH, when defined, is used as the exact
// file path by wp-phpunit's own bootstrap — not a directory it appends
// a filename to (that only happens in its own fallback branch).
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// Ships as a dev dependency (composer.json) so `composer install` is
	// enough — no separate SVN/wp-cli scaffold step needed.
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test suite at \"{$_tests_dir}\".\n";
	echo "Run `composer install` (brings in wp-phpunit/wp-phpunit), or set WP_TESTS_DIR yourself.\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin under test (and WooCommerce, which it depends on)
 * before the test suite boots WordPress.
 */
function profitlens_tests_manually_load_plugin() {
	$woocommerce = WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';

	if ( file_exists( $woocommerce ) ) {
		require $woocommerce;
	}

	// Cost of Goods Sold is off by default (WC 10.3+) — the fixtures in
	// ProfitLens_Calculation_Test_Case call set_cogs_value()/
	// get_cogs_value() directly, which are no-ops with a _doing_it_wrong
	// notice while the feature is disabled (same reason the seeder does
	// this for the real site — see class-profitlens-seeder-command.php).
	// Has to happen before WooCommerce's own init reads it.
	update_option( 'woocommerce_feature_cost_of_goods_sold_enabled', 'yes' );

	require dirname( __DIR__ ) . '/profit-lens.php';
}
tests_add_filter( 'muplugins_loaded', 'profitlens_tests_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
