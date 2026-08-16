<?php
/**
 * Shipping cost component.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Component_Shipping implements ProfitLens_Cost_Component {

	/**
	 * The amount the customer was CHARGED for shipping — not what it cost
	 * the store to actually ship the order, which Free has no data source
	 * for. See ProfitLens_Profit_Engine::get_order_revenue() for why this
	 * matters: revenue includes this same amount, so it nets to zero
	 * impact on profit rather than being counted as a pure loss. That's
	 * the honest thing to do without real carrier-cost data — it is NOT
	 * the same as knowing whether the store made or lost money on
	 * shipping.
	 *
	 * @param WC_Order $order
	 * @return float
	 */
	public function calculate( WC_Order $order ) {
		return (float) $order->get_shipping_total();
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return 'shipping';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Shipping', 'profit-lens' );
	}

	/**
	 * @return bool
	 */
	public function is_estimated() {
		return false;
	}
}
