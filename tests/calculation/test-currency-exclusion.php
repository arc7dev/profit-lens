<?php
/**
 * Issue #42: orders in a currency other than the store's base currency
 * (get_woocommerce_currency()) are excluded from every total the engine
 * produces, and reported separately via currency_exclusions — see
 * ProfitLens_Profit_Engine::aggregate()'s docblock for why WooCommerce
 * gives no way to convert them instead (no per-order exchange rate is
 * stored).
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Currency_Exclusion extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Profit_Engine */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = ProfitLens_Profit_Engine::create_default();
	}

	public function tear_down() {
		update_option( 'woocommerce_currency', 'USD' );
		parent::tear_down();
	}

	/**
	 * @return array{0:DateTime,1:DateTime}
	 */
	private function range() {
		return array( new DateTime( '-7 days' ), new DateTime( 'tomorrow' ) );
	}

	/**
	 * Mixed base-currency and foreign-currency orders: every KPI/chart/
	 * products total reflects only the base-currency order, and the
	 * foreign one is reported in currency_exclusions instead of silently
	 * dropped or silently mixed in.
	 */
	public function test_mixed_currency_orders_are_excluded_from_totals() {
		update_option( 'woocommerce_currency', 'USD' );
		$product = $this->create_product( 5.0, 20.0 );

		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed'
		);
		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 30.0 ) ),
			'completed',
			array( 'currency' => 'EUR' )
		);

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 20.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 1, $summary['kpis']['revenue']['orders_count'] );

		$this->assertSame( 'USD', $summary['currency_exclusions']['base_currency'] );
		$this->assertCount( 1, $summary['currency_exclusions']['items'] );
		$this->assertSame( 'EUR', $summary['currency_exclusions']['items'][0]['currency'] );
		$this->assertSame( 1, $summary['currency_exclusions']['items'][0]['orders'] );
		$this->assertEquals( 30.0, $summary['currency_exclusions']['items'][0]['revenue'] );
	}

	/**
	 * Every counted order in the range is foreign-currency: the ready
	 * response still comes back (status isn't 'empty' — that's about
	 * COGS, not this), but orders_count/revenue read 0 while
	 * currency_exclusions explains why. Dashboard.jsx/NoOrdersState's
	 * job (not this test) is to not present that 0 as "nothing sold".
	 */
	public function test_all_orders_in_another_currency_are_excluded() {
		update_option( 'woocommerce_currency', 'USD' );
		$product = $this->create_product( 5.0, 20.0 );

		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed',
			array( 'currency' => 'EUR' )
		);

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 0.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 0, $summary['kpis']['revenue']['orders_count'] );
		$this->assertEquals( 0.0, $summary['kpis']['net_profit']['amount'] );

		$this->assertCount( 1, $summary['currency_exclusions']['items'] );
		$this->assertSame( 1, $summary['currency_exclusions']['items'][0]['orders'] );
		$this->assertEquals( 20.0, $summary['currency_exclusions']['items'][0]['revenue'] );
	}

	/**
	 * Regression: a dataset with no foreign-currency orders at all must
	 * produce identical totals to before this feature existed, and an
	 * explicitly empty (not absent, not null) currency_exclusions.items.
	 */
	public function test_no_foreign_currency_orders_leaves_totals_unchanged() {
		update_option( 'woocommerce_currency', 'USD' );
		$product = $this->create_product( 5.0, 20.0 );

		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed'
		);

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 20.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 1, $summary['kpis']['revenue']['orders_count'] );
		$this->assertSame( 'USD', $summary['currency_exclusions']['base_currency'] );
		$this->assertSame( array(), $summary['currency_exclusions']['items'] );
	}

	/**
	 * A refund against a foreign-currency order must not reach
	 * total_costs.refunds (or anything else) — it's never read at all,
	 * because ProfitLens_Cost_Component_Refunds::calculate() only runs
	 * for orders that pass the currency filter in the first place (see
	 * aggregate()'s own docblock on this). The exclusion's own reported
	 * revenue is get_order_revenue()'s figure (line items + shipping,
	 * same definition the base-currency revenue KPI uses) — NOT net of
	 * the refund, matching how the base-currency path also doesn't net
	 * refunds into "revenue" (refunds are their own, separate
	 * cost_breakdown bucket there).
	 */
	public function test_refund_of_an_excluded_order_is_also_excluded() {
		update_option( 'woocommerce_currency', 'USD' );
		$product = $this->create_product( 5.0, 20.0 );

		$order = $this->create_order(
			array( array( 'product' => $product, 'qty' => 2, 'total' => 40.0 ) ),
			'completed',
			array( 'currency' => 'EUR' )
		);

		$items = $order->get_items();
		$item  = reset( $items );
		$this->refund_item( $order, $item->get_id(), 10.0, 0 );

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 0.0, $summary['kpis']['total_costs']['amount'] );
		$this->assertEquals( 0.0, $summary['kpis']['revenue']['amount'] );

		$this->assertCount( 1, $summary['currency_exclusions']['items'] );
		$this->assertEquals( 40.0, $summary['currency_exclusions']['items'][0]['revenue'] );
	}

	/**
	 * A store that changed its base currency (not a multi-currency
	 * plugin): old orders still say the OLD currency — WooCommerce
	 * never rewrites a placed order's own currency when the store-wide
	 * setting changes — so they're excluded by the exact same mechanism
	 * as a genuinely mixed-currency order, even though the real-world
	 * cause here is completely different. Confirms the exclusion logic
	 * (and, separately, the UI copy) doesn't assume a multi-currency
	 * plugin is the only possible cause.
	 */
	public function test_orders_predating_a_base_currency_change_are_excluded() {
		$product = $this->create_product( 5.0, 20.0 );

		// Store used to run in USD...
		update_option( 'woocommerce_currency', 'USD' );
		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed',
			array( 'currency' => 'USD' )
		);

		// ...then switched its base currency to EUR. New orders are EUR;
		// the old USD order's own currency doesn't change retroactively.
		update_option( 'woocommerce_currency', 'EUR' );
		$this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 25.0 ) ),
			'completed',
			array( 'currency' => 'EUR' )
		);

		list( $after, $before ) = $this->range();
		$summary = $this->engine->get_summary( $after, $before );

		$this->assertEquals( 25.0, $summary['kpis']['revenue']['amount'] );
		$this->assertSame( 1, $summary['kpis']['revenue']['orders_count'] );

		$this->assertSame( 'EUR', $summary['currency_exclusions']['base_currency'] );
		$this->assertCount( 1, $summary['currency_exclusions']['items'] );
		$this->assertSame( 'USD', $summary['currency_exclusions']['items'][0]['currency'] );
		$this->assertSame( 1, $summary['currency_exclusions']['items'][0]['orders'] );
	}
}
