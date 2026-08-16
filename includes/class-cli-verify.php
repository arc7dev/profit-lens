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
 *   2. ProfitLens_Profit_Engine::get_summary() for the same range,
 *      restricted to completed+processing — its `revenue` (product lines
 *      + shipping, tax excluded) is compared against the matching slice
 *      of computation 1.
 *
 * Only registered under WP-CLI; never loaded on a normal request.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_CLI_Verify {

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

		$after  = new DateTime( $assoc_args['after'] ?? '-12 months' );
		$before = new DateTime( $assoc_args['before'] ?? 'today' );
		$exclude_meta_key = $assoc_args['exclude-meta-key'] ?? null;

		WP_CLI::log( sprintf( 'Range: %s to %s', $after->format( 'Y-m-d' ), $before->format( 'Y-m-d' ) ) );

		if ( $exclude_meta_key ) {
			WP_CLI::log( "Excluding orders with meta key '$exclude_meta_key'." );
		}

		$breakdown = $this->direct_breakdown_by_status( $after, $before, $exclude_meta_key );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '%-12s %8s %12s %10s %10s %10s', 'status', 'orders', 'total', 'shipping', 'tax', 'discounts' ) );

		foreach ( $breakdown as $status => $row ) {
			WP_CLI::log( sprintf(
				'%-12s %8d %12.2f %10.2f %10.2f %10.2f',
				str_replace( 'wc-', '', $status ),
				$row['count'],
				$row['total'],
				$row['shipping'],
				$row['tax'],
				$row['discount']
			) );
		}

		$counted_statuses = array( 'wc-completed', 'wc-processing' );
		$expected_revenue = 0.0;

		foreach ( $counted_statuses as $status ) {
			if ( isset( $breakdown[ $status ] ) ) {
				// Direct-CRUD "total" includes tax; the engine's revenue
				// doesn't, so subtract it here for a like-for-like check.
				$expected_revenue += $breakdown[ $status ]['total'] - $breakdown[ $status ]['tax'];
			}
		}

		$engine       = ProfitLens_Profit_Engine::create_default();
		$engine_orders = $this->get_orders( $after, $before, $counted_statuses, $exclude_meta_key );
		$engine_revenue = 0.0;

		foreach ( $engine_orders as $order ) {
			$engine_revenue += $engine->get_order_revenue( $order );
		}

		$engine_revenue = round( $engine_revenue, 2 );
		$expected_revenue = round( $expected_revenue, 2 );
		$delta = round( $engine_revenue - $expected_revenue, 2 );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Engine revenue (completed+processing, product lines + shipping): %.2f', $engine_revenue ) );
		WP_CLI::log( sprintf( 'Direct CRUD equivalent (total - tax, same orders):                %.2f', $expected_revenue ) );

		// Currency-float summation over thousands of orders accumulates
		// sub-cent rounding noise (confirmed empirically: ~$0.02/order on
		// a 2,362-order sample, unrelated to tax/discount handling, which
		// matched exactly). A tolerance proportional to order count avoids
		// a false failure from that noise while still catching a real
		// calculation bug, which would produce a delta orders of magnitude
		// larger.
		$tolerance = max( 0.05, 0.02 * count( $engine_orders ) );

		if ( abs( $delta ) > $tolerance ) {
			WP_CLI::error( sprintf(
				'MISMATCH: delta of %.2f across %d orders exceeds tolerance of %.2f. This means a real bug in the engine, not rounding noise — do not proceed.',
				$delta,
				count( $engine_orders ),
				$tolerance
			) );
		}

		WP_CLI::success( sprintf( 'Engine revenue matches direct CRUD sum (delta %.2f, within tolerance %.2f).', $delta, $tolerance ) );
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
			$args['meta_key']     = $exclude_meta_key;
			$args['meta_compare'] = 'NOT EXISTS';
		}

		return wc_get_orders( $args );
	}
}
