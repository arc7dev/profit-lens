<?php
/**
 * Tests for ProfitLens_Pro_Links::build_url()/build_all() — the one place
 * this plugin builds a link into arc7.dev's per-source Pro click
 * attribution routes. See that class's own docblock for the URL shape
 * and why an unrecognized source collapses to 'other' instead of being
 * passed through as-is.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

class Test_ProfitLens_Pro_Links extends WP_UnitTestCase {

	/**
	 * @dataProvider provide_valid_sources
	 * @param string $source
	 */
	public function test_build_url_for_each_valid_source( $source ) {
		$url = ProfitLens_Pro_Links::build_url( $source );

		$this->assertStringStartsWith( "https://arc7.dev/profit-lens/pro/from/{$source}/?", $url );
		$this->assertStringContainsString( 'utm_source=plugin', $url );
		$this->assertStringContainsString( "utm_medium={$source}", $url );
		$this->assertStringContainsString( 'utm_campaign=free-' . PROFITLENS_VERSION, $url );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function provide_valid_sources() {
		return array(
			'export-csv'    => array( 'export-csv' ),
			'product-modal' => array( 'product-modal' ),
			'ad-spend'      => array( 'ad-spend' ),
			'settings'      => array( 'settings' ),
			'dashboard'     => array( 'dashboard' ),
			'other'         => array( 'other' ),
		);
	}

	/**
	 * Anything outside the whitelist — a typo, a stale value from an old
	 * plugin version, or an attempted injection — collapses to 'other'
	 * rather than being passed through into the URL unescaped.
	 */
	public function test_unrecognized_source_collapses_to_other() {
		$url = ProfitLens_Pro_Links::build_url( 'not-a-real-source' );

		$this->assertStringStartsWith( 'https://arc7.dev/profit-lens/pro/from/other/?', $url );
		$this->assertStringContainsString( 'utm_medium=other', $url );
	}

	public function test_build_all_returns_one_url_per_source_keyed_by_source() {
		$urls = ProfitLens_Pro_Links::build_all();

		$this->assertSame( ProfitLens_Pro_Links::SOURCES, array_keys( $urls ) );

		foreach ( ProfitLens_Pro_Links::SOURCES as $source ) {
			$this->assertSame( ProfitLens_Pro_Links::build_url( $source ), $urls[ $source ] );
		}
	}
}
