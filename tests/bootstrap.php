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

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test suite at \"{$_tests_dir}\".\n";
	echo "Install it with bin/install-wp-tests.sh (see wp-cli/wp-cli or wp-phpunit/wp-phpunit).\n";
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

	require dirname( __DIR__ ) . '/profit-lens.php';
}
tests_add_filter( 'muplugins_loaded', 'profitlens_tests_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
