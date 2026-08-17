<?php
/**
 * Cases 7-11: refund handling — the single most dangerous class of bug
 * this engine can have (CLAUDE.md: counting an order's revenue AND its
 * refund record can silently subtract the same money twice).
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Component_Refunds extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Cost_Component_Refunds */
	private $component;

	public function setUp(): void {
		parent::setUp();
		$this->component = new ProfitLens_Cost_Component_Refunds();
	}

	/**
	 * Case 7: WooCommerce's automatic reconciliation refund (order status
	 * changed to "refunded" directly, no manual refund entered first) must
	 * be subtracted exactly once — not zero times (missed), not twice.
	 */
	public function test_automatic_full_refund_is_counted_exactly_once() {
		$product = $this->create_product( 8.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 3, 'total' => 60.0 ) ) );

		$this->trigger_automatic_full_refund( $order );

		$this->assertSame( 1, count( $order->get_refunds() ), 'Exactly one refund record should exist.' );
		$this->assertEquals( 60.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 8: a manual partial refund on an order that stays "completed"
	 * doesn't touch revenue — only the refunds component reflects it.
	 */
	public function test_manual_partial_refund_still_completed() {
		$product = $this->create_product( 8.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 5, 'total' => 100.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 40.0, 2 );
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( 'completed', $order->get_status() );
		$this->assertEquals( 40.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 9: a manual partial refund followed by the automatic
	 * remainder-refund (order later marked fully refunded) leaves TWO
	 * refund records. get_total_refunded() must sum both correctly to
	 * the full order total — not just read "the" first one.
	 */
	public function test_partial_refund_then_automatic_remainder_sums_correctly() {
		$product = $this->create_product( 8.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 5, 'total' => 100.0 ) ) );
		$item_id = array_key_first( $order->get_items( 'line_item' ) );

		$this->refund_item( $order, $item_id, 20.0, 1 );
		$order = wc_get_order( $order->get_id() );
		$this->trigger_automatic_full_refund( $order );

		$this->assertSame( 2, count( $order->get_refunds() ), 'A manual partial refund plus the automatic remainder should leave two refund records.' );
		$this->assertEquals( 100.0, $this->component->calculate( $order ), '', 0.01 );
	}

	/**
	 * Case 10.
	 */
	public function test_no_refund_is_zero() {
		$product = $this->create_product( 8.0, 20.0 );
		$order   = $this->create_order( array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ) );

		$this->assertEquals( 0.0, $this->component->calculate( $order ) );
	}

	public function test_key_label_and_is_estimated() {
		$this->assertSame( 'refunds', $this->component->get_key() );
		$this->assertSame( 'Refunds', $this->component->get_label() );
		$this->assertFalse( $this->component->is_estimated() );
		$this->assertNull( $this->component->get_note() );
	}
}
