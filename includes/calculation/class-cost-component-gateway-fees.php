<?php
/**
 * Gateway fees cost component. WooCommerce doesn't reliably store what a
 * payment processor actually charged, so this is a computed estimate:
 * percentage + fixed fee, configurable per gateway.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Component_Gateway_Fees implements ProfitLens_Cost_Component {

	const DEFAULT_RATE  = 0.029;
	const DEFAULT_FIXED = 0.30;

	/**
	 * rate% × order total + fixed. Uses the order's full total (product +
	 * shipping + tax) as the base, since that's the amount that actually
	 * gets run through the payment processor — not just product revenue.
	 *
	 * An order with no payment method (manually created, fully covered by
	 * a coupon, etc.) or a $0 total never actually hit a gateway, so the
	 * fee is 0 — charging the flat fee on a $0 order would be pure
	 * fiction, not an estimate.
	 *
	 * @param WC_Order $order
	 * @return float
	 */
	public function calculate( WC_Order $order ) {
		$total = (float) $order->get_total();

		if ( $total <= 0.0 || '' === $order->get_payment_method() ) {
			return 0.0;
		}

		list( $rate, $fixed ) = $this->get_rate_for_gateway( $order->get_payment_method(), $order );

		return round( $total * $rate + $fixed, 2 );
	}

	/**
	 * Per-gateway rate/fixed fee, overridable via filter since there's no
	 * settings screen yet — same pattern as the `profitlens_demo_status`
	 * REST override. A store owner (or a future settings page) can hook
	 * this to plug in real negotiated rates per gateway.
	 *
	 * @param string   $gateway_id
	 * @param WC_Order $order
	 * @return array{0:float,1:float} [rate, fixed]
	 */
	private function get_rate_for_gateway( $gateway_id, WC_Order $order ) {
		/**
		 * Filters the [rate, fixed] pair used to estimate a gateway's fee.
		 *
		 * @param array    $rate_and_fixed [percentage as a 0-1 fraction, fixed dollar fee].
		 * @param string   $gateway_id     Payment gateway ID (e.g. 'stripe', 'paypal').
		 * @param WC_Order $order
		 */
		return apply_filters(
			'profitlens_gateway_fee_rate',
			array( self::DEFAULT_RATE, self::DEFAULT_FIXED ),
			$gateway_id,
			$order
		);
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return 'gateway_fees';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Gateway Fees', 'profit-lens' );
	}

	/**
	 * Always true — this is a computed estimate, never the payment
	 * processor's actual charge.
	 *
	 * @return bool
	 */
	public function is_estimated() {
		return true;
	}

	/**
	 * @return null
	 */
	public function get_note() {
		return null;
	}
}
