<?php
/**
 * Integration tests for ProfitLens_Profit_Engine: order-status filtering,
 * the anti-double-count query guard, revenue/shipping netting, coverage,
 * aggregation, chart series, and the loss-making-product insight.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Profit_Engine extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Profit_Engine */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = ProfitLens_Profit_Engine::create_default();
	}

	/**
	 * @param string $days_ago
	 * @return array{0:DateTime,1:DateTime}
	 */
	private function range( $days_ago = 7 ) {
		return array( new DateTime( "-{$days_ago} days" ), new DateTime( 'tomorrow' ) );
	}

	// -----------------------------------------------------------------
	// Cases 1-6, 11: which orders count.
	// -----------------------------------------------------------------

	/**
	 * @dataProvider counted_status_provider
	 */
	public function test_status_counts_toward_revenue_correctly( $status, $should_count ) {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ), $status );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		if ( $should_count ) {
			$this->assertEquals( 20.0, $summary['kpis']['revenue']['amount'], "Status '$status' should count toward revenue." );
		} else {
			$this->assertEquals( 0.0, $summary['kpis']['revenue']['amount'], "Status '$status' should NOT count toward revenue." );
		}
	}

	public function counted_status_provider() {
		return array(
			'completed (case 1)'  => array( 'completed', true ),
			'processing (case 2)' => array( 'processing', true ),
			'cancelled (case 3)'  => array( 'cancelled', false ),
			'failed (case 4)'     => array( 'failed', false ),
			'on-hold (case 5)'    => array( 'on-hold', false ),
		);
	}

	/**
	 * Case 6: an order fully refunded via status change is excluded
	 * entirely (status is "refunded", neither completed nor processing) —
	 * it's a full wash, contributing nothing either way.
	 */
	public function test_status_refunded_order_excluded_entirely() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$this->trigger_automatic_full_refund( $order );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 0.0, $summary['kpis']['revenue']['amount'] );
		$this->assertEquals( 0.0, $summary['kpis']['net_profit']['amount'] );
	}

	/**
	 * Case 11 — the most dangerous bug, guarded directly: a refund record
	 * must never be iterated by get_summary() as if it were its own
	 * order. If it were, its (negative) total would count as its own
	 * "sale" on top of the parent order's revenue AND the refund being
	 * subtracted again from the parent — double-subtracting the same
	 * money.
	 */
	public function test_refund_records_are_never_counted_as_their_own_orders() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 5, 'total' => 100.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 40.0, 2 );
		$order = wc_get_order( $order->get_id() );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		// Revenue is the ORIGINAL gross line total (100), not reduced —
		// the refund is netted once via the "refunds" cost component.
		// If the refund record were counted as its own order, revenue
		// would come out lower than 100 (100 - 40, or worse).
		$this->assertEquals( 100.0, $summary['kpis']['revenue']['amount'] );
		$this->assertEquals( 40.0, $this->find_cost_breakdown_amount( $summary, 'refunds' ) );

		// Exactly one order counted, not two (the order and its refund).
		$this->assertEquals( 1, $summary['kpis']['revenue']['orders_count'] );
	}

	// -----------------------------------------------------------------
	// Shipping/revenue netting regression (per-turn correction from the
	// design review: shipping collected must not be a pure loss).
	// -----------------------------------------------------------------

	public function test_shipping_collected_nets_to_zero_profit_impact() {
		$product = $this->create_product( 5.0, 20.0 );
		// No product cost, no gateway (bacs has the default 2.9%+0.30
		// applied, so isolate by asserting the shipping-specific delta
		// rather than absolute profit): a $10 shipping charge should
		// contribute zero net impact to profit — not -$10.
		$order_no_shipping = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed',
			array( 'payment_method' => '' ) // no gateway fee, isolates the shipping effect
		);
		$profit_without_shipping = $this->engine->calculate_order_profit( $order_no_shipping )['profit'];

		$product2 = $this->create_product( 5.0, 20.0 );
		$order_with_shipping = $this->create_order(
			array( array( 'product' => $product2, 'qty' => 1, 'total' => 20.0 ) ),
			'completed',
			array( 'payment_method' => '', 'shipping_total' => 10.0 )
		);
		$profit_with_shipping = $this->engine->calculate_order_profit( $order_with_shipping )['profit'];

		$this->assertEquals(
			$profit_without_shipping,
			$profit_with_shipping,
			'Collecting $10 for shipping must not change profit — revenue (+10) and the shipping cost line (-10) should cancel out exactly.'
		);
	}

	// -----------------------------------------------------------------
	// Coverage (cases 17-18).
	// -----------------------------------------------------------------

	public function test_revenue_coverage_excludes_no_cost_product_revenue() {
		$with_cost    = $this->create_product( 5.0, 20.0 );
		$without_cost = $this->create_product( null, 30.0 );

		$order = $this->create_order(
			array(
				array( 'product' => $with_cost, 'qty' => 1, 'total' => 20.0 ),
				array( 'product' => $without_cost, 'qty' => 1, 'total' => 30.0 ),
			)
		);

		list( $after, $before ) = $this->range();
		$coverage = $this->engine->get_cost_coverage( $after, $before );

		// 20 of the period's 50 in revenue is backed by a known cost.
		$this->assertEquals( 40.0, $coverage['revenue_covered_pct'] );
		$this->assertEquals( 30.0, $coverage['revenue_uncovered'] );
	}

	/**
	 * Per-product has_cost flag (feeds ProductTable's "No cost set" chip):
	 * true for a product whose cost is defined, false for one that isn't —
	 * checked on the same two products/order as the revenue-coverage test
	 * above, but reading products instead of cost_coverage.
	 */
	public function test_product_has_cost_reflects_whether_its_own_cost_is_known() {
		$with_cost    = $this->create_product( 5.0, 20.0 );
		$without_cost = $this->create_product( null, 30.0 );

		$this->create_order(
			array(
				array( 'product' => $with_cost, 'qty' => 1, 'total' => 20.0 ),
				array( 'product' => $without_cost, 'qty' => 1, 'total' => 30.0 ),
			)
		);

		list( $after, $before ) = $this->range();
		$products = $this->engine->get_summary( $after, $before )['products'];

		$by_id = array();
		foreach ( $products as $product ) {
			$by_id[ $product['id'] ] = $product;
		}

		$this->assertTrue( $by_id[ $with_cost->get_id() ]['has_cost'] );
		$this->assertFalse( $by_id[ $without_cost->get_id() ]['has_cost'] );
	}

	/**
	 * A product sold across two orders where only one line's cost was
	 * unknown (e.g. the cost was set partway through the period) must
	 * still flip has_cost to false for the whole row — a mix of real cost
	 * and an implicit $0 for the uncovered units is exactly the case the
	 * chip exists to flag, not just "cost never set at all".
	 */
	public function test_product_has_cost_false_when_only_some_of_its_lines_are_uncovered() {
		$product = $this->create_product( 5.0, 20.0 );
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		// Simulate the cost having been unset after the first sale: strip
		// it directly rather than via a second product, so both order
		// lines point at the exact same product_id.
		$product->set_cogs_value( null );
		$product->save();

		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		list( $after, $before ) = $this->range();
		$products = $this->engine->get_summary( $after, $before )['products'];

		$this->assertFalse( $products[0]['has_cost'] );
	}

	// -----------------------------------------------------------------
	// Case 25/26: zero-price product doesn't divide by zero.
	// -----------------------------------------------------------------

	public function test_zero_price_product_margin_does_not_error() {
		$product = $this->create_product( 0.0, 0.0 );
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 0.0 ) ) );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertIsFloat( $summary['products'][0]['margin_pct'] );
		$this->assertEquals( 0.0, $summary['products'][0]['margin_pct'] );
	}

	// -----------------------------------------------------------------
	// Aggregation / chart / insight (cases 33-36).
	// -----------------------------------------------------------------

	public function test_get_summary_aggregates_multiple_orders() {
		$p1 = $this->create_product( 5.0, 20.0 );
		$p2 = $this->create_product( 8.0, 30.0 );

		$this->create_order( array( array( 'product' => $p1, 'qty' => 2, 'total' => 40.0 ) ) );
		$this->create_order( array( array( 'product' => $p2, 'qty' => 1, 'total' => 30.0 ) ) );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 70.0, $summary['kpis']['revenue']['amount'] );
		$this->assertEquals( 2, $summary['kpis']['revenue']['orders_count'] );
	}

	/**
	 * get_net_profit() is a lighter-weight path to the exact same profit
	 * figure get_summary() computes (built for change_pct's prior-period
	 * comparison, which never needs the rest of the summary) — it must
	 * never drift from what get_summary() would have said for the same
	 * range, including with a refund in play (exercises the same
	 * cost-component math, not just plain revenue).
	 */
	public function test_get_net_profit_matches_get_summary() {
		$p1 = $this->create_product( 5.0, 20.0 );
		$p2 = $this->create_product( 8.0, 30.0 );

		$order1  = $this->create_order( array( array( 'product' => $p1, 'qty' => 2, 'total' => 40.0 ) ) );
		$item_id = array_key_first( $order1->get_items( 'line_item' ) );
		$this->refund_item( $order1, $item_id, 20.0, 1 );

		$this->create_order( array( array( 'product' => $p2, 'qty' => 1, 'total' => 30.0 ) ) );

		list( $after, $before ) = $this->range();

		$summary = $this->engine->get_summary( $after, $before );
		$net_profit = $this->engine->get_net_profit( $after, $before );

		$this->assertSame( $summary['kpis']['net_profit']['amount'], $net_profit );
	}

	public function test_chart_series_fills_gaps_with_zero() {
		$product = $this->create_product( 5.0, 20.0 );
		// Only today has an order; the range spans several days.
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		$after  = new DateTime( '-3 days' );
		$before = new DateTime( 'today' );
		$series = $this->engine->get_chart_series( $after, $before );

		$this->assertCount( 4, $series, 'Series should include every day in range, not just days with orders.' );

		$zero_days = array_filter( $series, fn( $day ) => 0.0 === $day['profit'] );
		$this->assertCount( 3, $zero_days, 'The three days without orders should show 0, not be missing.' );
	}

	public function test_insight_finds_worst_loss_making_product() {
		$profitable = $this->create_product( 5.0, 20.0 );
		$loser      = $this->create_product( 50.0, 20.0 ); // costs more than it sells for

		$this->create_order( array( array( 'product' => $profitable, 'qty' => 1, 'total' => 20.0 ) ) );
		$this->create_order( array( array( 'product' => $loser, 'qty' => 1, 'total' => 20.0 ) ) );

		list( $after, $before ) = $this->range();
		$insight = $this->engine->get_insight( $after, $before );

		$this->assertNotNull( $insight );
		$this->assertSame( 'loss_making_product', $insight['type'] );
		$this->assertSame( $loser->get_id(), $insight['product_id'] );
	}

	public function test_insight_is_null_when_nothing_lost_money() {
		$product = $this->create_product( 5.0, 20.0 );
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		list( $after, $before ) = $this->range();

		$this->assertNull( $this->engine->get_insight( $after, $before ) );
	}

	public function test_calculate_product_profit_aggregates_across_orders() {
		$product = $this->create_product( 5.0, 20.0 );
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$this->create_order( array( array( 'product' => $product, 'qty' => 2, 'total' => 40.0 ) ) );

		list( $after, $before ) = $this->range();
		$result = $this->engine->calculate_product_profit( $product->get_id(), $after, $before );

		$this->assertSame( 3, $result['units'] );
		$this->assertEquals( 60.0, $result['revenue'] );
	}

	public function test_calculate_product_profit_returns_null_for_unsold_product() {
		$product = $this->create_product( 5.0, 20.0 );

		list( $after, $before ) = $this->range();

		$this->assertNull( $this->engine->calculate_product_profit( $product->get_id(), $after, $before ) );
	}

	/**
	 * @param array  $summary
	 * @param string $key
	 * @return float|null
	 */
	private function find_cost_breakdown_amount( array $summary, $key ) {
		foreach ( $summary['cost_breakdown'] as $entry ) {
			if ( $entry['key'] === $key ) {
				return $entry['amount'];
			}
		}

		return null;
	}
}
