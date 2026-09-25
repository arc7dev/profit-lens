<?php
/**
 * Tests for ProfitLens_Access::current_user_has_access() — the one gate
 * both the admin menu (class-admin.php) and every REST route
 * (class-rest-controller.php) run before anything else. See that
 * class's own docblock for why profit_lens_allowed_users is a shared
 * option, not a filter: Pro (profit-lens-pro) is the only thing that
 * ever writes it, but this plugin has no dependency on Pro being
 * active/loaded to read it — these tests never touch Pro at all,
 * confirming that holds.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

class Test_ProfitLens_Access extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'profit_lens_allowed_users' );
		parent::tear_down();
	}

	/**
	 * `manage_woocommerce` is a capability WooCommerce itself registers
	 * onto the administrator role when it activates — it isn't one WP
	 * core grants to any role on its own. This suite's CI run has no
	 * WooCommerce installed, so an administrator created via the user
	 * factory has every default WP-core capability but NOT this one,
	 * and every test that relies on the "falls back to manage_woocommerce"
	 * behavior needs it granted directly rather than assumed from a role.
	 * Confirmed this is the actual cause of the 3 CI failures reported
	 * (not a stale/zero current-user-ID issue): each failure traces to
	 * either a direct current_user_can('manage_woocommerce') assertion or
	 * a call into ProfitLens_Access::current_user_has_access() that falls
	 * through to one — the array-membership tests below (which never call
	 * current_user_can() at all) pass in both environments already.
	 *
	 * @return int New user ID, with manage_woocommerce explicitly granted.
	 */
	private function create_user_with_manage_woocommerce() {
		$user_id = self::factory()->user->create();
		( new WP_User( $user_id ) )->add_cap( 'manage_woocommerce' );

		return $user_id;
	}

	public function test_falls_back_to_manage_woocommerce_when_option_missing() {
		$this->assertFalse( get_option( 'profit_lens_allowed_users' ) );

		$admin_id = $this->create_user_with_manage_woocommerce();
		wp_set_current_user( $admin_id );
		$this->assertTrue( ProfitLens_Access::current_user_has_access() );

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( ProfitLens_Access::current_user_has_access() );
	}

	public function test_true_when_current_user_id_is_in_the_allowed_list() {
		$allowed_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'profit_lens_allowed_users', array( $allowed_id ) );

		wp_set_current_user( $allowed_id );

		$this->assertTrue( ProfitLens_Access::current_user_has_access() );
	}

	/**
	 * The whole point of the option: an administrator who'd otherwise
	 * pass manage_woocommerce is still denied once an allowlist exists
	 * and they're not on it.
	 */
	public function test_false_when_option_exists_and_current_user_is_not_in_it() {
		$allowed_id     = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$not_allowed_id = $this->create_user_with_manage_woocommerce();
		update_option( 'profit_lens_allowed_users', array( $allowed_id ) );

		wp_set_current_user( $not_allowed_id );

		$this->assertTrue( current_user_can( 'manage_woocommerce' ) );
		$this->assertFalse( ProfitLens_Access::current_user_has_access() );
	}

	/**
	 * Safety net: an empty list must never lock every admin out of the
	 * plugin that manages the list in the first place.
	 */
	public function test_falls_back_to_manage_woocommerce_when_list_is_empty() {
		update_option( 'profit_lens_allowed_users', array() );

		$admin_id = $this->create_user_with_manage_woocommerce();
		wp_set_current_user( $admin_id );

		$this->assertTrue( ProfitLens_Access::current_user_has_access() );
	}

	/**
	 * get_settings_users()/save_settings_users() (profit-lens-pro) store
	 * IDs already cast with intval(); a store manually edited via wp-cli
	 * or a direct DB edit might not be. Confirmed rather than assumed
	 * that string IDs still match.
	 */
	public function test_matches_string_ids_in_the_stored_option() {
		$allowed_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'profit_lens_allowed_users', array( (string) $allowed_id ) );

		wp_set_current_user( $allowed_id );

		$this->assertTrue( ProfitLens_Access::current_user_has_access() );
	}
}
