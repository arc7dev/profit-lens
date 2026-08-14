<?php
/**
 * Bootstrap de PHPUnit. Sigue el patrón estándar del test suite de
 * WordPress (WP_TESTS_DIR) más el autoload de Composer para las clases del
 * propio plugin (classmap — mismo motivo que en profit-lens.php: los
 * archivos de includes/ no siguen naming PSR-4).
 *
 * @package ProfitLens
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "No se encontró el test suite de WordPress en \"{$_tests_dir}\".\n";
	echo "Instalarlo con bin/install-wp-tests.sh (ver herramienta wp-cli/wp-cli o wp-phpunit/wp-phpunit).\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Carga el plugin bajo prueba (y WooCommerce, del que depende) antes de que
 * el test suite arranque WordPress.
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
