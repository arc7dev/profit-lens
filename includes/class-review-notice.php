<?php
/**
 * WordPress.org Guideline-compliant review request: only on the Profit
 * Lens screen (never a global admin notice), dismissible, no incentive,
 * no filtering by sentiment — see this file's own should_show() for the
 * three conditions (14 days since first activation, real dashboard data,
 * not already dismissed/snoozed).
 *
 * State lives in two places, both read/written only by this class:
 * - `self::OPTION_FIRST_ACTIVATED` (site option, set once, never
 *   overwritten) — when the 14-day clock started.
 * - Per-user meta (`self::META_DISMISSED` / `self::META_SNOOZED_UNTIL`)
 *   — each WordPress user who manages this store gets asked and can
 *   dismiss independently; one admin closing it doesn't hide it from
 *   another.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Review_Notice {

	const OPTION_FIRST_ACTIVATED = 'profitlens_first_activated_at';
	const META_DISMISSED         = 'profitlens_review_notice_dismissed';
	const META_SNOOZED_UNTIL     = 'profitlens_review_notice_snoozed_until';

	const DAYS_BEFORE_SHOWING = 14;
	const SNOOZE_DAYS         = 30;

	const REST_ROUTE = '/review-notice';

	const REVIEW_URL = 'https://wordpress.org/support/plugin/profit-lens/reviews/#new-post';

	public function __construct() {
		// Fallback for installs that already existed before this feature
		// shipped: register_activation_hook() (ProfitLens_Plugin::activate(),
		// which also calls record_first_activation()) only fires on a fresh
		// activation, never on "the plugin files were updated and WordPress
		// loaded the new version" — an existing install's users would
		// otherwise never get an activation date at all, and should_show()
		// treats a missing date as "can't confirm 14 days passed" (never
		// show), not "show immediately". add_option() below is a no-op if
		// the option already exists, so this costs one cheap autoloaded
		// option read per admin request and nothing once it's set.
		add_action( 'admin_init', array( $this, 'maybe_backfill_first_activation' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Called from ProfitLens_Plugin::activate() — the real, intended path
	 * for a fresh install. add_option() (not update_option()) so a
	 * plugin deactivate/reactivate cycle on an existing install doesn't
	 * restart the 14-day clock.
	 */
	public static function record_first_activation() {
		add_option( self::OPTION_FIRST_ACTIVATED, time() );
	}

	/**
	 * See this method's own call site in __construct() for why this
	 * exists alongside record_first_activation().
	 */
	public function maybe_backfill_first_activation() {
		add_option( self::OPTION_FIRST_ACTIVATED, time() );
	}

	/**
	 * @return int|null Unix timestamp, or null if never recorded (should
	 *                   only happen in the brief window before this
	 *                   class's own admin_init hook has fired once).
	 */
	public static function get_first_activated_at() {
		$value = get_option( self::OPTION_FIRST_ACTIVATED, null );

		return null === $value ? null : (int) $value;
	}

	/**
	 * "The dashboard has data" for the purposes of asking for a review —
	 * deliberately NOT the same thing as a live /summary request's
	 * status==='ready' for a specific range (this runs on every admin
	 * page load via window.profitLensData, not scoped to whatever range
	 * the merchant happens to have selected). Two cheap, independent,
	 * all-time checks instead, matching exactly what flips a real
	 * /summary response from 'empty' to 'ready'
	 * (class-rest-controller.php's own branch):
	 * 1. At least one counted order exists, ever (same Completed/
	 *    Processing definition used everywhere else in this plugin) —
	 *    a single indexed, limit-1 query.
	 * 2. The catalog has at least one product with a known cost —
	 *    reuses the cost source's own optimized raw-SQL coverage check
	 *    (ProfitLens_Cost_Source_Cogs::get_catalog_coverage(), ~65ms
	 *    measured, see that method's own docblock) rather than running
	 *    the full per-order aggregate() just to decide whether to show
	 *    a dismissible notice.
	 *
	 * @return bool
	 */
	public static function has_dashboard_data() {
		$has_orders = ! empty(
			wc_get_orders(
				array(
					'type'   => 'shop_order',
					'status' => array( 'wc-completed', 'wc-processing' ),
					'limit'  => 1,
					'return' => 'ids',
				)
			)
		);

		if ( ! $has_orders ) {
			return false;
		}

		$cost_source = ProfitLens_Profit_Engine::create_default()
			->get_product_cost_component()
			->get_cost_source();

		// get_catalog_coverage() is an optional, duck-typed method (not
		// part of the Cost_Source interface) — see that interface's own
		// docblock on why. Every source this plugin ships today
		// implements it; a hypothetical future one that doesn't just
		// can't satisfy this check, same as a store with zero costed
		// products.
		if ( ! method_exists( $cost_source, 'get_catalog_coverage' ) ) {
			return false;
		}

		$coverage = $cost_source->get_catalog_coverage();

		return ( $coverage['with_cost'] ?? 0 ) > 0;
	}

	/**
	 * Pure function of every input the three display conditions depend
	 * on — unit-testable without touching options, user meta, or the
	 * database, same reasoning as ProfitLens_Assets::build_pro_status().
	 *
	 * @param int|null $first_activated_at Unix timestamp, or null (never recorded yet — treated as "not shown").
	 * @param bool     $has_dashboard_data
	 * @param bool     $dismissed           "Don't ask again" or "Leave a review" already happened.
	 * @param int|null $snoozed_until       Unix timestamp "Maybe later" expires at, or null/past if not snoozed.
	 * @param int      $now                 Unix timestamp — passed in, not time(), so a test can fix it.
	 * @param bool     $force_14_days       PROFITLENS_DEV_FORCE_REVIEW_NOTICE — bypasses ONLY the 14-day
	 *                                      gate; dismissed/snoozed/has-data still apply, so a real dismiss
	 *                                      action is still testable against a forced-eligible state.
	 * @return bool
	 */
	public static function should_show( $first_activated_at, $has_dashboard_data, $dismissed, $snoozed_until, $now, $force_14_days = false ) {
		if ( $dismissed ) {
			return false;
		}

		if ( $snoozed_until && $now < $snoozed_until ) {
			return false;
		}

		if ( ! $has_dashboard_data ) {
			return false;
		}

		if ( $force_14_days ) {
			return true;
		}

		if ( null === $first_activated_at ) {
			return false;
		}

		return ( $now - $first_activated_at ) >= self::DAYS_BEFORE_SHOWING * DAY_IN_SECONDS;
	}

	/**
	 * What ProfitLens_Assets::enqueue() hands to
	 * window.profitLensData.reviewNotice — the frontend never computes
	 * should_show() itself or reads options/user meta directly, it only
	 * renders what this already decided.
	 *
	 * @return array{show:bool,reviewUrl:string}
	 */
	public static function get_state_for_current_user() {
		$user_id = get_current_user_id();

		$show = self::should_show(
			self::get_first_activated_at(),
			self::has_dashboard_data(),
			(bool) get_user_meta( $user_id, self::META_DISMISSED, true ),
			self::get_snoozed_until( $user_id ),
			time(),
			defined( 'PROFITLENS_DEV_FORCE_REVIEW_NOTICE' ) && PROFITLENS_DEV_FORCE_REVIEW_NOTICE
		);

		return array(
			'show'      => $show,
			'reviewUrl' => self::REVIEW_URL,
		);
	}

	/**
	 * @param int $user_id
	 * @return int|null
	 */
	private static function get_snoozed_until( $user_id ) {
		$value = get_user_meta( $user_id, self::META_SNOOZED_UNTIL, true );

		return '' === $value ? null : (int) $value;
	}

	public function register_routes() {
		register_rest_route(
			ProfitLens_REST_Controller::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_action' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'enum'     => array( 'later', 'dismiss', 'reviewed' ),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * manage_woocommerce — same capability every other route/menu item in
	 * this plugin gates on (ProfitLens_REST_Controller::check_permission(),
	 * ProfitLens_Admin::register_menu()). The nonce itself is checked
	 * automatically by WP_REST_Server before this even runs, via the
	 * X-WP-Nonce header @wordpress/api-fetch already attaches (see
	 * class-assets.php's wpApiSettings localization) — no manual
	 * wp_verify_nonce() call needed here, same as every other route in
	 * this plugin.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_action( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$action  = $request->get_param( 'action' );

		if ( 'later' === $action ) {
			update_user_meta( $user_id, self::META_SNOOZED_UNTIL, time() + self::SNOOZE_DAYS * DAY_IN_SECONDS );
		} else {
			// 'dismiss' (Don't ask again) and 'reviewed' (Leave a review
			// was clicked) both mean the same thing from this plugin's
			// side: never show it to this user again. Nothing
			// distinguishes which one happened once stored — there's no
			// reporting surface that would use the difference, and
			// recording it would be tracking review sentiment, which
			// Guideline 5 explicitly prohibits reacting to anyway.
			update_user_meta( $user_id, self::META_DISMISSED, true );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
