<?php
/**
 * Integration tests for ProfitLens_Cost_Snapshotter (issue #7) — both real
 * WooCommerce entry points a "counted" order can reach a snapshot through,
 * "first write wins" idempotency, and the SNAPSHOT_UNKNOWN sentinel that
 * keeps a null-cost line from ever falling back to live resolution again.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Snapshotter extends ProfitLens_Calculation_Test_Case {

	/**
	 * @param WC_Order $order
	 * @param int      $item_id
	 * @return string Raw meta value ('' when unset).
	 */
	private function snapshot_meta( WC_Order $order, $item_id ) {
		$item = $order->get_item( $item_id );

		return $item->get_meta( ProfitLens_Cost_Component_Product::SNAPSHOT_META_KEY, true );
	}

	/**
	 * create_order()'s default: a brand-new WC_Order, never read from the
	 * DB before set_status() runs, saved directly as 'completed'. Confirmed
	 * against WooCommerce core that this is exactly the case
	 * woocommerce_order_status_changed CANNOT catch (WC_Order::set_status()
	 * only queues a status_transition when $this->object_read is already
	 * true — class-wc-order.php) — so this test is really asserting the
	 * woocommerce_new_order entry point works, not the status-change one.
	 */
	public function test_order_created_directly_completed_gets_snapshotted() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->assertSame( '5', $this->snapshot_meta( $order, $item_id ) );
	}

	/**
	 * The normal storefront flow: an order that already exists as
	 * 'pending' (object_read becomes true once the data store's create()
	 * runs) and later transitions into a counted status — the
	 * woocommerce_order_status_changed path, distinct from the test above.
	 */
	public function test_order_transitioning_into_processing_gets_snapshotted() {
		$product = $this->create_product( 7.5, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ), 'pending' );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->assertSame( '', $this->snapshot_meta( $order, $item_id ), 'A pending order must not be snapshotted yet.' );

		$order->set_status( 'processing' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '7.5', $this->snapshot_meta( $order, $item_id ) );
	}

	/**
	 * A status that never becomes "counted" (per
	 * ProfitLens_Profit_Engine::get_counted_orders()) must never get a
	 * snapshot — on-hold is a real intermediate state (awaiting an offline
	 * payment, for example) that should still resolve live if it's ever
	 * inspected before actually converting.
	 */
	public function test_order_that_never_becomes_counted_is_not_snapshotted() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ), 'on-hold' );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->assertSame( '', $this->snapshot_meta( $order, $item_id ) );
	}

	/**
	 * The core fix (issue #7): "first write wins". A cost change AFTER an
	 * order is already counted must not touch that order's frozen line —
	 * calling write_snapshot() again (e.g. the order bounces
	 * processing → on-hold → processing, re-entering a counted status a
	 * second time) is a no-op for a line that already has a value.
	 */
	public function test_second_snapshot_attempt_does_not_overwrite_the_first() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ), 'processing' );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->assertSame( '5', $this->snapshot_meta( $order, $item_id ) );

		// Cost changes; order re-enters a counted status a second time.
		$product->set_cogs_value( 99.0 );
		$product->save();

		$order->set_status( 'on-hold' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$order->set_status( 'processing' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '5', $this->snapshot_meta( $order, $item_id ), 'The original snapshot must survive a second counted-status transition.' );
	}

	/**
	 * The other half of the core fix: a line with NO known cost at
	 * snapshot time still gets frozen (as SNAPSHOT_UNKNOWN), not left
	 * unwritten — otherwise this exact scenario (a product's cost gets
	 * filled in for the first time, which is what CostCoverageNotice
	 * actively prompts merchants to do) would still silently rewrite this
	 * already-counted order's numbers, which is the entire bug issue #7
	 * describes.
	 */
	public function test_cost_set_after_snapshot_does_not_retroactively_cover_the_order() {
		$product = $this->create_product( null, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->assertSame( ProfitLens_Cost_Component_Product::SNAPSHOT_UNKNOWN, $this->snapshot_meta( $order, $item_id ) );

		$product->set_cogs_value( 5.0 );
		$product->save();
		$order = wc_get_order( $order->get_id() );

		$engine   = ProfitLens_Profit_Engine::create_default();
		$lines    = $engine->get_product_cost_component()->resolve_line_items( $order );
		$line     = reset( $lines );

		$this->assertNull( $line['unit_cost'], 'The line must stay uncovered — the cost was unknown when this order was actually counted.' );
		$this->assertTrue( $line['from_snapshot'] );
	}

	/**
	 * An order that predates this feature (no snapshot meta at all — the
	 * project's deliberate choice not to force a backfill, see CLAUDE.md/
	 * issue #7) must keep behaving exactly as it did before: live,
	 * current-cost resolution, every time. Simulated by removing the
	 * snapshot meta write_snapshot() would have added, rather than mocking
	 * the hook — this is what a real pre-upgrade order actually looks like
	 * in the database.
	 */
	public function test_order_with_no_snapshot_falls_back_to_live_resolution() {
		$product = $this->create_product( 5.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$item = $order->get_item( $item_id );
		$item->delete_meta_data( ProfitLens_Cost_Component_Product::SNAPSHOT_META_KEY );
		$item->save();

		$product->set_cogs_value( 12.0 );
		$product->save();
		$order = wc_get_order( $order->get_id() );

		$engine = ProfitLens_Profit_Engine::create_default();
		$lines  = $engine->get_product_cost_component()->resolve_line_items( $order );
		$line   = reset( $lines );

		$this->assertSame( 12.0, $line['unit_cost'], 'No snapshot present must mean live resolution, matching pre-feature behavior exactly.' );
		$this->assertFalse( $line['from_snapshot'] );
	}

	/**
	 * cost_coverage.snapshot_covered_pct — the disclosure signal
	 * CostCoverageNotice reads. One snapshotted order, one order with its
	 * snapshot meta stripped to simulate a pre-feature order: exactly half
	 * this period's product revenue is snapshot-backed.
	 */
	public function test_snapshot_covered_pct_reflects_the_mix_of_snapshotted_and_legacy_orders() {
		$product = $this->create_product( 5.0, 20.0 );

		$this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		$legacy_order = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );
		$item_id      = array_key_first( $legacy_order->get_items( 'line_item' ) );
		$item         = $legacy_order->get_item( $item_id );
		$item->delete_meta_data( ProfitLens_Cost_Component_Product::SNAPSHOT_META_KEY );
		$item->save();

		list( $after, $before ) = array( new DateTime( '-7 days' ), new DateTime( 'tomorrow' ) );
		$coverage = ProfitLens_Profit_Engine::create_default()->get_cost_coverage( $after, $before );

		$this->assertEquals( 50.0, $coverage['snapshot_covered_pct'] );
	}
}
