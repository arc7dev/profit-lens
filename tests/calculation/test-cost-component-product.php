<?php
/**
 * Cases 12-16: cost reversal on partial refunds, and missing-cost
 * products never being treated as free.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Component_Product extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Cost_Component_Product */
	private $component;

	public function setUp(): void {
		parent::setUp();
		$this->component = new ProfitLens_Cost_Component_Product( new ProfitLens_Cost_Source_Cogs() );
	}

	/**
	 * Case 12: 2 of 5 units refunded — cost is charged for the 3 kept,
	 * not all 5.
	 */
	public function test_partial_quantity_refund_reduces_cost_proportionally() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 5, 'total' => 100.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 40.0, 2 );
		$order = wc_get_order( $order->get_id() );

		$this->assertEquals( 30.0, $this->component->calculate( $order ), 'Cost should reflect 3 kept units (10 x 3), not the original 5.' );
	}

	/**
	 * Case 13: the entire line refunded — its cost contribution drops to
	 * zero, not just to some partial amount.
	 */
	public function test_full_line_refund_zeroes_that_lines_cost() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 3, 'total' => 60.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 60.0, 3 );
		$order = wc_get_order( $order->get_id() );

		$this->assertEquals( 0.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 14: defensive clamp — refunded quantity can't push net
	 * quantity, and therefore cost, negative. wc_create_refund() itself
	 * refuses to over-refund a line through the normal API, so this
	 * forces the inconsistent state directly (a botched migration or a
	 * bug elsewhere are exactly the scenarios this clamp exists for).
	 */
	public function test_over_refunded_quantity_clamps_to_zero_not_negative() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 2, 'total' => 40.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$refund = $this->refund_item( $order, $item_id, 40.0, 2 );
		$order  = wc_get_order( $order->get_id() );

		// Push the refund's recorded quantity past what was ever ordered.
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			$refund_item->set_quantity( -5 );
			$refund_item->save();
		}

		$order = wc_get_order( $order->get_id() );

		foreach ( $this->component->resolve_line_items( $order ) as $line ) {
			$this->assertGreaterThanOrEqual( 0.0, $line['net_qty'] );
			$this->assertGreaterThanOrEqual( 0.0, $line['line_cost'] );
		}

		$this->assertEquals( 0.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 15: a product with no cost defined contributes nothing to the
	 * cost total — it's excluded, not silently treated as free ($0),
	 * which would inflate profit.
	 */
	public function test_product_without_cost_is_excluded_not_zeroed() {
		$product = $this->create_product( null, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 4, 'total' => 80.0 ) ) );

		$lines = $this->component->resolve_line_items( $order );
		$line  = reset( $lines );

		$this->assertNull( $line['unit_cost'], 'Unit cost must be null, not 0, when nothing was configured.' );
		$this->assertEquals( 0.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 16, as originally designed, doesn't hold — see the equivalent
	 * (and fuller explanation of why) in
	 * Test_ProfitLens_Cost_Source_Cogs::test_woocommerce_collapses_explicit_zero_cost_to_null().
	 * WooCommerce itself never lets get_cogs_value() return 0.0, through
	 * any path, so a product line here behaves exactly like case 15
	 * (no cost known) whether or not $0 was ever "set". Confirming that,
	 * instead of asserting a state WooCommerce doesn't allow to exist.
	 */
	public function test_product_with_attempted_zero_cost_behaves_like_no_cost() {
		$product = $this->create_product( 0.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 4, 'total' => 80.0 ) ) );

		$lines = $this->component->resolve_line_items( $order );
		$line  = reset( $lines );

		$this->assertNull( $line['unit_cost'] );
		$this->assertEquals( 0.0, $line['line_cost'] );
	}

	/**
	 * Case 27 (coupon line items): line_revenue already reflects the
	 * item's own `total`, which WooCommerce keeps net of any coupon —
	 * nothing here re-subtracts a discount.
	 */
	public function test_line_revenue_uses_coupon_adjusted_total() {
		$product = $this->create_product( 10.0, 20.0 );
		// Simulates a coupon having already reduced this line from $40 to $30.
		$order = $this->create_order(
			array( array( 'product' => $product, 'qty' => 2, 'total' => 30.0 ) ),
			'completed',
			array( 'discount_total' => 10.0 )
		);

		$lines = $this->component->resolve_line_items( $order );
		$line  = reset( $lines );

		$this->assertEquals( 30.0, $line['line_revenue'] );
	}

	/**
	 * net_revenue backs the per-product breakdown (and therefore the
	 * "worst product" insight) — it must reflect refunds the same way
	 * cost does, or a refunded product would show inflated profit
	 * (revenue untouched while cost went down).
	 */
	public function test_net_revenue_reflects_refunded_amount() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 5, 'total' => 100.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 40.0, 2 );
		$order = wc_get_order( $order->get_id() );

		$lines = $this->component->resolve_line_items( $order );
		$line  = reset( $lines );

		$this->assertEquals( 60.0, $line['net_revenue'] );
		$this->assertEquals( 100.0, $line['line_revenue'], 'Gross line_revenue stays the original amount — only net_revenue is refund-adjusted.' );
	}

	public function test_key_label_and_is_estimated() {
		$this->assertSame( 'product_cost', $this->component->get_key() );
		$this->assertFalse( $this->component->is_estimated() );
		$this->assertNull( $this->component->get_note() );
	}
}
