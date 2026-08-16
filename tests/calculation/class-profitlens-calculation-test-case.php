<?php
/**
 * Shared fixtures for the calculation engine's test suite. Builds real
 * WC_Product/WC_Order objects against the WP test database rather than
 * mocking WooCommerce's CRUD layer — the whole point of this engine is
 * that it only talks to WooCommerce through that layer, so the tests
 * should exercise the real thing.
 *
 * @package ProfitLens\Tests
 */

defined( 'ABSPATH' ) || exit;

abstract class ProfitLens_Calculation_Test_Case extends WP_UnitTestCase {

	/**
	 * @param float|null $cost      Value passed to set_cogs_value(). Null leaves it unset.
	 * @param float      $price     Regular price.
	 * @return WC_Product_Simple
	 */
	protected function create_product( $cost, $price = 20.0 ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( (string) $price );
		$product->set_status( 'publish' );

		if ( null !== $cost ) {
			$product->set_cogs_value( $cost );
		}

		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * @param float|null $own_cost         set_cogs_value() on the variation itself. Null leaves it unset.
	 * @param float|null $parent_cost      set_cogs_value() on the parent. Null leaves it unset.
	 * @param bool       $additive         set_cogs_value_is_additive() on the variation.
	 * @param float      $price
	 * @return WC_Product_Variation
	 */
	protected function create_variation( $own_cost, $parent_cost, $additive = false, $price = 30.0 ) {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Test Variable Product' );
		$parent->set_status( 'publish' );

		if ( null !== $parent_cost ) {
			$parent->set_cogs_value( $parent_cost );
		}

		$parent->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( (string) $price );

		if ( null !== $own_cost ) {
			$variation->set_cogs_value( $own_cost );
		}

		$variation->set_cogs_value_is_additive( $additive );
		$variation->save();

		return wc_get_product( $variation->get_id() );
	}

	/**
	 * Builds an order the same way profitlens-seeder does for real orders:
	 * explicit props, no calculate_totals() — see that plugin's own note
	 * on why (HPOS + this project's WooCommerce version compute totals
	 * from live cart context that doesn't exist for a programmatically
	 * built historical order).
	 *
	 * @param array  $items  List of ['product' => WC_Product, 'qty' => int, 'total' => float|null].
	 *                       'total' defaults to product price * qty.
	 * @param string $status Order status without the 'wc-' prefix.
	 * @param array  $props  Extra order props: shipping_total, payment_method, etc.
	 * @return WC_Order
	 */
	protected function create_order( array $items, $status = 'completed', array $props = array() ) {
		$order = new WC_Order();
		$order->set_status( $status );
		$order->set_currency( 'USD' );
		$order->set_payment_method( $props['payment_method'] ?? 'bacs' );
		$order->set_shipping_total( $props['shipping_total'] ?? 0.0 );
		$order->set_shipping_tax( 0 );
		$order->set_cart_tax( $props['tax'] ?? 0.0 );
		$order->set_discount_total( $props['discount_total'] ?? 0.0 );

		$revenue = 0.0;

		foreach ( $items as $spec ) {
			/** @var WC_Product $product */
			$product = $spec['product'];
			$qty     = $spec['qty'];
			$total   = $spec['total'] ?? round( (float) $product->get_price() * $qty, 2 );

			$item = new WC_Order_Item_Product();
			$item->set_product_id( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );

			if ( $product->is_type( 'variation' ) ) {
				$item->set_variation_id( $product->get_id() );
			}

			$item->set_quantity( $qty );
			$item->set_subtotal( $total );
			$item->set_total( $total );
			$order->add_item( $item );

			$revenue += $total;
		}

		$order->set_total(
			round(
				$revenue + ( $props['shipping_total'] ?? 0.0 ) + ( $props['tax'] ?? 0.0 ) - ( $props['discount_total'] ?? 0.0 ),
				2
			)
		);

		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Refunds part or all of a specific line item — quantity and/or
	 * amount — the same way WooCommerce's own refund flow does
	 * (negative quantity/total on a dedicated refund line item; see
	 * wc_create_refund() in WooCommerce core).
	 *
	 * @param WC_Order $order
	 * @param int      $item_id
	 * @param float    $amount
	 * @param int      $qty
	 * @return WC_Order_Refund
	 */
	protected function refund_item( WC_Order $order, $item_id, $amount, $qty = 0 ) {
		return wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => $amount,
				'line_items' => array(
					$item_id => array(
						'qty'          => $qty,
						'refund_total' => $amount,
					),
				),
			)
		);
	}

	/**
	 * Simulates WooCommerce's automatic full-refund reconciliation
	 * (wc_order_fully_refunded(), hooked to woocommerce_order_status_
	 * refunded) WITHOUT a manual refund preceding it — the "phantom
	 * refund" case documented in CLAUDE.md: a single WC_Order_Refund for
	 * the entire remaining total, created purely by the status change.
	 *
	 * @param WC_Order $order
	 */
	protected function trigger_automatic_full_refund( WC_Order $order ) {
		$order->set_status( 'refunded' );
		$order->save();
	}
}
