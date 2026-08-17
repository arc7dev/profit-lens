<?php
/**
 * Cases 15-16 and 19-24: cost null-detection and variation cost
 * precedence — all verified against a live WooCommerce install before
 * being written here (see the session's HPOS/Action Scheduler work in
 * CLAUDE.md for the verification standard this project holds to).
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Source_Cogs extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Cost_Source_Cogs */
	private $source;

	public function setUp(): void {
		parent::setUp();
		$this->source = new ProfitLens_Cost_Source_Cogs();
	}

	/**
	 * Case 19: a simple product resolves its own cost directly.
	 */
	public function test_simple_product_with_cost() {
		$product = $this->create_product( 12.5, 30.0 );

		$this->assertSame( 12.5, $this->source->get_product_cost( $product ) );
	}

	/**
	 * Case 15.
	 */
	public function test_simple_product_without_cost_is_null() {
		$product = $this->create_product( null, 30.0 );

		$this->assertNull( $this->source->get_product_cost( $product ) );
	}

	/**
	 * Case 16, as originally designed, doesn't hold: WooCommerce itself
	 * makes an explicit $0 cost indistinguishable from "no cost set" —
	 * confirmed against a live install, including bypassing set_cogs_value()
	 * entirely (writing '_cogs_total_value' via raw SQL and loading a
	 * product object that had never touched this row before, to rule out
	 * any caching explanation). WC_Product_Data_Store_CPT::read_product_data()
	 * loads the stored value through set_props(), which calls the same
	 * set_cogs_value() → adjust_cogs_value_before_set() that collapses
	 * `0.0 === $value` to null on the write path — so it collapses on
	 * *read* too, no matter how the "0" got into the database. There is no
	 * WooCommerce-supported path that makes get_cogs_value() return 0.0.
	 *
	 * ProfitLens_Cost_Source_Cogs::has_defined_cost() still checks for this
	 * case (own value !== null) — that's correct, harmless, and forward-
	 * compatible if WooCommerce ever changes this, but it will never
	 * actually branch that way through get_cogs_value() today. This test
	 * locks in the discovery instead of asserting a state that cannot be
	 * constructed.
	 */
	public function test_woocommerce_collapses_explicit_zero_cost_to_null() {
		$product = $this->create_product( 0.0, 30.0 );

		$this->assertNull(
			$product->get_cogs_value(),
			'If this ever starts returning 0.0, ProfitLens_Cost_Source_Cogs can start trusting an explicit $0 cost — until then it cannot.'
		);
		$this->assertNull( $this->source->get_product_cost( $product ) );
	}

	/**
	 * Case 20: a variation with its own cost uses it, ignoring the
	 * parent entirely.
	 */
	public function test_variation_with_own_cost_ignores_parent() {
		$variation = $this->create_variation( 5.0, 12.0 );

		$this->assertSame( 5.0, $this->source->get_product_cost( $variation ) );
	}

	/**
	 * Case 21: no cost on the variation itself — falls back to the
	 * parent's cost, via WooCommerce's own resolution
	 * (get_cogs_total_value()), not a reimplementation of it.
	 */
	public function test_variation_without_own_cost_falls_back_to_parent() {
		$variation = $this->create_variation( null, 12.0 );

		$this->assertSame( 12.0, $this->source->get_product_cost( $variation ) );
	}

	/**
	 * Case 22: neither the variation nor the parent has a cost — null,
	 * never 0.
	 */
	public function test_variation_and_parent_both_without_cost_is_null() {
		$variation = $this->create_variation( null, null );

		$this->assertNull( $this->source->get_product_cost( $variation ) );
	}

	/**
	 * Case 23: additive mode sums the variation's own cost on top of the
	 * parent's, per WooCommerce's own semantics
	 * (WC_Product_Variation::get_cogs_total_value_core()).
	 */
	public function test_variation_additive_mode_sums_with_parent() {
		$variation = $this->create_variation( 5.0, 12.0, true );

		$this->assertSame( 17.0, $this->source->get_product_cost( $variation ) );
	}

	/**
	 * Case 24: additive mode, no cost of the variation's own — resolves
	 * to just the parent's value (0 + parent), and still counts as
	 * "known", via the parent fallback in has_defined_cost().
	 */
	public function test_variation_additive_mode_without_own_cost_uses_parent_only() {
		$variation = $this->create_variation( null, 12.0, true );

		$this->assertSame( 12.0, $this->source->get_product_cost( $variation ) );
	}

	public function test_key_and_label() {
		$this->assertSame( 'woocommerce_cogs', $this->source->get_key() );
		$this->assertFalse( $this->source->is_estimated() );
	}
}
