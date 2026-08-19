<?php
/**
 * Profit Lens REST endpoint.
 *
 * Calls ProfitLens_Profit_Engine for every field — no mock data. The
 * response contract is the same one the mock scaffold shipped (see the
 * plugin's REST contract notes), with two deliberate additions: each
 * cost_breakdown entry now carries a `note` field (see
 * ProfitLens_Cost_Component::get_note()) so the UI can surface a caveat
 * like shipping's "this is what was collected, not the real logistics
 * cost" without a separate request; and each `products` entry now carries
 * `revenue_covered_pct` (what fraction of THAT product's own revenue this
 * period is backed by a known cost — same metric/name/severity tiers as
 * the period-level cost_coverage.revenue_covered_pct, just scoped to one
 * row) plus the older `has_cost` boolean it supersedes (true iff
 * revenue_covered_pct is ~100 — kept for back-compat, new code should read
 * the percentage instead) so ProductTable can distinguish "no cost
 * anywhere" from "cost known for most of what sold" instead of collapsing
 * both into the same flat chip.
 *
 * QA override without URL parameters (debug querystring params tend to
 * survive into production): use the `profitlens_demo_status` /
 * `profitlens_demo_error` filters, e.g. from a local mu-plugin:
 *
 *     add_filter( 'profitlens_demo_status', fn() => 'empty' );
 *
 * These still take priority over the real calculation below — useful for
 * screenshotting the empty/error states without having to actually strip a
 * store's cost data or take WooCommerce down.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_REST_Controller {

	const REST_NAMESPACE = 'profit-lens/v1';
	const ROUTE_SUMMARY  = '/summary';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_SUMMARY,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'range'  => array(
						'type'    => 'string',
						'enum'    => array( '7d', '30d', 'month', 'custom' ),
						'default' => '30d',
					),
					'after'  => array(
						'type'   => 'string',
						'format' => 'date',
					),
					'before' => array(
						'type'   => 'string',
						'format' => 'date',
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_summary( WP_REST_Request $request ) {
		$demo_status = apply_filters( 'profitlens_demo_status', null, $request );

		if ( 'error' === $demo_status ) {
			return rest_ensure_response( $this->build_error_response( $request, null ) );
		}

		$range = $this->get_range_bounds( $request );

		if ( 'empty' === $demo_status ) {
			return rest_ensure_response( $this->build_empty_response( $range ) );
		}

		if ( 'ready' !== $demo_status && null !== $demo_status ) {
			// Unrecognized override value — ignore it and fall through to
			// the real calculation rather than silently doing nothing.
			$demo_status = null;
		}

		// WooCommerce inactive: in practice ProfitLens_Plugin never
		// instantiates this controller (and therefore never registers this
		// route) unless WooCommerce is active, so this route shouldn't be
		// reachable in that state — but the check stays here anyway,
		// defensively, rather than relying on that being true forever.
		if ( null === $demo_status && ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( $this->build_error_response( $request, null ) );
		}

		try {
			$summary = ProfitLens_Profit_Engine::create_default()->get_summary( $range['after'], $range['before'] );
		} catch ( \Throwable $e ) {
			// Never surface $e->getMessage()/getTrace() to the client — it
			// can leak file paths, table names, or other internals. Log
			// server-side where a store owner or support can find it.
			error_log( sprintf( 'Profit Lens: summary calculation failed for range %s: %s', $range['key'], $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return rest_ensure_response( $this->build_error_response( $request, $e ) );
		}

		// Real empty state: not "no orders in this date range" (a slow week
		// is not an error state), but "no product ANYWHERE in the catalog
		// has a defined cost" — cost_coverage.products_with_cost is
		// catalog-wide, not scoped to the requested range, precisely so
		// this check means what it says regardless of which range triggered
		// it. Below that point, kpis/products/chart would all be either
		// wrong (100% of revenue shown as "profit") or meaningless zeros,
		// so the contract omits them rather than showing a number nobody
		// should trust.
		if ( 0 === $summary['cost_coverage']['products_with_cost'] ) {
			return rest_ensure_response( $this->build_empty_response( $range, $summary['cost_coverage'] ) );
		}

		return rest_ensure_response( $this->build_ready_response( $range, $summary ) );
	}

	/**
	 * @param WP_REST_Request $request
	 * @param \Throwable|null $exception Real cause, logged already —
	 *                                    never included in the response.
	 * @return array
	 */
	private function build_error_response( WP_REST_Request $request, $exception ) {
		$error = apply_filters(
			'profitlens_demo_error',
			array(
				'code'    => 'calculation_failed',
				'message' => __( 'Profit Lens could not calculate profit for this period.', 'profit-lens' ),
			),
			$request
		);

		return array(
			'status' => 'error',
			'error'  => $error,
		);
	}

	/**
	 * @param array      $range         Range config (see get_range_bounds()).
	 * @param array|null $cost_coverage Real catalog-wide coverage, when
	 *                                   known (0 products with cost, but the
	 *                                   catalog itself may not be empty —
	 *                                   showing e.g. "0 of 850 products have
	 *                                   a cost set" is more actionable for
	 *                                   the merchant than hiding the count).
	 *                                   Null only for the demo-filter path,
	 *                                   which has no real catalog to report.
	 * @return array
	 */
	private function build_empty_response( array $range, $cost_coverage = null ) {
		return array(
			'range'          => $range['meta'],
			'status'         => 'empty',
			'kpis'           => null,
			'insight'        => null,
			'cost_coverage'  => $cost_coverage ?? array(
				'products_with_cost'  => 0,
				'products_total'      => 0,
				'pct'                 => 0.0,
				'revenue_covered_pct' => 0.0,
				'revenue_uncovered'   => 0.0,
			),
			'chart'          => array( 'series' => array() ),
			'cost_breakdown' => array(),
			'products'       => array(),
			'products_meta'  => array(
				'total'  => 0,
				'totals' => array(
					'units'      => 0,
					'revenue'    => 0.0,
					'cost'       => 0.0,
					'profit'     => 0.0,
					'margin_pct' => 0.0,
				),
			),
		);
	}

	/**
	 * @param array $range   Range config (see get_range_bounds()).
	 * @param array $summary ProfitLens_Profit_Engine::get_summary() output
	 *                        for this range — kpis.net_profit.change_pct
	 *                        comes in as null; this method fills it in
	 *                        (needs a second, prior-period calculation the
	 *                        engine deliberately doesn't do for itself).
	 * @return array
	 */
	private function build_ready_response( array $range, array $summary ) {
		$summary['kpis']['net_profit']['change_pct'] = $this->calculate_change_pct( $range, $summary['kpis']['net_profit']['amount'] );

		return array_merge(
			array(
				'range'  => $range['meta'],
				'status' => 'ready',
			),
			$summary
		);
	}

	/**
	 * Prior-period comparison: the same number of days immediately
	 * preceding the current range (e.g. a 7-day range compares against the
	 * 7 days right before it, not the same week last month). Omits the
	 * figure entirely — rather than a misleading percentage — when:
	 * - the prior period itself made zero or negative profit ("+400%"
	 *   against a ~$0 or negative base isn't a meaningful comparison), or
	 * - the CURRENT period is exactly $0. That one isn't a division
	 *   concern (the prior period is the divisor, not this one) — it's
	 *   that a $0 current profit is almost always "no orders this period"
	 *   (see build_ready_response()'s own no-orders notice), not a
	 *   genuine break-even result, and computing a real percentage
	 *   against it (e.g. "-100%" for any positive prior profit) implies a
	 *   comparison that didn't really happen. Found live: a period with 0
	 *   orders and a prior period with real profit rendered as
	 *   "▲ -100% vs prior period" — a negative number under an up arrow,
	 *   comparing against nothing.
	 *
	 * Uses ProfitLens_Profit_Engine::get_net_profit() rather than a second
	 * full get_summary() — change_pct only ever needs the prior period's
	 * profit figure, never its chart/products/cost-coverage breakdown.
	 * get_net_profit() skips exactly that unused work (see its docblock);
	 * the per-order aggregation pass itself (the real cost: loading each
	 * order and its line items) still runs, since it's what produces the
	 * profit figure in the first place — there's no way around paying for
	 * that part twice without caching the prior period outright.
	 *
	 * Known, accepted cost: this doubles the per-order aggregation work on
	 * every request. Measured against a real ~250-order/30-days store, cold
	 * process, worst case (real data on both sides of the boundary — the
	 * steady-state case a live store settles into, not just this project's
	 * artificially-quiet "today"): ~378ms, vs. ~271ms for a single period.
	 * Non-caching options were investigated and exhausted first — the
	 * catalog-coverage scan (a genuine architectural defect: fixed,
	 * data-independent overwork on every request) was found and eliminated
	 * via a raw-SQL rewrite; a redundant resolve_line_items() call was
	 * found and removed; this lite path was added specifically to avoid
	 * the unused chart/products/coverage work a second get_summary() would
	 * have paid for. What's left is genuinely proportional to order
	 * volume, not a defect to fix — deliberately accepted as-is rather than
	 * caching or deferring change_pct to a second request, a decision made
	 * explicitly (not by default) once these numbers were in hand.
	 *
	 * @param array $range         Range config (see get_range_bounds()).
	 * @param float $current_profit
	 * @return float|null
	 */
	private function calculate_change_pct( array $range, $current_profit ) {
		$period_days = (int) $range['after']->diff( $range['before'] )->days + 1;

		$prior_before = ( clone $range['after'] )->modify( '-1 day' );
		$prior_after  = ( clone $prior_before )->modify( '-' . ( $period_days - 1 ) . ' days' );

		try {
			$prior_profit = ProfitLens_Profit_Engine::create_default()->get_net_profit( $prior_after, $prior_before );
		} catch ( \Throwable $e ) {
			// A failed prior-period calculation shouldn't take down the
			// current period's otherwise-successful response — change_pct
			// is a nice-to-have on top of it, not a required field.
			error_log( 'Profit Lens: prior-period calculation for change_pct failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		if ( $prior_profit <= 0.0 || 0.0 === $current_profit ) {
			return null;
		}

		return round( ( ( $current_profit - $prior_profit ) / $prior_profit ) * 100, 1 );
	}

	/**
	 * Resolves the request's `range` (and, for 'custom', `after`/`before`)
	 * into concrete date bounds, anchored to the site's configured
	 * timezone (current_datetime()) rather than the server's — a merchant
	 * dashboard's "today" should match what the merchant sees in wp-admin,
	 * not the server's clock.
	 *
	 * @param WP_REST_Request $request
	 * @return array{key:string,after:DateTimeImmutable,before:DateTimeImmutable,meta:array}
	 */
	private function get_range_bounds( WP_REST_Request $request ) {
		$range_key = $request->get_param( 'range' );
		$today     = current_datetime();

		switch ( $range_key ) {
			case '7d':
				$after  = $today->modify( '-6 days' );
				$before = $today;
				break;

			case 'month':
				$after  = $today->modify( 'first day of this month' );
				$before = $today;
				break;

			case 'custom':
				$after  = $this->parse_date_param( $request->get_param( 'after' ) );
				$before = $this->parse_date_param( $request->get_param( 'before' ) );

				// Missing/unparseable custom bounds, or an inverted range:
				// fall back to the 30d default rather than erroring — the
				// REST args schema already validated the *format* of
				// after/before when present, but not that they're both
				// present or in order.
				if ( ! $after || ! $before || $after > $before ) {
					$range_key = '30d';
					$after     = $today->modify( '-29 days' );
					$before    = $today;
				}
				break;

			case '30d':
			default:
				$range_key = '30d';
				$after     = $today->modify( '-29 days' );
				$before    = $today;
				break;
		}

		// Normalize both ends to midnight in the site's timezone, so a
		// range built at 11:59pm and one built at 12:01am on the same
		// calendar day resolve to the exact same bounds.
		$after  = $after->setTime( 0, 0, 0 );
		$before = $before->setTime( 0, 0, 0 );

		return array(
			'key'    => $range_key,
			'after'  => $after,
			'before' => $before,
			'meta'   => array(
				'key'    => $range_key,
				'label'  => $this->format_range_label( $after, $before ),
				'after'  => $after->format( 'Y-m-d' ),
				'before' => $before->format( 'Y-m-d' ),
			),
		);
	}

	/**
	 * @param string|null $value
	 * @return DateTimeImmutable|null
	 */
	private function parse_date_param( $value ) {
		if ( ! $value ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, wp_timezone() );

		return $date ? $date : null;
	}

	/**
	 * "Jul 2 – Jul 8, 2026" (same year), or "Dec 28, 2025 – Jan 3, 2026"
	 * (spanning a year boundary) — the year is only repeated on the start
	 * date when it differs from the end date's.
	 *
	 * @param DateTimeImmutable $after
	 * @param DateTimeImmutable $before
	 * @return string
	 */
	private function format_range_label( DateTimeImmutable $after, DateTimeImmutable $before ) {
		$start_format = $after->format( 'Y' ) === $before->format( 'Y' ) ? 'M j' : 'M j, Y';

		return $after->format( $start_format ) . ' – ' . $before->format( 'M j, Y' );
	}
}
