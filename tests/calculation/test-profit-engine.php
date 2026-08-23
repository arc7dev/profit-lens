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
	 * Per-product revenue_covered_pct (feeds ProductTable's coverage chip)
	 * and the has_cost flag it supersedes: full coverage (100%, has_cost
	 * true) for a product whose cost is defined, zero coverage (0%,
	 * has_cost false) for one that isn't — checked on the same two
	 * products/order as the revenue-coverage test above, but reading
	 * products instead of cost_coverage. These are the two ends of the
	 * range; the partial case in between is covered separately below.
	 */
	public function test_product_coverage_pct_reflects_whether_its_own_cost_is_known() {
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

		$this->assertEquals( 100.0, $by_id[ $with_cost->get_id() ]['revenue_covered_pct'] );
		$this->assertTrue( $by_id[ $with_cost->get_id() ]['has_cost'] );

		$this->assertEquals( 0.0, $by_id[ $without_cost->get_id() ]['revenue_covered_pct'] );
		$this->assertFalse( $by_id[ $without_cost->get_id() ]['has_cost'] );
	}

	/**
	 * FIXED as of issue #7 (github.com/arc7dev/profit-lens/issues/7) — this
	 * test used to characterize the opposite, known-bad behavior (see git
	 * history for the original KNOWN-LIMITATION version of this test, and
	 * its own docblock's explicit instruction: "if cost resolution is ever
	 * changed to snapshot/version cost per order, THIS TEST WILL START
	 * FAILING (0.0 will become 50.0) — that is the fix working, not a
	 * regression"). It now asserts the fix.
	 *
	 * ProfitLens_Cost_Snapshotter freezes each line's resolved unit cost as
	 * order item meta the moment an order first becomes "counted" — see
	 * ProfitLens_Cost_Component_Product::write_snapshot(). Both orders here
	 * are created directly in 'completed' status (create_order()'s
	 * default), which — confirmed against WooCommerce core — means neither
	 * one ever passes through a status TRANSITION on an already-read
	 * object, so it's woocommerce_new_order, not
	 * woocommerce_order_status_changed, that fires the snapshot for each.
	 * That's exactly the "order born already in a counted status" case
	 * ProfitLens_Cost_Snapshotter has to cover on its own — see its
	 * class docblock.
	 *
	 * The unset happens strictly BETWEEN the two create_order() calls, so
	 * order 1 snapshots the real $5.00 cost and order 2 snapshots
	 * SNAPSHOT_UNKNOWN — a real 50/50 split, not the all-or-nothing result
	 * the product's current state alone would give.
	 */
	public function test_cost_resolution_is_now_versioned_per_order_via_snapshot() {
		$product = $this->create_product( 5.0, 20.0 );
		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		// Unset the cost after the first order — strip it directly rather
		// than via a second product, so both order lines point at the
		// exact same product_id.
		$product->set_cogs_value( null );
		$product->save();

		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		list( $after, $before ) = $this->range();
		$products = $this->engine->get_summary( $after, $before )['products'];

		// Order 1's line is frozen at the $5.00 cost that was live when it
		// was snapshotted — order 2's line, snapshotted after the cost was
		// removed, is not. Exactly half this product's period revenue is
		// covered, matching the per-order split rather than the product's
		// single current state.
		$this->assertEquals( 50.0, $products[0]['revenue_covered_pct'] );
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
	 * Issue #17: aggregate() used to load every matching order into memory
	 * at once (wc_get_orders(['limit' => -1])), which exhausted PHP's
	 * memory_limit on a real 202-day/1,661-order range. Fixed by paging
	 * through orders in get_batch_size()-sized chunks instead — this test
	 * forces that multi-page path via the profitlens_aggregate_batch_size
	 * filter (batch size 2, for 5 orders — 3 pages: 2+2+1) without
	 * actually needing hundreds of real orders to exercise it, and asserts
	 * the result is identical to what a single unbatched pass produces:
	 * same revenue, same order count, same per-product totals. A
	 * regression here (an order double-counted or dropped between pages)
	 * would silently corrupt every range wide enough to span more than one
	 * batch, not just unusually long ones — exactly the class of bug
	 * CLAUDE.md's "never calibrate a threshold to pass" rule exists for,
	 * so this compares against a hand-computed expectation, not against
	 * whatever the unbatched code happened to produce before this fix.
	 */
	public function test_aggregate_paginates_without_dropping_or_double_counting_orders() {
		$p1 = $this->create_product( 5.0, 20.0 );
		$p2 = $this->create_product( 8.0, 30.0 );

		// 5 orders, deliberately not a multiple of the batch size (2), so
		// the last page is a partial one — the do/while loop's stopping
		// condition (batch_count === batch_size) has to handle that
		// correctly, not just the "every page is full" case.
		$this->create_order( array( array( 'product' => $p1, 'qty' => 1, 'total' => 20.0 ) ) );
		$this->create_order( array( array( 'product' => $p1, 'qty' => 2, 'total' => 40.0 ) ) );
		$this->create_order( array( array( 'product' => $p2, 'qty' => 1, 'total' => 30.0 ) ) );
		$this->create_order( array( array( 'product' => $p2, 'qty' => 3, 'total' => 90.0 ) ) );
		$this->create_order( array( array( 'product' => $p1, 'qty' => 1, 'total' => 20.0 ) ) );

		list( $after, $before ) = $this->range();

		add_filter(
			'profitlens_aggregate_batch_size',
			function () {
				return 2;
			}
		);

		$summary = $this->engine->get_summary( $after, $before );

		remove_all_filters( 'profitlens_aggregate_batch_size' );

		// Hand-computed, not derived from the code under test: 20+40+30+90+20.
		$this->assertEquals( 200.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 5, $summary['kpis']['revenue']['orders_count'] );

		$p1_profit = $this->engine->calculate_product_profit( $p1->get_id(), $after, $before );
		$p2_profit = $this->engine->calculate_product_profit( $p2->get_id(), $after, $before );

		// p1: qty 1+2+1 = 4 units, revenue 20+40+20 = 80.
		$this->assertSame( 4, $p1_profit['units'] );
		$this->assertEquals( 80.0, $p1_profit['revenue'] );

		// p2: qty 1+3 = 4 units, revenue 30+90 = 120.
		$this->assertSame( 4, $p2_profit['units'] );
		$this->assertEquals( 120.0, $p2_profit['revenue'] );
	}

	/**
	 * The do/while loop in aggregate() has to terminate for a range with
	 * zero matching orders — an easy off-by-one to get wrong when
	 * switching from "one query" to "query until a short page" (e.g. a
	 * naive `do { ... } while ( count($orders) > 0 )` would query forever
	 * once the range is empty, since an empty first page never satisfies
	 * that particular condition... but would satisfy `count === $limit`
	 * only by coincidence if $limit were ever 0). Exercises that
	 * specifically with the default (non-filtered) batch size.
	 */
	public function test_aggregate_terminates_for_empty_range() {
		list( $after, $before ) = $this->range();

		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 0.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 0, $summary['kpis']['revenue']['orders_count'] );
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
