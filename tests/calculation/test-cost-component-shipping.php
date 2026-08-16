<?php
/**
 * Case 32.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/calculation/class-profitlens-calculation-test-case.php';

class Test_ProfitLens_Cost_Component_Shipping extends ProfitLens_Calculation_Test_Case {

	public function test_calculate_matches_shipping_total_exactly() {
		$product = $this->create_product( 10.0, 20.0 );
		$order   = $this->create_order(
			array( array( 'product' => $product, 'qty' => 1, 'total' => 20.0 ) ),
			'completed',
			array( 'shipping_total' => 12.34 )
		);

		$component = new ProfitLens_Cost_Component_Shipping();

		$this->assertSame( 12.34, $component->calculate( $order ) );
		$this->assertFalse( $component->is_estimated() );
		$this->assertSame( 'shipping', $component->get_key() );
	}
}
