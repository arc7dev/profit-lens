<?php
/**
 * `wp profitlens verify` — sanity-checks the calculation engine against a
 * direct, independent CRUD summation of real orders. General-purpose (runs
 * against any store's real order data, no dependency on profitlens-seeder
 * or its meta keys unless --exclude-meta-key is passed) — useful as a
 * standalone diagnostic, not only for this project's local dataset.
 *
 * Two independent computations, deliberately not sharing code with the
 * engine, so this is a real cross-check rather than the engine validating
 * itself:
 *   1. A per-status breakdown (order count, order total, shipping, tax,
 *      discounts) computed directly via WC_Order getters.
 *   2. Per ORDER (not just in aggregate), the engine's revenue for a
 *      completed/processing order compared against that same order's own
 *      (total - tax).
 *
 * Why per-order, not one aggregate number: an early version of this
 * command compared only the aggregate totals against a tolerance scaled by
 * order count ("rounding noise, proportional to volume"). It wasn't —
 * the first real discrepancy found this way was $40.00 in two completely
 * different order sets (a real accumulated-rounding delta would not repeat
 * exactly), and turned out to be 4 specific orders off by exactly $10.00
 * each, traced to a source-data defect (see profitlens-seeder's
 * step_orders() for the fix and the full story). A tolerance formula would
 * have hidden that forever behind a passing check. Comparing every order
 * individually means a real mismatch always names the exact order(s)
 * responsible instead of leaving a mystery delta to hunt down by hand —
 * and the aggregate tolerance is gone in favor of a fixed cent-level
 * epsilon (self::PER_ORDER_EPSILON) that only accounts for float rounding
 * within a single order's own arithmetic, never for volume.
 *
 * Only registered under WP-CLI; never loaded on a normal request.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_CLI_Verify {

	/**
	 * Cent-level float-rounding allowance for a SINGLE order's own
	 * arithmetic — not a tolerance that scales with how many orders are
	 * being checked. Any order whose diff exceeds this is a named,
	 * reported mismatch, never silently absorbed.
	 */
	const PER_ORDER_EPSILON = 0.01;

	/**
	 * Registers this as a subcommand of the `profitlens` namespace,
	 * rather than registering a whole `profitlens` command class — so it
	 * coexists with profitlens-seeder's `wp profitlens import`/`cleanup`
	 * (a different, dev-only plugin that already owns that class-level
	 * registration). Verified empirically: WP-CLI lets independent
	 * plugins each add their own subcommands under the same top-level
	 * namespace this way, as long as the subcommand names themselves
	 * don't collide.
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'profitlens verify', array( __CLASS__, 'verify' ) );
	}

	/**
	 * Compares the engine's revenue calculation against a direct CRUD sum
	 * for the same orders.
	 *
	 * ## OPTIONS
	 *
	 * [--after=<date>]
	 * : Start of the range (Y-m-d). Defaults to 12 months ago.
	 *
	 * [--before=<date>]
	 * : End of the range (Y-m-d). Defaults to today.
	 *
	 * [--exclude-meta-key=<key>]
	 * : Skip orders carrying this meta key (e.g. a seeder's synthetic-order
	 * marker). Off by default — most stores have no such marker and no
	 * reason to exclude anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp profitlens verify
	 *     wp profitlens verify --after=2025-08-13 --before=2026-07-08 --exclude-meta-key=_profitlens_synthetic
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function verify( $args, $assoc_args ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$after            = new DateTime( $assoc_args['after'] ?? '-12 months' );
		$before           = new DateTime( $assoc_args['before'] ?? 'today' );
		$exclude_meta_key = $assoc_args['exclude-meta-key'] ?? null;

		WP_CLI::log( sprintf( 'Range: %s to %s', $after->format( 'Y-m-d' ), $before->format( 'Y-m-d' ) ) );

		if ( $exclude_meta_key ) {
			WP_CLI::log( "Excluding orders with meta key '$exclude_meta_key'." );
		}

		$breakdown = $this->direct_breakdown_by_status( $after, $before, $exclude_meta_key );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '%-12s %8s %12s %10s %10s %10s', 'status', 'orders', 'total', 'shipping', 'tax', 'discounts' ) );

		foreach ( $breakdown as $status => $row ) {
			WP_CLI::log(
				sprintf(
					'%-12s %8d %12.2f %10.2f %10.2f %10.2f',
					str_replace( 'wc-', '', $status ),
					$row['count'],
					$row['total'],
					$row['shipping'],
					$row['tax'],
					$row['discount']
				)
			);
		}

		$counted_statuses = array( 'wc-completed', 'wc-processing' );
		$engine           = ProfitLens_Profit_Engine::create_default();
		$engine_orders    = $this->get_orders( $after, $before, $counted_statuses, $exclude_meta_key );

		$engine_revenue = 0.0;
		$direct_revenue = 0.0;
		$mismatches     = array();

		foreach ( $engine_orders as $order ) {
			$engine_rev = round( $engine->get_order_revenue( $order ), 2 );
			// Per-order equivalent of "direct CRUD total": the order's own
			// authoritative total, tax excluded (same reason the engine
			// excludes it — it's never the merchant's money).
			$direct = round( (float) $order->get_total() - (float) $order->get_total_tax(), 2 );
			$diff   = round( $engine_rev - $direct, 2 );

			$engine_revenue += $engine_rev;
			$direct_revenue += $direct;

			if ( abs( $diff ) > self::PER_ORDER_EPSILON ) {
				$mismatches[] = array(
					'id'     => $order->get_id(),
					'status' => $order->get_status(),
					'engine' => $engine_rev,
					'direct' => $direct,
					'diff'   => $diff,
				);
			}
		}

		$engine_revenue = round( $engine_revenue, 2 );
		$direct_revenue = round( $direct_revenue, 2 );
		$delta          = round( $engine_revenue - $direct_revenue, 2 );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Engine revenue (completed+processing, product lines + shipping): %.2f', $engine_revenue ) );
		WP_CLI::log( sprintf( 'Direct CRUD equivalent (total - tax, same orders):                %.2f', $direct_revenue ) );

		if ( $mismatches ) {
			WP_CLI::log( '' );
			WP_CLI::log(
				sprintf(
					'%d of %d orders account for the entire %.2f delta — not spread across the rest:',
					count( $mismatches ),
					count( $engine_orders ),
					$delta
				)
			);
			WP_CLI::log( sprintf( '%-10s %-12s %10s %10s %8s', 'order', 'status', 'engine', 'direct', 'diff' ) );

			foreach ( $mismatches as $m ) {
				WP_CLI::log(
					sprintf(
						'%-10d %-12s %10.2f %10.2f %+8.2f',
						$m['id'],
						$m['status'],
						$m['engine'],
						$m['direct'],
						$m['diff']
					)
				);
			}

			WP_CLI::error(
				'Revenue mismatch traced to the specific order(s) listed above — not rounding noise. ' .
				'Investigate each one (this is exactly how the $10-per-order coupon data defect fixed in ' .
				'profitlens-seeder was found) before trusting the numbers.'
			);
		}

		WP_CLI::success( sprintf( 'Engine revenue matches (order total - tax) exactly for all %d counted orders (delta %.2f).', count( $engine_orders ), $delta ) );
	}

	/**
	 * @param DateTime    $after
	 * @param DateTime    $before
	 * @param string|null $exclude_meta_key
	 * @return array<string,array{count:int,total:float,shipping:float,tax:float,discount:float}>
	 */
	private function direct_breakdown_by_status( DateTime $after, DateTime $before, $exclude_meta_key ) {
		$orders = $this->get_orders( $after, $before, null, $exclude_meta_key );
		$agg    = array();

		foreach ( $orders as $order ) {
			$status = $order->get_status();
			// get_status() returns without the 'wc-' prefix; keep the
			// prefixed form as the array key so it lines up with what
			// get_counted_orders() below (and the engine) use.
			$status = 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;

			if ( ! isset( $agg[ $status ] ) ) {
				$agg[ $status ] = array(
					'count'    => 0,
					'total'    => 0.0,
					'shipping' => 0.0,
					'tax'      => 0.0,
					'discount' => 0.0,
				);
			}

			++$agg[ $status ]['count'];
			$agg[ $status ]['total']    += (float) $order->get_total();
			$agg[ $status ]['shipping'] += (float) $order->get_shipping_total();
			$agg[ $status ]['tax']      += (float) $order->get_total_tax();
			$agg[ $status ]['discount'] += (float) $order->get_total_discount();
		}

		ksort( $agg );

		return $agg;
	}

	/**
	 * @param DateTime          $after
	 * @param DateTime          $before
	 * @param array|null        $statuses
	 * @param string|null       $exclude_meta_key
	 * @return WC_Order[]
	 */
	private function get_orders( DateTime $after, DateTime $before, $statuses, $exclude_meta_key ) {
		$args = array(
			'type'         => 'shop_order',
			'date_created' => $after->format( 'Y-m-d' ) . '...' . $before->format( 'Y-m-d' ),
			'limit'        => -1,
			'return'       => 'objects',
		);

		if ( $statuses ) {
			$args['status'] = $statuses;
		}

		if ( $exclude_meta_key ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_key']     = $exclude_meta_key;
			$args['meta_compare'] = 'NOT EXISTS';
		}

		return wc_get_orders( $args );
	}
}
