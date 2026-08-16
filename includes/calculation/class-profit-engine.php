<?php
/**
 * Profit calculation engine.
 *
 * Reads orders exclusively through the WooCommerce CRUD API
 * (wc_get_orders(), $order->get_*()) — never wp_posts/wp_postmeta
 * directly. Two independent reasons: it's the HPOS compatibility promise
 * already declared in profit-lens.php, and this same project found real
 * WooCommerce installs where the analytics lookup tables
 * (wp_wc_order_stats, wp_wc_order_product_lookup) were empty or stale
 * relative to the actual orders — they are not a trustworthy source of
 * truth. If a lookup-table optimization is ever added, it has to be an
 * opt-in cache with a CRUD fallback, never the primary source.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Profit_Engine {

	/**
	 * @var ProfitLens_Cost_Component_Product
	 */
	private $product_cost;

	/**
	 * The other cost components — shipping, refunds, gateway fees today;
	 * a Pro AdSpend component slots in here later without anything else
	 * in this class changing.
	 *
	 * @var ProfitLens_Cost_Component[]
	 */
	private $other_components;

	/**
	 * @param ProfitLens_Cost_Component_Product $product_cost     Kept as its own named
	 *                                                             dependency (not just one
	 *                                                             more entry in the array)
	 *                                                             because cost coverage is
	 *                                                             inherently product-cost-
	 *                                                             specific, not a generic
	 *                                                             cost-component concern.
	 * @param ProfitLens_Cost_Component[]       $other_components
	 */
	public function __construct( ProfitLens_Cost_Component_Product $product_cost, array $other_components = array() ) {
		$this->product_cost     = $product_cost;
		$this->other_components = $other_components;
	}

	/**
	 * Convenience constructor wiring up the standard Free-tier component
	 * set, in cost_breakdown display order. The one place that decides
	 * which components are active — reused by `wp profitlens verify` and,
	 * eventually, the REST controller.
	 *
	 * @return self
	 */
	public static function create_default() {
		$cost_source = new ProfitLens_Cost_Source_Cogs();

		return new self(
			new ProfitLens_Cost_Component_Product( $cost_source ),
			array(
				new ProfitLens_Cost_Component_Shipping(),
				new ProfitLens_Cost_Component_Refunds(),
				new ProfitLens_Cost_Component_Gateway_Fees(),
			)
		);
	}

	/**
	 * All active cost components, product cost first.
	 *
	 * @return ProfitLens_Cost_Component[]
	 */
	private function all_components() {
		return array_merge( array( $this->product_cost ), $this->other_components );
	}

	/**
	 * Profit for a single order: revenue - every active cost component.
	 *
	 * @param WC_Order $order
	 * @return array{revenue:float,costs:array<string,float>,total_cost:float,profit:float}
	 */
	public function calculate_order_profit( WC_Order $order ) {
		$revenue = $this->get_order_revenue( $order );
		$costs   = array();

		foreach ( $this->all_components() as $component ) {
			$costs[ $component->get_key() ] = $component->calculate( $order );
		}

		$total_cost = array_sum( $costs );

		return array(
			'revenue'    => $revenue,
			'costs'      => $costs,
			'total_cost' => round( $total_cost, 2 ),
			'profit'     => round( $revenue - $total_cost, 2 ),
		);
	}

	/**
	 * An order's revenue: product line items (already net of any coupon —
	 * get_total(), never get_subtotal(), never get_discount_total()
	 * subtracted separately) PLUS the amount charged for shipping. Tax is
	 * excluded entirely — it's the tax authority's money, never the
	 * merchant's, on either side of the ledger.
	 *
	 * Shipping deserves a note: get_shipping_total() is what the customer
	 * was CHARGED, not what it cost the store to actually ship the order
	 * (Free has no source for that). It's included here, and the exact
	 * same amount is ALSO subtracted as the "shipping" cost_breakdown
	 * entry (ProfitLens_Cost_Component_Shipping) — so it nets to zero
	 * profit impact rather than either overstating profit (counted as
	 * revenue with nothing offsetting it) or understating it (subtracted
	 * as a cost with no matching revenue, which is what an earlier version
	 * of this engine did: a store that collected $10 for shipping and
	 * spent $8 would have shown a flat -$10 hit to profit instead of the
	 * true ~+$2). Net-zero is the honest treatment without real carrier-
	 * cost data — it is NOT the same as knowing the store's real shipping
	 * margin, and the UI must not imply otherwise.
	 *
	 * @param WC_Order $order
	 * @return float
	 */
	public function get_order_revenue( WC_Order $order ) {
		$revenue = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$revenue += (float) $item->get_total();
		}

		$revenue += (float) $order->get_shipping_total();

		return round( $revenue, 2 );
	}

	/**
	 * Aggregate profit for a product over a date range.
	 *
	 * @param int               $product_id
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{product_id:int,name:string,units:int,revenue:float,cost:float,profit:float,margin_pct:float}|null
	 */
	public function calculate_product_profit( $product_id, DateTimeInterface $after, DateTimeInterface $before ) {
		$data = $this->aggregate( $after, $before );

		if ( ! isset( $data['products'][ $product_id ] ) ) {
			return null;
		}

		$product = $data['products'][ $product_id ];

		return array(
			'product_id' => $product_id,
			'name'       => $product['name'],
			'units'      => (int) round( $product['units'] ),
			'revenue'    => round( $product['revenue'], 2 ),
			'cost'       => round( $product['cost'], 2 ),
			'profit'     => round( $product['profit'], 2 ),
			'margin_pct' => round( $product['margin_pct'], 1 ),
		);
	}

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array<int,array{key:string,label:string,amount:float,is_estimated:bool}>
	 */
	public function get_cost_breakdown( DateTimeInterface $after, DateTimeInterface $before ) {
		return $this->format_cost_breakdown( $this->aggregate( $after, $before ) );
	}

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{products_with_cost:int,products_total:int,pct:float,revenue_covered_pct:float,revenue_uncovered:float}
	 */
	public function get_cost_coverage( DateTimeInterface $after, DateTimeInterface $before ) {
		return $this->format_cost_coverage( $this->aggregate( $after, $before ) );
	}

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array<int,array{date:string,label:string,profit:float}>
	 */
	public function get_chart_series( DateTimeInterface $after, DateTimeInterface $before ) {
		return $this->format_chart_series( $this->aggregate( $after, $before ), $after, $before );
	}

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{type:string,message:string,product_id:int}|null
	 */
	public function get_insight( DateTimeInterface $after, DateTimeInterface $before ) {
		return $this->format_insight( $this->aggregate( $after, $before ) );
	}

	/**
	 * Orchestrates everything above into the shape
	 * GET /profit-lens/v1/summary needs. Aggregates orders exactly once —
	 * the individual get_*() methods above each aggregate independently
	 * for standalone use, but this is the path the REST controller will
	 * actually call, so it doesn't pay for five full order scans to build
	 * one response.
	 *
	 * Deliberately does NOT include the `range`/`status` envelope from the
	 * REST contract, nor `change_pct` (a prior-period comparison, which
	 * needs the caller to run this twice) — those are response-shaping
	 * concerns for whoever calls the engine, not something a calculation
	 * result should decide for itself.
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array
	 */
	public function get_summary( DateTimeInterface $after, DateTimeInterface $before ) {
		$data = $this->aggregate( $after, $before );

		$revenue    = round( $data['revenue'], 2 );
		$total_cost = round( array_sum( $data['cost_totals'] ), 2 );
		$profit     = round( $revenue - $total_cost, 2 );
		$currency   = get_woocommerce_currency();

		$products        = $this->format_products( $data );
		$products_totals = $this->sum_products( $products );

		return array(
			'kpis'           => array(
				'net_profit'     => array(
					'amount'     => $profit,
					'currency'   => $currency,
					'change_pct' => null,
				),
				'net_margin_pct' => $revenue > 0.0 ? round( ( $profit / $revenue ) * 100, 1 ) : 0.0,
				'revenue'        => array(
					'amount'       => $revenue,
					'currency'     => $currency,
					'orders_count' => count( $data['orders'] ),
				),
				'total_costs'    => array(
					'amount'   => $total_cost,
					'currency' => $currency,
				),
			),
			'insight'        => $this->format_insight( $data ),
			'cost_coverage'  => $this->format_cost_coverage( $data ),
			'chart'          => array( 'series' => $this->format_chart_series( $data, $after, $before ) ),
			'cost_breakdown' => $this->format_cost_breakdown( $data ),
			'products'       => $products,
			'products_meta'  => array(
				'total'  => count( $products ),
				'totals' => $products_totals,
			),
		);
	}

	// -----------------------------------------------------------------
	// Aggregation — one pass over the period's orders, shared by every
	// public method above.
	// -----------------------------------------------------------------

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array
	 */
	private function aggregate( DateTimeInterface $after, DateTimeInterface $before ) {
		$orders = $this->get_counted_orders( $after, $before );

		$revenue           = 0.0;
		$cost_totals        = array();
		$chart_by_day       = array();
		$products           = array();
		$revenue_covered    = 0.0;
		$revenue_uncovered  = 0.0;

		foreach ( $this->all_components() as $component ) {
			$cost_totals[ $component->get_key() ] = 0.0;
		}

		foreach ( $orders as $order ) {
			$order_revenue = $this->get_order_revenue( $order );
			$revenue      += $order_revenue;

			$order_costs = array();

			foreach ( $this->all_components() as $component ) {
				$amount                                = $component->calculate( $order );
				$order_costs[ $component->get_key() ]  = $amount;
				$cost_totals[ $component->get_key() ] += $amount;
			}

			$order_profit = $order_revenue - array_sum( $order_costs );

			$created = $order->get_date_created();

			if ( $created ) {
				$day                   = $created->date( 'Y-m-d' );
				$chart_by_day[ $day ]  = ( isset( $chart_by_day[ $day ] ) ? $chart_by_day[ $day ] : 0.0 ) + $order_profit;
			}

			foreach ( $this->product_cost->resolve_line_items( $order ) as $line ) {
				if ( ! $line['product'] ) {
					continue;
				}

				$product_id = $line['product']->get_id();

				if ( ! isset( $products[ $product_id ] ) ) {
					$products[ $product_id ] = array(
						'id'      => $product_id,
						'name'    => $line['product']->get_name(),
						'units'   => 0.0,
						'revenue' => 0.0,
						'cost'    => 0.0,
					);
				}

				$products[ $product_id ]['units']   += $line['net_qty'];
				$products[ $product_id ]['revenue'] += $line['net_revenue'];
				$products[ $product_id ]['cost']    += $line['line_cost'];

				if ( null !== $line['unit_cost'] ) {
					$revenue_covered += $line['net_revenue'];
				} else {
					$revenue_uncovered += $line['net_revenue'];
				}
			}
		}

		foreach ( $products as $product_id => $product ) {
			$products[ $product_id ]['profit']     = $product['revenue'] - $product['cost'];
			$products[ $product_id ]['margin_pct'] = $product['revenue'] > 0.0
				? ( $products[ $product_id ]['profit'] / $product['revenue'] ) * 100
				: 0.0;
		}

		return array(
			'orders'            => $orders,
			'revenue'           => $revenue,
			'cost_totals'       => $cost_totals,
			'chart_by_day'      => $chart_by_day,
			'products'          => $products,
			'revenue_covered'   => $revenue_covered,
			'revenue_uncovered' => $revenue_uncovered,
		);
	}

	/**
	 * The only order query in the engine. `type` is deliberately locked to
	 * 'shop_order' — WooCommerce stores refunds as their own order-type
	 * records (type=shop_order_refund) in the same table, and if those
	 * were ever iterated here as if they were regular orders, each
	 * refund's (negative) total would be counted as its own "sale" on top
	 * of the parent order's own revenue AND the Refunds component
	 * subtracting the same refund again from the parent — the exact
	 * double-subtraction CLAUDE.md warns is the most dangerous bug this
	 * engine could have.
	 *
	 * Only 'completed' and 'processing' count as revenue, per product
	 * decision — cancelled, failed, on-hold, and (status-level) refunded
	 * orders contribute nothing. A partially refunded order that is still
	 * 'completed' IS counted, with the refund netted via
	 * ProfitLens_Cost_Component_Refunds.
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return WC_Order[]
	 */
	private function get_counted_orders( DateTimeInterface $after, DateTimeInterface $before ) {
		return wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'date_created' => $after->format( 'Y-m-d' ) . '...' . $before->format( 'Y-m-d' ),
				'limit'        => -1,
				'return'       => 'objects',
			)
		);
	}

	/**
	 * Whole-catalog cost coverage (not date-scoped, unlike revenue
	 * coverage) — every sellable product/variation, regardless of whether
	 * it sold in the requested period. Variable-parent products aren't
	 * sellable themselves and are skipped in favor of their variations.
	 *
	 * @return array{with_cost:int,total:int}
	 */
	private function get_catalog_coverage() {
		$with_cost   = 0;
		$total       = 0;
		$cost_source = $this->product_cost->get_cost_source();

		$product_ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( ! $variation ) {
						continue;
					}

					++$total;

					if ( null !== $cost_source->get_product_cost( $variation ) ) {
						++$with_cost;
					}
				}

				continue;
			}

			++$total;

			if ( null !== $cost_source->get_product_cost( $product ) ) {
				++$with_cost;
			}
		}

		return array(
			'with_cost' => $with_cost,
			'total'     => $total,
		);
	}

	// -----------------------------------------------------------------
	// Formatting — turns raw aggregate() data into REST-contract shapes.
	// -----------------------------------------------------------------

	/**
	 * @param array $data
	 * @return array
	 */
	private function format_cost_breakdown( array $data ) {
		$breakdown = array();

		foreach ( $this->all_components() as $component ) {
			$breakdown[] = array(
				'key'          => $component->get_key(),
				'label'        => $component->get_label(),
				'amount'       => round( $data['cost_totals'][ $component->get_key() ], 2 ),
				'is_estimated' => $component->is_estimated(),
			);
		}

		return $breakdown;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function format_cost_coverage( array $data ) {
		$catalog       = $this->get_catalog_coverage();
		$revenue_total = $data['revenue_covered'] + $data['revenue_uncovered'];

		return array(
			'products_with_cost'  => $catalog['with_cost'],
			'products_total'      => $catalog['total'],
			'pct'                 => $catalog['total'] > 0
				? round( ( $catalog['with_cost'] / $catalog['total'] ) * 100, 1 )
				: 0.0,
			'revenue_covered_pct' => $revenue_total > 0.0
				? round( ( $data['revenue_covered'] / $revenue_total ) * 100, 1 )
				: 100.0,
			'revenue_uncovered'   => round( $data['revenue_uncovered'], 2 ),
		);
	}

	/**
	 * @param array              $data
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array
	 */
	private function format_chart_series( array $data, DateTimeInterface $after, DateTimeInterface $before ) {
		$series = array();
		$cursor = new DateTime( $after->format( 'Y-m-d' ) );
		$end    = new DateTime( $before->format( 'Y-m-d' ) );

		while ( $cursor <= $end ) {
			$day      = $cursor->format( 'Y-m-d' );
			$series[] = array(
				'date'   => $day,
				'label'  => $cursor->format( 'M j' ),
				'profit' => round( isset( $data['chart_by_day'][ $day ] ) ? $data['chart_by_day'][ $day ] : 0.0, 2 ),
			);
			$cursor->modify( '+1 day' );
		}

		return $series;
	}

	/**
	 * Finds the product that lost the most money in the period. Requires
	 * net_revenue/line_cost from resolve_line_items() (not the gross
	 * per-order figures) so that a refund-driven loss actually surfaces
	 * here instead of being masked by unadjusted revenue.
	 *
	 * @param array $data
	 * @return array|null
	 */
	private function format_insight( array $data ) {
		$worst = null;

		foreach ( $data['products'] as $product ) {
			if ( $product['profit'] >= 0 ) {
				continue;
			}

			if ( null === $worst || $product['profit'] < $worst['profit'] ) {
				$worst = $product;
			}
		}

		if ( null === $worst ) {
			return null;
		}

		return array(
			'type'       => 'loss_making_product',
			'message'    => sprintf(
				/* translators: 1: product name, 2: dollar amount lost, 3: units sold */
				__( '%1$s lost $%2$s this period — %3$d units sold below cost.', 'profit-lens' ),
				$worst['name'],
				number_format( abs( $worst['profit'] ), 2 ),
				(int) round( $worst['units'] )
			),
			'product_id' => $worst['id'],
		);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function format_products( array $data ) {
		$products = array();

		foreach ( $data['products'] as $product ) {
			$products[] = array(
				'id'         => $product['id'],
				'name'       => $product['name'],
				'units'      => (int) round( $product['units'] ),
				'revenue'    => round( $product['revenue'], 2 ),
				'cost'       => round( $product['cost'], 2 ),
				'profit'     => round( $product['profit'], 2 ),
				'margin_pct' => round( $product['margin_pct'], 1 ),
			);
		}

		usort(
			$products,
			function ( $a, $b ) {
				return $b['profit'] <=> $a['profit'];
			}
		);

		return $products;
	}

	/**
	 * Sum of the "Profit by Product" rows — deliberately NOT the same as
	 * kpis.total_costs (which also includes shipping/gateway fees/refunds,
	 * none of which are attributable to a single product).
	 *
	 * @param array $products
	 * @return array
	 */
	private function sum_products( array $products ) {
		$totals = array(
			'units'      => 0,
			'revenue'    => 0.0,
			'cost'       => 0.0,
			'profit'     => 0.0,
			'margin_pct' => 0.0,
		);

		foreach ( $products as $product ) {
			$totals['units']   += $product['units'];
			$totals['revenue'] += $product['revenue'];
			$totals['cost']    += $product['cost'];
			$totals['profit']  += $product['profit'];
		}

		$totals['revenue']    = round( $totals['revenue'], 2 );
		$totals['cost']       = round( $totals['cost'], 2 );
		$totals['profit']     = round( $totals['profit'], 2 );
		$totals['margin_pct'] = $totals['revenue'] > 0.0
			? round( ( $totals['profit'] / $totals['revenue'] ) * 100, 1 )
			: 0.0;

		return $totals;
	}
}
