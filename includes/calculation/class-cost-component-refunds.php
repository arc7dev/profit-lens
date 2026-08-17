<?php
/**
 * Refunds cost component.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Component_Refunds implements ProfitLens_Cost_Component {

	/**
	 * Always $order->get_total_refunded() — never sums get_refunds()
	 * "by hand" and never assumes a single refund record. WooCommerce
	 * creates an automatic reconciliation WC_Order_Refund (reason "Order
	 * fully refunded.") the moment an order's status changes to
	 * "refunded", with no real refund necessarily having been entered —
	 * and a manual partial refund followed by that automatic one leaves
	 * TWO refund records that both have to count. get_total_refunded() is
	 * the data store's own aggregate and handles both cases correctly by
	 * construction; picking "the" refund and reading its total would not.
	 *
	 * @param WC_Order $order
	 * @return float
	 */
	public function calculate( WC_Order $order ) {
		return (float) $order->get_total_refunded();
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return 'refunds';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Refunds', 'profit-lens' );
	}

	/**
	 * @return bool
	 */
	public function is_estimated() {
		return false;
	}

	/**
	 * @return null
	 */
	public function get_note() {
		return null;
	}
}
