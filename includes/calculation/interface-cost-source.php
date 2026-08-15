<?php
/**
 * Contract for a cost source.
 *
 * FREE (this plugin) implements a single source: WooCommerce's native
 * COGS field (10.3+). PRO adds sources that require connecting an external
 * account (Meta Ads spend, Google Ads spend) — but that lives on arc7.dev,
 * not in this repo (see "THE FREE / PRO LINE" in CLAUDE.md).
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

interface ProfitLens_Cost_Source {

	/**
	 * Unit cost of a product according to this source.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return float|null Unit cost, or null if this source has no data for
	 *                     the product (e.g. COGS not set).
	 */
	public function get_product_cost( WC_Product $product );

	/**
	 * Stable identifier for the source (e.g. 'woocommerce_cogs'), used as
	 * the `key` in the REST response's cost_breakdown.
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Human-readable label for the source, shown in the UI
	 * (e.g. "Product Cost").
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether this source gives an exact cost (explicitly entered data) or
	 * an estimated one (computed with a rate or an assumption). Feeds the
	 * `is_estimated` field of each cost_breakdown entry.
	 *
	 * @return bool
	 */
	public function is_estimated();

	/**
	 * Whether the source has data available to operate (e.g. whether
	 * WooCommerce 10.3+ with COGS is present). When no source is
	 * available, the endpoint responds with status "empty".
	 *
	 * @return bool
	 */
	public function is_available();
}
