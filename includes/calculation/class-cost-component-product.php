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
	 * Order item meta key a snapshot's resolved unit cost is written under
	 * (see write_snapshot()). Namespaced with `snapshot_` — not just
	 * `_profitlens_unit_cost` — so it reads unambiguously as a frozen
	 * historical value, never as something that tracks the product's
	 * current cost.
	 */
	const SNAPSHOT_META_KEY = '_profitlens_snapshot_unit_cost';

	/**
	 * Sentinel written to SNAPSHOT_META_KEY when a line's cost was
	 * genuinely unknown (no COGS configured) at snapshot time — see
	 * write_snapshot() for why this has to be an explicit value and not
	 * simply "don't write anything".
	 */
	const SNAPSHOT_UNKNOWN = '__unknown__';

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
	 * @return array<int,array{item_id:int,product:WC_Product|null,unit_cost:float|null,net_qty:float,line_revenue:float,refunded_amount:float,net_revenue:float,line_cost:float,from_snapshot:bool}>
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

			list( $unit_cost, $from_snapshot ) = $this->resolve_unit_cost( $item, $product );

			$line_cost = null !== $unit_cost ? $unit_cost * $net_qty : 0.0;

			$lines[ $item_id ] = array(
				'item_id'         => $item_id,
				'product'         => $product ? $product : null,
				'unit_cost'       => $unit_cost,
				'net_qty'         => $net_qty,
				'line_revenue'    => $line_revenue,
				'refunded_amount' => $refunded_amount,
				'net_revenue'     => max( 0.0, $line_revenue - $refunded_amount ),
				'line_cost'       => $line_cost,
				'from_snapshot'   => $from_snapshot,
			);
		}

		return $lines;
	}

	/**
	 * Unit cost for one line: a frozen snapshot if this item has one
	 * (written by write_snapshot() the moment the order first became
	 * "counted" — see ProfitLens_Cost_Snapshotter), otherwise the
	 * product's CURRENT cost, resolved live exactly as before this
	 * feature existed.
	 *
	 * The distinction that matters here is "does this item have
	 * SNAPSHOT_META_KEY at all", not "is the resolved cost non-null" — a
	 * line whose cost was genuinely unknown at snapshot time still gets
	 * SNAPSHOT_UNKNOWN written (see write_snapshot()), specifically so it
	 * does NOT fall through to live resolution later. An order with no
	 * meta at all is, by construction, one that predates this feature
	 * (no forced backfill — see CLAUDE.md/issue #7) and keeps behaving
	 * exactly as it did before: current-cost, live, every time.
	 *
	 * @param WC_Order_Item_Product $item
	 * @param WC_Product|false      $product
	 * @return array{0:float|null,1:bool} [unit_cost, from_snapshot]
	 */
	private function resolve_unit_cost( WC_Order_Item_Product $item, $product ) {
		$snapshot = $item->get_meta( self::SNAPSHOT_META_KEY, true );

		if ( '' !== $snapshot ) {
			return array(
				self::SNAPSHOT_UNKNOWN === $snapshot ? null : (float) $snapshot,
				true,
			);
		}

		if ( ! $product ) {
			return array( null, false );
		}

		return array( $this->cost_source->get_product_cost( $product ), false );
	}

	/**
	 * Freezes each line's current resolved unit cost as order item meta,
	 * so a later change to the product's cost can never retroactively
	 * change this order's numbers again (see issue #7). Called by
	 * ProfitLens_Cost_Snapshotter the moment an order first becomes
	 * "counted" (completed/processing) — never re-run after that, by
	 * design: the meta's mere presence is what makes a line immune to
	 * future cost changes, so re-writing it later would defeat the point.
	 *
	 * Idempotent per line ("first write wins"): a line that already has
	 * SNAPSHOT_META_KEY is left untouched. Safe to call more than once for
	 * the same order (e.g. a status bounces between counted and
	 * non-counted more than once) — only ever fills in what's missing.
	 *
	 * Writes SNAPSHOT_UNKNOWN, not nothing, for a line whose cost can't be
	 * resolved right now (no COGS configured, or a deleted product) — see
	 * resolve_unit_cost()'s docblock for why "wrote nothing" and "wrote
	 * unknown" have to mean different things.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function write_snapshot( WC_Order $order ) {
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			/** @var WC_Order_Item_Product $item */
			if ( '' !== $item->get_meta( self::SNAPSHOT_META_KEY, true ) ) {
				continue;
			}

			$product   = $item->get_product();
			$unit_cost = $product ? $this->cost_source->get_product_cost( $product ) : null;

			$item->update_meta_data(
				self::SNAPSHOT_META_KEY,
				null !== $unit_cost ? (string) $unit_cost : self::SNAPSHOT_UNKNOWN
			);
			$item->save();
		}
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
