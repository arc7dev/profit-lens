<?php
/**
 * Whether the current user is allowed to see Profit Lens at all — the
 * one check both the admin menu (class-admin.php) and every REST route
 * (class-rest-controller.php) run before anything else.
 *
 * `profit_lens_allowed_users` is a shared wp_options key, not a filter:
 * Free reads it, only Pro's User Control tab (profit-lens-pro's
 * class-rest-controller-pro.php) ever writes it. A filter would need
 * Pro's own callback registered on the exact same request that's
 * rendering Free's menu or answering a REST call — true whenever Pro is
 * active, but this option has to keep governing access even on a
 * request where Pro's code never runs at all (Pro temporarily
 * deactivated, mid-upgrade, etc.), the same reason
 * profitlens_has_ad_spend_data/profitlens_csv_import_url use a filter
 * (those are read once per page load, always alongside Pro's own active
 * code) while this uses a plain option instead.
 *
 * A missing option (Pro never installed, or installed but its allowlist
 * never configured — Pro's own activation hook seeds it immediately, so
 * "missing" in practice mostly means Pro isn't active right now) falls
 * back to exactly what governed access before this feature existed:
 * any manage_woocommerce user. An explicitly empty list falls back the
 * same way, on purpose — nothing about "who may configure this" should
 * be able to lock every admin out of the plugin that manages it.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Access {

	/**
	 * @return bool
	 */
	public static function current_user_has_access() {
		$allowed = get_option( 'profit_lens_allowed_users', false );

		if ( false === $allowed ) {
			return current_user_can( 'manage_woocommerce' );
		}

		$allowed = array_map( 'intval', (array) $allowed );

		if ( empty( $allowed ) ) {
			return current_user_can( 'manage_woocommerce' );
		}

		return in_array( get_current_user_id(), $allowed, true );
	}
}
