<?php
/**
 * Product cost component: the sum of what an order's items actually cost
 * the store, net of whatever was returned.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Component_Product implements ProfitLens_Cost_Component {

	/**
	 * @var ProfitLens_Cost_Source
	 */
	private $cost_source;

	/**
	 * @param ProfitLens_Cost_Source $cost_source
	 */
	public function __construct( ProfitLens_Cost_Source $cost_source ) {
		$this->cost_source = $cost_source;
	}

	/**
	 * Exposed so the engine's whole-catalog coverage scan (not scoped to
	 * any one order) can reuse the exact same cost resolution, instead of
	 * duplicating it.
	 *
	 * @return ProfitLens_Cost_Source
	 */
	public function get_cost_source() {
		return $this->cost_source;
	}

	/**
	 * @param WC_Order $order
	 * @return float
	 */
	public function calculate( WC_Order $order ) {
		$total = 0.0;

		foreach ( $this->resolve_line_items( $order ) as $line ) {
			$total += $line['line_cost'];
		}

		return round( $total, 2 );
	}

	/**
	 * Per-line-item detail for an order: unit cost (null if unknown), net
	 * quantity (returned units excluded), gross vs. refund-net revenue,
	 * and the resulting line cost. Exposed separately from calculate() —
	 * not part of ProfitLens_Cost_Component, since nothing else needs
	 * this level of detail — because both the engine's per-product
	 * breakdown and its cost-coverage calculation need the exact same
	 * per-line resolution and must not duplicate it or drift apart.
	 *
	 * net_revenue matters as much as net_qty here: cost is already
	 * refund-adjusted (net_qty), so if a per-product profit figure used
	 * the unadjusted line_revenue instead of net_revenue, a partially
	 * refunded product would show inflated profit — cost going down
	 * while revenue stayed put. The period-level revenue total in
	 * ProfitLens_Profit_Engine deliberately does NOT do this per line
	 * (it uses gross line revenue and nets the whole order's refund once,
	 * as its own cost_breakdown entry — see get_order_revenue()); this
	 * net_revenue is specifically for per-product attribution, where
	 * there's no separate "refunds" bucket to net against otherwise.
	 *
	 * @param WC_Order $order
	 * @return array<int,array{item_id:int,product:WC_Product|null,unit_cost:float|null,net_qty:float,line_revenue:float,refunded_amount:float,net_revenue:float,line_cost:float}>
	 */
	public function resolve_line_items( WC_Order $order ) {
		$lines = array();

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();

			// Refund line items store negative quantities/totals by
			// WooCommerce convention (see wc_create_refund()) — abs() to
			// get how many units/dollars actually came back.
			$refunded_qty    = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
			$refunded_amount = abs( (float) $order->get_total_refunded_for_item( $item_id ) );
			$net_qty         = max( 0.0, (float) $item->get_quantity() - $refunded_qty );
			$line_revenue    = (float) $item->get_total();

			$unit_cost = null;
			$line_cost = 0.0;

			// A deleted product leaves get_product() returning false; no
			// cost data is possible for it, same as "unknown cost" below.
			if ( $product ) {
				$unit_cost = $this->cost_source->get_product_cost( $product );

				if ( null !== $unit_cost ) {
					$line_cost = $unit_cost * $net_qty;
				}
			}

			$lines[ $item_id ] = array(
				'item_id'         => $item_id,
				'product'         => $product ?: null,
				'unit_cost'       => $unit_cost,
				'net_qty'         => $net_qty,
				'line_revenue'    => $line_revenue,
				'refunded_amount' => $refunded_amount,
				'net_revenue'     => max( 0.0, $line_revenue - $refunded_amount ),
				'line_cost'       => $line_cost,
			);
		}

		return $lines;
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return 'product_cost';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Product Cost', 'profit-lens' );
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
