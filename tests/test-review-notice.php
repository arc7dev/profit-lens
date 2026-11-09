<?php
/**
 * Tests for ProfitLens_Review_Notice::should_show() — pure function of
 * every input the three WordPress.org Guideline 5 display conditions
 * depend on (14 days since first activation, real dashboard data, not
 * already dismissed/snoozed). See that method's own docblock for why
 * it's tested this way rather than against real options/user meta, same
 * reasoning as ProfitLens_Assets::build_pro_status().
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

class Test_ProfitLens_Review_Notice extends WP_UnitTestCase {

	const NOW            = 2_000_000_000; // Fixed "now" for every test (2033-05-18 — value itself is arbitrary, only used for relative math below).
	const FOURTEEN_DAYS   = 14 * DAY_IN_SECONDS;
	const ACTIVATED_READY = self::NOW - self::FOURTEEN_DAYS; // Exactly 14 days ago.

	public function test_shows_when_every_condition_is_met() {
		$this->assertTrue(
			ProfitLens_Review_Notice::should_show( self::ACTIVATED_READY, true, false, null, self::NOW )
		);
	}

	public function test_hidden_before_14_days_since_first_activation() {
		$activated_yesterday = self::NOW - DAY_IN_SECONDS;

		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( $activated_yesterday, true, false, null, self::NOW )
		);
	}

	public function test_hidden_when_first_activation_was_never_recorded() {
		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( null, true, false, null, self::NOW )
		);
	}

	public function test_hidden_without_dashboard_data() {
		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( self::ACTIVATED_READY, false, false, null, self::NOW )
		);
	}

	public function test_hidden_when_snoozed_via_maybe_later() {
		$snoozed_until = self::NOW + DAY_IN_SECONDS; // "Maybe later", still in the future.

		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( self::ACTIVATED_READY, true, false, $snoozed_until, self::NOW )
		);
	}

	public function test_shows_again_once_a_snooze_has_expired() {
		$snoozed_until = self::NOW - 1; // "Maybe later" 30 days ago, already elapsed.

		$this->assertTrue(
			ProfitLens_Review_Notice::should_show( self::ACTIVATED_READY, true, false, $snoozed_until, self::NOW )
		);
	}

	/**
	 * Covers both real triggers for $dismissed=true — "Don't ask again"
	 * and having already clicked "Leave a review" — which this plugin
	 * deliberately stores as the same flag (see
	 * ProfitLens_Review_Notice::handle_action()'s own comment on why
	 * distinguishing them isn't useful and would edge toward tracking
	 * review sentiment, which Guideline 5 prohibits reacting to).
	 */
	public function test_hidden_once_dismissed_or_already_reviewed() {
		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( self::ACTIVATED_READY, true, true, null, self::NOW )
		);
	}

	/**
	 * PROFITLENS_DEV_FORCE_REVIEW_NOTICE bypasses ONLY the 14-day gate —
	 * dismissed/snoozed/no-data still have to hold for a real dismiss
	 * action to be testable against a forced-eligible notice.
	 */
	public function test_dev_force_flag_bypasses_only_the_14_day_gate() {
		$activated_today = self::NOW;

		$this->assertTrue(
			ProfitLens_Review_Notice::should_show( $activated_today, true, false, null, self::NOW, true )
		);
		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( $activated_today, false, false, null, self::NOW, true ),
			'force_14_days must not bypass the has_dashboard_data check.'
		);
		$this->assertFalse(
			ProfitLens_Review_Notice::should_show( $activated_today, true, true, null, self::NOW, true ),
			'force_14_days must not bypass the dismissed check.'
		);
	}
}
