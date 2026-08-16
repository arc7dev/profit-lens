<?php
/**
 * Cost source backed by WooCommerce's native Cost of Goods Sold field
 * (WC_Product::get_cogs_value(), 10.3+). The only FREE cost source.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Cost_Source_Cogs implements ProfitLens_Cost_Source {

	/**
	 * WooCommerce's own get_cogs_total_value() resolves variation vs.
	 * parent cost correctly (see WC_Product_Variation::get_cogs_total_value_core())
	 * — including the "additive" mode where a variation's cost is added on
	 * top of the parent's instead of replacing it. We reuse that resolution
	 * rather than reimplementing it.
	 *
	 * The problem: get_cogs_total_value() collapses "no cost anywhere in
	 * the chain" into 0.0, indistinguishable from a genuinely-configured
	 * $0 cost. Using it directly here would have inflated profit for every
	 * product without a cost (312 of them, ~12%, in the seeded dataset) by
	 * silently treating them as free. So this method first determines
	 * *whether* a cost is known — checking get_cogs_value() (nullable) on
	 * the product itself and, for variations with no value of their own,
	 * on the parent — and only then reads the resolved total.
	 *
	 * @param WC_Product $product
	 * @return float|null
	 */
	public function get_product_cost( WC_Product $product ) {
		if ( ! $this->has_defined_cost( $product ) ) {
			return null;
		}

		return $product->get_cogs_total_value();
	}

	/**
	 * @param WC_Product $product
	 * @return bool
	 */
	private function has_defined_cost( WC_Product $product ) {
		if ( null !== $product->get_cogs_value() ) {
			return true;
		}

		if ( ! $product instanceof WC_Product_Variation ) {
			return false;
		}

		// No value on the variation itself: WooCommerce falls back to the
		// parent's cost (both in additive and non-additive mode — additive
		// mode just adds the variation's own effective value, which
		// defaults to 0 when unset, on top of the parent's). So the
		// variation "has a defined cost" whenever the parent does, even if
		// the variation never set its own.
		$parent = wc_get_product( $product->get_parent_id() );

		return $parent && null !== $parent->get_cogs_value();
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return 'woocommerce_cogs';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Product Cost', 'profit-lens' );
	}

	/**
	 * @return bool
	 */
	public function is_estimated() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
		}

		return 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' );
	}
}
