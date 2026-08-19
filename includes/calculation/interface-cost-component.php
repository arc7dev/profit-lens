<?php
/**
 * Contract for a per-order cost component: one line of `cost_breakdown` in
 * the REST response. FREE ships four implementations — ProductCost,
 * GatewayFees, Shipping, Refunds. PRO adds AdSpend the same way: a fifth
 * class implementing this interface, appended to the engine's component
 * list. Nothing else about the engine changes.
 *
 * Unlike ProfitLens_Cost_Source (which answers "what does this PRODUCT
 * cost?"), a cost component answers "how much does this concept subtract
 * from this ORDER's profit?" — ProductCost is the one component that needs
 * per-product cost data, and gets it from a ProfitLens_Cost_Source
 * internally; the others (gateway fees, shipping, refunds) read straight
 * off the order.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

interface ProfitLens_Cost_Component {

	/**
	 * Stable identifier, used as `key` in cost_breakdown
	 * (e.g. 'product_cost', 'gateway_fees').
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Human-readable label for the UI (e.g. "Product Cost").
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether this component's amount is a real recorded figure or an
	 * estimate (e.g. gateway fees computed from a configured rate rather
	 * than the payment processor's actual charge). Feeds `is_estimated` in
	 * cost_breakdown.
	 *
	 * @return bool
	 */
	public function is_estimated();

	/**
	 * This component's dollar amount for a single order.
	 *
	 * @param WC_Order $order
	 * @return float
	 */
	public function calculate( WC_Order $order );

	/**
	 * An optional caveat about this component's figure, surfaced verbatim
	 * in cost_breakdown so the UI doesn't need a separate lookup table to
	 * know when a number needs a footnote (e.g. Shipping: the amount is
	 * what was collected from the customer, not the real logistics cost —
	 * Free has no source for the latter). Null for components with nothing
	 * to caveat.
	 *
	 * @return string|null
	 */
	public function get_note();
}
