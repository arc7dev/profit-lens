<?php
/**
 * Cases 25 (partial — $0 order), 26, 28-31.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Component_Gateway_Fees extends ProfitLens_Calculation_Test_Case {

	/** @var ProfitLens_Cost_Component_Gateway_Fees */
	private $component;

	public function setUp(): void {
		parent::setUp();
		$this->component = new ProfitLens_Cost_Component_Gateway_Fees();
	}

	/**
	 * Case 28: default rate (2.9% + $0.30) applies when nothing overrides it.
	 */
	public function test_default_rate() {
		$product = $this->create_product( 10.0, 100.0 );
		$order   = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 100.0 ) ),
			'completed',
			array( 'payment_method' => 'stripe' )
		);

		// 100 * 0.029 + 0.30 = 3.20
		$this->assertEquals( 3.20, $this->component->calculate( $order ) );
	}

	/**
	 * Case 29: a filtered override for a specific gateway is honored.
	 */
	public function test_filter_overrides_rate_for_gateway() {
		$callback = function ( $rate_and_fixed, $gateway_id ) {
			return 'paypal' === $gateway_id ? array( 0.034, 0.49 ) : $rate_and_fixed;
		};

		add_filter( 'profitlens_gateway_fee_rate', $callback, 10, 2 );

		$product = $this->create_product( 10.0, 100.0 );
		$order   = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 100.0 ) ),
			'completed',
			array( 'payment_method' => 'paypal' )
		);

		// 100 * 0.034 + 0.49 = 3.89
		$this->assertEquals( 3.89, $this->component->calculate( $order ) );

		remove_filter( 'profitlens_gateway_fee_rate', $callback, 10 );
	}

	/**
	 * Case 30.
	 */
	public function test_is_always_estimated() {
		$this->assertTrue( $this->component->is_estimated() );
	}

	/**
	 * Case 31: no payment method recorded (e.g. a manually created
	 * order) never hit a gateway — the fee must be 0, not the flat fee.
	 */
	public function test_no_payment_method_means_zero_fee() {
		$product = $this->create_product( 10.0, 100.0 );
		$order   = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 100.0 ) ),
			'completed',
			array( 'payment_method' => '' )
		);

		$this->assertSame( 0.0, $this->component->calculate( $order ) );
	}

	/**
	 * Case 26: a $0 order (e.g. 100%-off coupon) never actually hit a
	 * gateway either — charging the flat $0.30 fee would be fiction.
	 */
	public function test_zero_total_order_means_zero_fee() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 0.0 ) ),
			'completed',
			array( 'payment_method' => 'stripe', 'discount_total' => 20.0 )
		);

		$this->assertSame( 0.0, $this->component->calculate( $order ) );
	}
}
