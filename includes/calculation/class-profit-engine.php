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
	/**
	 * Exposes the wired product-cost component so hook glue (e.g.
	 * ProfitLens_Cost_Snapshotter) can reuse the exact same cost
	 * resolution/source wiring create_default() decided, instead of
	 * duplicating that decision in a second place.
	 *
	 * @return ProfitLens_Cost_Component_Product
	 */
	public function get_product_cost_component() {
		return $this->product_cost;
	}

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
	 * @return array{product_id:int,name:string,units:int,revenue:float,cost:float,profit:float,margin_pct:float,has_cost:bool,revenue_covered_pct:float}|null
	 */
	public function calculate_product_profit( $product_id, DateTimeInterface $after, DateTimeInterface $before ) {
		$data = $this->aggregate( $after, $before );

		if ( ! isset( $data['products'][ $product_id ] ) ) {
			return null;
		}

		$product = $data['products'][ $product_id ];

		return array(
			'product_id'          => $product_id,
			'name'                => $product['name'],
			'units'               => (int) round( $product['units'] ),
			'revenue'             => round( $product['revenue'], 2 ),
			'cost'                => round( $product['cost'], 2 ),
			'profit'              => round( $product['profit'], 2 ),
			'margin_pct'          => round( $product['margin_pct'], 1 ),
			'has_cost'            => $product['has_cost'],
			'revenue_covered_pct' => round( $product['revenue_covered_pct'], 1 ),
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
	 * @return array{products_with_cost:int,products_total:int,pct:float,revenue_covered_pct:float,revenue_uncovered:float,snapshot_covered_pct:float}
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
					'orders_count' => $data['orders_count'],
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
	 * Default for get_batch_size() — how many orders aggregate() loads
	 * into memory (as full WC_Order objects) at a time. See get_batch_size()
	 * for why this exists at all.
	 */
	const DEFAULT_BATCH_SIZE = 200;

	/**
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @param bool              $lite  When true, skips every part of this
	 *                                  pass that only exists to feed the
	 *                                  chart/products/coverage sections of
	 *                                  get_summary() — see get_net_profit().
	 *                                  revenue and cost_totals (the only
	 *                                  two things a profit figure needs)
	 *                                  are computed identically either way.
	 * @return array
	 */
	private function aggregate( DateTimeInterface $after, DateTimeInterface $before, $lite = false ) {
		$revenue                 = 0.0;
		$cost_totals             = array();
		$chart_by_day            = array();
		$products                = array();
		$revenue_covered         = 0.0;
		$revenue_uncovered       = 0.0;
		$revenue_snapshot_backed = 0.0;
		$orders_count            = 0;

		foreach ( $this->all_components() as $component ) {
			$cost_totals[ $component->get_key() ] = 0.0;
		}

		// Paged rather than one wc_get_orders(['limit' => -1, ...]) call —
		// issue #17: a 202-day range with 1,661 orders exhausted PHP's
		// memory_limit loading every WC_Order object at once. Each batch
		// is processed and discarded (unset() below) before the next page
		// is fetched, so memory use stays bounded by batch size regardless
		// of how many orders the range actually contains. Everything from
		// here to the closing brace of the inner foreach is the exact same
		// per-order accumulation this method always did — only the outer
		// loop and the source of $orders are new, deliberately, to keep
		// this change reviewable as "same math, paged differently" rather
		// than a rewrite of the aggregation logic itself.
		$batch_size = $this->get_batch_size();
		$page       = 1;

		do {
			$orders        = $this->get_counted_orders( $after, $before, $page, $batch_size );
			$batch_count   = count( $orders );
			$orders_count += $batch_count;

			foreach ( $orders as $order ) {
				$order_revenue = $this->get_order_revenue( $order );
				$revenue      += $order_revenue;

				// Resolved once per order and reused below for both the
				// product cost component's total AND the per-product
				// breakdown — resolve_line_items() is the single most
				// expensive per-order operation here (a get_product_cost()
				// plus refunded-qty/-amount lookup per line item), and calling
				// it twice (once implicitly via
				// ProfitLens_Cost_Component_Product::calculate(), once
				// explicitly for the breakdown below) used to do exactly that
				// redundant work on every single get_summary() call. It can't
				// be skipped even in $lite mode: it's how the product cost
				// component's own total gets computed, which revenue/cost_totals
				// need either way.
				$product_lines      = $this->product_cost->resolve_line_items( $order );
				$product_cost_total = 0.0;

				foreach ( $product_lines as $line ) {
					$product_cost_total += $line['line_cost'];
				}

				$order_costs = array();

				foreach ( $this->all_components() as $component ) {
					// Same rounding ProfitLens_Cost_Component_Product::calculate()
					// itself applies — this branch has to stay in exact sync
					// with that method's behavior, not just its current
					// implementation, since it's standing in for a call to it.
					$amount = ( $component === $this->product_cost )
						? round( $product_cost_total, 2 )
						: $component->calculate( $order );

					$order_costs[ $component->get_key() ]  = $amount;
					$cost_totals[ $component->get_key() ] += $amount;
				}

				if ( $lite ) {
					// Everything from here down only feeds chart_by_day,
					// products, and revenue_covered/uncovered — none of which
					// get_net_profit() reads. Skipping it doesn't touch a
					// single query (it was already-loaded, in-memory data
					// either way); it only cuts the per-order PHP bookkeeping.
					continue;
				}

				$order_profit = $order_revenue - array_sum( $order_costs );

				$created = $order->get_date_created();

				if ( $created ) {
					$day                  = $created->date( 'Y-m-d' );
					$chart_by_day[ $day ] = ( isset( $chart_by_day[ $day ] ) ? $chart_by_day[ $day ] : 0.0 ) + $order_profit;
				}

				foreach ( $product_lines as $line ) {
					if ( ! $line['product'] ) {
						continue;
					}

					$product_id = $line['product']->get_id();

					// Scoped identically to revenue_covered/revenue_uncovered
					// just below (same "has a product" gate, same net_revenue
					// figure) so snapshot_covered_pct shares the exact same
					// denominator as revenue_covered_pct — both describe a
					// fraction of the same attributable-revenue universe.
					if ( $line['from_snapshot'] ) {
						$revenue_snapshot_backed += $line['net_revenue'];
					}

					if ( ! isset( $products[ $product_id ] ) ) {
						$products[ $product_id ] = array(
							'id'                => $product_id,
							'name'              => $line['product']->get_name(),
							'units'             => 0.0,
							'revenue'           => 0.0,
							'cost'              => 0.0,
							// Split the same way the period-level revenue_covered/
							// revenue_uncovered totals above are — this product's
							// own share of each, so per-product coverage can be a
							// real percentage instead of the all-or-nothing
							// has_cost boolean below. A product with cost known
							// for 9 of its 10 sales this period should not read
							// the same as one with no cost anywhere: its
							// cost/profit figures are only slightly off, not
							// fabricated. Denominator for the percentage is this
							// same product's own 'revenue' (built up below) — by
							// construction, revenue_covered + revenue_uncovered
							// always sums to it exactly, since every line's
							// net_revenue lands in exactly one of the three.
							'revenue_covered'   => 0.0,
							'revenue_uncovered' => 0.0,
						);
					}

					$products[ $product_id ]['units']   += $line['net_qty'];
					$products[ $product_id ]['revenue'] += $line['net_revenue'];
					$products[ $product_id ]['cost']    += $line['line_cost'];

					if ( null !== $line['unit_cost'] ) {
						$revenue_covered                            += $line['net_revenue'];
						$products[ $product_id ]['revenue_covered'] += $line['net_revenue'];
					} else {
						$revenue_uncovered                            += $line['net_revenue'];
						$products[ $product_id ]['revenue_uncovered'] += $line['net_revenue'];
					}
				}
			}

			// Drop this batch's WC_Order objects before fetching the next
			// page — the whole point of paging in the first place.
			unset( $orders );

			// unset() alone is NOT enough — confirmed empirically, not
			// assumed: WordPress's non-persistent object cache keeps its
			// own reference to every order/item/product loaded this
			// request (cache groups 'orders', 'order-items',
			// 'order_item_meta', 'posts', 'post_meta', and the taxonomy
			// relationship groups touched while resolving each line's
			// product), and nothing evicts that cache mid-request by
			// default. Measured directly against this environment's 1,661-
			// order/202-day repro (issue #17): unset() alone still grew
			// from 91MB to 125MB over 4 batches of 200 (heading straight
			// for the 128MB limit); adding this call plateaued it at
			// ~95MB for the full 1,661 orders across 9 batches.
			// wp_cache_flush_runtime() (WP 6.0+, this plugin requires
			// 6.4+) rather than naming those cache groups directly: it
			// clears exactly the non-persistent, per-request runtime
			// cache — which is what's actually leaking here — without
			// touching a persistent object cache backend (Redis/
			// Memcached) a real host might have configured, and without
			// this code silently going stale if a future WooCommerce
			// version renames its internal cache groups (already
			// observed once while investigating this: this environment's
			// actual group names didn't match what OrdersTableDataStore's
			// own source suggested).
			wp_cache_flush_runtime();

			++$page;
		} while ( $batch_count === $batch_size );

		foreach ( $products as $product_id => $product ) {
			$products[ $product_id ]['profit']     = $product['revenue'] - $product['cost'];
			$products[ $product_id ]['margin_pct'] = $product['revenue'] > 0.0
				? ( $products[ $product_id ]['profit'] / $product['revenue'] ) * 100
				: 0.0;

			// Same convention format_cost_coverage() uses at the period
			// level for the exact same "no revenue to divide by" case: a
			// product with zero revenue this period (e.g. every sale was
			// fully refunded) has nothing to be "overstated", so it isn't
			// flagged as uncovered by default.
			$products[ $product_id ]['revenue_covered_pct'] = $product['revenue'] > 0.0
				? ( $product['revenue_covered'] / $product['revenue'] ) * 100
				: 100.0;

			// Retained for any consumer still reading the old all-or-nothing
			// flag — true only when every line's cost was known, i.e.
			// coverage is (within float rounding of) exactly 100%. New code
			// should read revenue_covered_pct instead, which distinguishes
			// "no cost anywhere" from "cost known for most of this
			// product's sales" — has_cost collapses that distinction.
			$products[ $product_id ]['has_cost'] = $products[ $product_id ]['revenue_covered_pct'] >= 99.95;
		}

		return array(
			'orders_count'            => $orders_count,
			'revenue'                 => $revenue,
			'cost_totals'             => $cost_totals,
			'chart_by_day'            => $chart_by_day,
			'products'                => $products,
			'revenue_covered'         => $revenue_covered,
			'revenue_uncovered'       => $revenue_uncovered,
			'revenue_snapshot_backed' => $revenue_snapshot_backed,
		);
	}

	/**
	 * Net profit for a period, and nothing else — built for change_pct's
	 * prior-period comparison, which only ever reads this one number. Skips
	 * catalog-wide cost coverage entirely (2 SQL queries that get_summary()
	 * pays for via format_cost_coverage()/get_catalog_coverage() — coverage
	 * isn't date-scoped, so the current period's call already has the
	 * answer; a prior-period call would just ask the same question again)
	 * and runs aggregate() in $lite mode (skips per-order chart/products
	 * bookkeeping — no queries either way, since resolve_line_items() still
	 * runs to compute cost; only the extra array-building on top of
	 * already-loaded data is skipped).
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return float
	 */
	public function get_net_profit( DateTimeInterface $after, DateTimeInterface $before ) {
		$data       = $this->aggregate( $after, $before, true );
		$revenue    = round( $data['revenue'], 2 );
		$total_cost = round( array_sum( $data['cost_totals'] ), 2 );

		return round( $revenue - $total_cost, 2 );
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
	 * @param int               $page  1-indexed, per wc_get_orders()'s own 'page' arg
	 *                                  (aliases to 'paged' internally for both the
	 *                                  HPOS and CPT data stores — confirmed against
	 *                                  this environment's WooCommerce, not assumed).
	 * @param int               $limit Max orders for this page. Never -1 here — see
	 *                                  aggregate(), which is the only caller and pages
	 *                                  through in get_batch_size()-sized chunks
	 *                                  specifically so this never has to load every
	 *                                  matching order at once (issue #17).
	 * @return WC_Order[]
	 */
	private function get_counted_orders( DateTimeInterface $after, DateTimeInterface $before, $page, $limit ) {
		return wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'date_created' => $after->format( 'Y-m-d' ) . '...' . $before->format( 'Y-m-d' ),
				'limit'        => $limit,
				'page'         => $page,
				// Explicit, not incidental: a deterministic sort is what
				// makes paging safe at all — otherwise page 2 could
				// reshuffle in an order page 1 already counted, or skip/
				// repeat one, between the two queries. date_created alone
				// isn't sufficient for that guarantee: checked
				// OrdersTableQuery::process_orderby() in this environment's
				// WooCommerce — it does NOT append id as an automatic
				// tiebreaker, so two orders sharing the exact same
				// date_created_gmt (down to the second) would have no
				// guaranteed relative order across pages on ORDER BY date
				// alone. 'date ID' compounds to
				// "ORDER BY date_created_gmt ASC, id ASC" — id is the
				// table's unique primary key, so this is fully
				// deterministic regardless of how many orders share a
				// timestamp.
				'orderby'      => 'date ID',
				'order'        => 'ASC',
				'return'       => 'objects',
			)
		);
	}

	/**
	 * How many orders aggregate() loads into memory (as full WC_Order
	 * objects) per page, instead of one wc_get_orders(['limit' => -1])
	 * call for the whole range. Issue #17: a 202-day range with 1,661
	 * orders exhausted PHP's memory_limit doing exactly that.
	 *
	 * Filterable rather than hardcoded so tests can exercise the
	 * multi-page path without creating hundreds of real orders, and so a
	 * host with unusually low memory_limit (or unusually large orders)
	 * has an escape hatch without a code change.
	 *
	 * @return int
	 */
	private function get_batch_size() {
		/**
		 * Filters ProfitLens_Profit_Engine::aggregate()'s order batch size.
		 *
		 * @param int $batch_size
		 */
		$batch_size = (int) apply_filters( 'profitlens_aggregate_batch_size', self::DEFAULT_BATCH_SIZE );

		// A batch size <= 0 would make the do/while loop in aggregate()
		// never terminate (0 matches `$batch_count === $batch_size` after
		// an empty final page just as easily as it matches "more pages
		// exist") — guard against a bad filter value rather than let that
		// happen.
		return $batch_size > 0 ? $batch_size : self::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Whole-catalog cost coverage (not date-scoped, unlike revenue
	 * coverage) — every sellable product/variation, regardless of whether
	 * it sold in the requested period. Variable-parent products aren't
	 * sellable themselves and are skipped in favor of their variations.
	 *
	 * Prefers a bulk SQL count when the active cost source exposes one
	 * (get_catalog_coverage(), checked via method_exists rather than the
	 * ProfitLens_Cost_Source interface — it's an optional fast path, not
	 * every source can offer one; see ProfitLens_Cost_Source_Cogs's version
	 * for why). Measured difference on a 2,800-product catalog: ~7,200
	 * queries / ~1.4s (looping wc_get_product() with no persistent object
	 * cache, the normal state of a fresh REST request) vs. 2 queries / a
	 * few ms. Falls back to the per-product loop for any cost source that
	 * doesn't implement the fast path.
	 *
	 * @return array{with_cost:int,total:int}
	 */
	private function get_catalog_coverage() {
		$cost_source = $this->product_cost->get_cost_source();

		if ( method_exists( $cost_source, 'get_catalog_coverage' ) ) {
			return $cost_source->get_catalog_coverage();
		}

		$with_cost = 0;
		$total     = 0;

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
				'note'         => $component->get_note(),
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
			'products_with_cost'   => $catalog['with_cost'],
			'products_total'       => $catalog['total'],
			'pct'                  => $catalog['total'] > 0
				? round( ( $catalog['with_cost'] / $catalog['total'] ) * 100, 1 )
				: 0.0,
			'revenue_covered_pct'  => $revenue_total > 0.0
				? round( ( $data['revenue_covered'] / $revenue_total ) * 100, 1 )
				: 100.0,
			'revenue_uncovered'    => round( $data['revenue_uncovered'], 2 ),
			// What fraction of this same revenue is backed by a frozen
			// per-order cost snapshot (issue #7) rather than the
			// product's current, editable cost — i.e. how much of this
			// period is immune to a future COGS edit rewriting it. Orders
			// placed before this feature shipped have no snapshot (no
			// forced backfill — see CLAUDE.md) and count against this
			// figure exactly like an uncovered product does against
			// revenue_covered_pct: visibly, not silently.
			'snapshot_covered_pct' => $revenue_total > 0.0
				? round( ( $data['revenue_snapshot_backed'] / $revenue_total ) * 100, 1 )
				: 100.0,
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
				'id'                  => $product['id'],
				'name'                => $product['name'],
				'units'               => (int) round( $product['units'] ),
				'revenue'             => round( $product['revenue'], 2 ),
				'cost'                => round( $product['cost'], 2 ),
				'profit'              => round( $product['profit'], 2 ),
				'margin_pct'          => round( $product['margin_pct'], 1 ),
				// Deprecated in favor of revenue_covered_pct below, kept for
				// back-compat — true iff revenue_covered_pct is (within
				// float rounding of) 100.
				'has_cost'            => $product['has_cost'],
				// What fraction of THIS product's own revenue in the period
				// is backed by a known cost — same metric, same name, and
				// the same severity tiers as the period-level
				// cost_coverage.revenue_covered_pct (see
				// CostCoverageNotice.jsx's getTier()), just scoped to one
				// row instead of the whole period. Lets ProductTable tell
				// "no cost anywhere" (0%) apart from "cost known for most
				// of what sold" (e.g. 90%) instead of collapsing both into
				// the same has_cost:false.
				'revenue_covered_pct' => round( $product['revenue_covered_pct'], 1 ),
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
