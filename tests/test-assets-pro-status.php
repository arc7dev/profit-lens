<?php
/**
 * Tests for ProfitLens_Assets::build_pro_status() — the three states
 * ProductTable.jsx's Export CSV button branches on (Pro absent, Pro
 * present without a valid license, Pro present and licensed). Tested as
 * a pure function of two booleans, not against the real
 * function_exists( 'profitlens_pro_fs' ) / can_use_premium_code__premium_only()
 * checks in ProfitLens_Assets::enqueue() — those read real global state
 * (whether the Pro plugin file loaded, and its live Freemius connection)
 * that a single PHPUnit process can't toggle between "installed" and
 * "not installed" mid-run. See build_pro_status()'s own docblock.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

class Test_ProfitLens_Assets_Pro_Status extends WP_UnitTestCase {

	public function test_pro_not_installed() {
		$status = ProfitLens_Assets::build_pro_status( false, false );

		$this->assertFalse( $status['installed'] );
		$this->assertFalse( $status['licensed'] );
	}

	public function test_pro_installed_without_valid_license() {
		$status = ProfitLens_Assets::build_pro_status( true, false );

		$this->assertTrue( $status['installed'] );
		$this->assertFalse( $status['licensed'] );
	}

	public function test_pro_installed_and_licensed() {
		$status = ProfitLens_Assets::build_pro_status( true, true );

		$this->assertTrue( $status['installed'] );
		$this->assertTrue( $status['licensed'] );
	}

	/**
	 * Defensive floor: a caller passing licensed=true alongside
	 * installed=false (nonsensical — the real call site only evaluates
	 * $pro_licensed when $pro_installed is already true) must still get
	 * licensed=false back, not a contradictory status object.
	 */
	public function test_licensed_is_forced_false_when_not_installed() {
		$status = ProfitLens_Assets::build_pro_status( false, true );

		$this->assertFalse( $status['installed'] );
		$this->assertFalse( $status['licensed'] );
	}

	public function test_dashboard_url_points_at_pro_admin_page() {
		$status = ProfitLens_Assets::build_pro_status( true, false );

		$this->assertSame(
			admin_url( 'admin.php?page=profit-lens-pro' ),
			$status['dashboardUrl']
		);
	}
}
