<?php
/**
 * Tests for ProfitLens_REST_Controller::get_summary()'s `setup_status`
 * field — distinguishes "COGS feature off" from "COGS on, nothing priced
 * yet" for EmptyState.jsx, both of which collapse to the same
 * `cost_coverage.products_with_cost === 0` signal the "empty" status
 * itself is keyed on. Calls the controller's callback directly (not a
 * full rest_do_request() dispatch), same style test-settings-users-endpoint.php
 * (profit-lens-pro) uses for its controller.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

class Test_ProfitLens_REST_Controller_Setup_Status extends WP_UnitTestCase {

	const COGS_OPTION = 'woocommerce_feature_cost_of_goods_sold_enabled';

	/** @var ProfitLens_REST_Controller */
	private $controller;

	public function set_up() {
		parent::set_up();
		$this->controller = new ProfitLens_REST_Controller();
	}

	public function tear_down() {
		// Explicit safety net — WP_UnitTestCase's own per-test transaction
		// rollback already reverts option changes, but this makes the
		// invariant (every test leaves COGS enabled) visible without
		// having to trust that mechanism silently.
		update_option( self::COGS_OPTION, 'yes' );
		parent::tear_down();
	}

	/**
	 * @param float|null $cost
	 * @return WC_Product
	 */
	private function create_product( $cost ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( '20.0' );
		$product->set_status( 'publish' );

		if ( null !== $cost ) {
			$product->set_cogs_value( $cost );
		}

		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * @return WP_REST_Response
	 */
	private function get_summary_response() {
		$request = new WP_REST_Request( 'GET', '/' . ProfitLens_REST_Controller::REST_NAMESPACE . ProfitLens_REST_Controller::ROUTE_SUMMARY );
		$request->set_param( 'range', '30d' );

		return $this->controller->get_summary( $request );
	}

	/**
	 * Case 1: COGS feature itself is off — confirmed via the real
	 * WooCommerce option is_available() falls back to reading
	 * (woocommerce_feature_cost_of_goods_sold_enabled), not a mock.
	 *
	 * Uses a product with NO cost (not create_product(10.0)): WooCommerce's
	 * own WC_Product::set_cogs_value() refuses to write anything and
	 * triggers a "doing it wrong" notice when the feature is disabled
	 * (confirmed live — this test originally tried that and failed on the
	 * notice, not on the assertion). The "feature off AND cost data
	 * already exists from before it was disabled" scenario is covered
	 * separately below, in the order that's actually reachable in
	 * WooCommerce (price while enabled, then disable).
	 */
	public function test_setup_status_is_cogs_disabled_when_feature_is_off() {
		update_option( self::COGS_OPTION, 'no' );
		$this->create_product( null );

		$data = $this->get_summary_response()->get_data();

		$this->assertSame( 'empty', $data['status'] );
		$this->assertSame( 'cogs_disabled', $data['setup_status'] );
	}

	/**
	 * Case 2: feature on, catalog has products, none priced.
	 */
	public function test_setup_status_is_no_costs_when_feature_on_and_nothing_priced() {
		update_option( self::COGS_OPTION, 'yes' );
		$this->create_product( null );

		$data = $this->get_summary_response()->get_data();

		$this->assertSame( 'empty', $data['status'] );
		$this->assertSame( 'no_costs', $data['setup_status'] );
	}

	/**
	 * Case 3: feature on, at least one product priced — real "ready"
	 * path, setup_status isn't part of this contract at all (only the
	 * empty-response shape carries it).
	 */
	public function test_setup_status_is_absent_when_ready() {
		update_option( self::COGS_OPTION, 'yes' );
		$this->create_product( 10.0 );

		$data = $this->get_summary_response()->get_data();

		$this->assertSame( 'ready', $data['status'] );
		$this->assertArrayNotHasKey( 'setup_status', $data );
	}

	/**
	 * The case this ticket's own review called out explicitly: COGS
	 * disabled must win even when real cost data already exists in
	 * postmeta (e.g. a merchant who had it enabled, priced some
	 * products, then turned the feature back off) — is_available() is
	 * checked BEFORE falling back to products_with_cost, not the other
	 * way around, precisely so a merchant in this state is pointed at
	 * "re-enable the feature" rather than "add costs" (which would be
	 * misleading — the costs already exist, WooCommerce just isn't
	 * reading them with the feature off).
	 */
	public function test_cogs_disabled_wins_even_with_existing_cost_data_in_postmeta() {
		// Price the product FIRST, while the feature is still on — matches
		// how a real merchant would have gotten cost data into postmeta in
		// the first place (set_cogs_value() while the feature is active).
		update_option( self::COGS_OPTION, 'yes' );
		$product = $this->create_product( 10.0 );

		update_option( self::COGS_OPTION, 'no' );

		// The underlying postmeta row is still there — WooCommerce's own
		// get_cogs_value() just refuses to read it with the feature off
		// (confirmed in get_catalog_coverage()'s own docblock), so this
		// assertion is the real-world precondition, not an assumption.
		$this->assertNotEmpty( get_post_meta( $product->get_id(), '_cogs_total_value', true ) );

		$data = $this->get_summary_response()->get_data();

		$this->assertSame( 'empty', $data['status'] );
		$this->assertSame( 'cogs_disabled', $data['setup_status'] );
	}
}
