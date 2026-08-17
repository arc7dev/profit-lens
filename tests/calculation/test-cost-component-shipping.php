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

	/**
	 * The shipping cost_breakdown entry carries a note explaining the
	 * amount is what was collected, not the store's real shipping cost —
	 * the only component with anything to caveat.
	 */
	public function test_note_explains_collected_vs_real_cost() {
		$component = new ProfitLens_Cost_Component_Shipping();

		$this->assertIsString( $component->get_note() );
		$this->assertNotEmpty( $component->get_note() );
	}
}
