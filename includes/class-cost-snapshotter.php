<?php
/**
 * Hooks orders into ProfitLens_Cost_Component_Product::write_snapshot() the
 * moment they first become "counted" (completed/processing) — see issue #7
 * (github.com/arc7dev/profit-lens/issues/7): without this, product cost is
 * re-resolved from the product's CURRENT state on every report, so editing
 * a cost today silently rewrites every past period's profit for orders
 * that sold it.
 *
 * Two entry points, not one — both required, confirmed against WooCommerce
 * core (10.3, this environment):
 *
 * - woocommerce_order_status_changed: the normal flow (pending →
 *   processing/completed). Only fires when the order object being
 *   transitioned was already read from the database (object_read === true
 *   at the moment set_status() ran — see WC_Order::set_status() /
 *   WC_Abstract_Order status_transition() gating in class-wc-order.php).
 * - woocommerce_new_order: an order that is CREATED already in a counted
 *   status (e.g. an import, an integration, an admin-created order saved
 *   directly as "completed") never passes through a transition on an
 *   already-read object, so woocommerce_order_status_changed — and even
 *   the more specific woocommerce_order_status_completed — never fires for
 *   it. woocommerce_new_order does fire unconditionally on creation
 *   (skipping only draft statuses), on both the legacy CPT data store and
 *   the HPOS one (OrdersTableDataStore.php) — confirmed by reading both.
 *   Without this second hook, any order born directly into a counted
 *   status would silently never get a snapshot and would depend on live
 *   cost resolution forever, defeating the point for exactly the kind of
 *   order (bulk-imported, integration-created) most likely to exist in
 *   volume on a real store.
 *
 * Deliberately does NOT hook refund creation: refunds are their own
 * order type (shop_order_refund) and ProfitLens_Profit_Engine never reads
 * product cost from a refund object — only from the parent order's line
 * items (see get_counted_orders()'s type filter).
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Snapshotter {

	/**
	 * Statuses the profit engine actually counts — kept in exact sync
	 * with ProfitLens_Profit_Engine::get_counted_orders()'s status filter.
	 * Un-prefixed (no 'wc-'), matching what $order->get_status() and the
	 * 'to'/'from' status_transition args return.
	 *
	 * @var string[]
	 */
	const COUNTED_STATUSES = array( 'completed', 'processing' );

	public function __construct() {
		add_action( 'woocommerce_new_order', array( $this, 'maybe_snapshot_new_order' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_snapshot_status_change' ), 20, 4 );
	}

	/**
	 * @param int      $order_id
	 * @param WC_Order $order
	 * @return void
	 */
	public function maybe_snapshot_new_order( $order_id, $order ) {
		if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
			return;
		}

		if ( in_array( $order->get_status(), self::COUNTED_STATUSES, true ) ) {
			$this->snapshot( $order );
		}
	}

	/**
	 * @param int      $order_id
	 * @param string   $from
	 * @param string   $to
	 * @param WC_Order $order
	 * @return void
	 */
	public function maybe_snapshot_status_change( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
			return;
		}

		if ( in_array( $to, self::COUNTED_STATUSES, true ) ) {
			$this->snapshot( $order );
		}
	}

	/**
	 * Reuses the exact cost resolution/source wiring
	 * ProfitLens_Profit_Engine::create_default() decided, rather than
	 * instantiating a second ProfitLens_Cost_Source_Cogs here — if the
	 * active cost source ever changes, this stays correct with no edit.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	private function snapshot( WC_Order $order ) {
		ProfitLens_Profit_Engine::create_default()
			->get_product_cost_component()
			->write_snapshot( $order );
	}
}
