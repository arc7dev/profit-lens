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
	 * The `null !== get_cogs_value()` check below can never actually see
	 * an explicit $0 for a top-level (non-variation) product, only "set or
	 * not" — confirmed against a live install (including writing
	 * '_cogs_total_value' via raw SQL and loading a product object that had
	 * never touched that row before, to rule out caching): WooCommerce's
	 * own WC_Product_Data_Store_CPT::read_product_data() loads the stored
	 * value through set_props(), which runs it through the same
	 * set_cogs_value() → adjust_cogs_value_before_set() that collapses
	 * `0.0 === $value` to null on write. So it collapses on *read* too, no
	 * matter how the "0" got into the database — WooCommerce makes an
	 * explicit $0 cost and "no cost set" indistinguishable for a top-level
	 * product, on every path, not just through this plugin's own
	 * set_cogs_value() calls.
	 *
	 * This does NOT extend to a VARIATION's own value: WC_Product_Variation
	 * overrides adjust_cogs_value_before_set() specifically "to disable the
	 * conversion of zero to null" (class-wc-product-variation.php) — a
	 * variation's own $0 cost is real and distinguishable from unset, on
	 * purpose, so a variation can override its parent's cost down to zero
	 * instead of silently inheriting it. Confirmed against 7 real
	 * variations in this project's seeded catalog: get_cogs_value()
	 * legitimately returns 0.0, not null, for each. The check below is
	 * written generically enough to already do the right thing in both
	 * cases — the distinction only mattered for
	 * ProfitLens_Cost_Source_Cogs::get_catalog_coverage()'s raw-SQL
	 * reimplementation of this same logic, which had to special-case it
	 * explicitly (SQL can't just call get_cogs_value() and let polymorphism
	 * handle it).
	 *
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
	 * Whole-catalog cost coverage — how many published, sellable
	 * products/variations have a defined cost, and how many exist in total.
	 *
	 * Optional fast path, called by ProfitLens_Profit_Engine::get_catalog_coverage()
	 * via duck-typing (method_exists) rather than the ProfitLens_Cost_Source
	 * interface: it's specific to how THIS source stores its data (a single
	 * postmeta key), so it can't be part of the generic contract every cost
	 * source has to implement — a future source that isn't meta-key-based
	 * (e.g. a Pro source reading from an external API) simply won't have
	 * this method, and the engine falls back to looping get_product_cost()
	 * per product for it.
	 *
	 * Why this exists at all: the naive way to get this count — load every
	 * product as a WC_Product object via wc_get_product() and call
	 * has_defined_cost() on each — costs 2-3 DB queries per product/variation
	 * with no persistent object cache (the normal case for a REST request:
	 * fresh PHP process, empty cache). Measured against a real 2,800-item
	 * catalog: ~7,200 queries and ~1.4s, paid on EVERY summary request,
	 * regardless of date range, because coverage isn't date-scoped. This SQL
	 * version does the same job in 2 queries.
	 *
	 * It has to reproduce has_defined_cost()'s exact semantics in raw SQL,
	 * not just "meta row exists" — and that semantics is NOT the same for a
	 * top-level product's own value as it is for a variation's own value:
	 *
	 * - WC_Product::adjust_cogs_value_before_set() collapses an explicit 0.0
	 *   cost to null on both write AND read (see the class docblock above),
	 *   so a top-level product's own '_cogs_total_value' of '0' does NOT
	 *   count as "has a cost".
	 * - WC_Product_Variation OVERRIDES that method specifically to disable
	 *   the collapse ("Replacement of the parent adjust_cogs_value_before_set
	 *   method to disable the conversion of zero to null.", class-wc-product-
	 *   variation.php) — a variation's own $0 cost is real and meaningfully
	 *   different from "not set" (it lets a variation override its parent's
	 *   cost down to zero instead of silently inheriting it), so a
	 *   variation's own '0' DOES count as "has a cost".
	 *
	 * Found the hard way: an earlier version of this method used the same
	 * "!= 0" exclusion for a variation's own value as for a top-level
	 * product's, and it disagreed with the live has_defined_cost()/
	 * get_product_cost() path on 7 real variations (parent product 64697,
	 * each with an explicit own cost of exactly $0) — get_cogs_value()
	 * legitimately returned 0.0 (not null) for every one of them. A parent
	 * PRODUCT's own value keeps the "!= 0" exclusion (the collapse-to-null
	 * DOES apply there); only a variation's OWN condition drops it. The
	 * PARENT's value referenced from the variations query is a top-level
	 * product's own value too, so it keeps the exclusion as well.
	 *
	 * Deliberately reads wp_postmeta directly for PRODUCTS, not orders — this
	 * does not touch the HPOS compatibility promise (which is about order
	 * storage; WooCommerce has no equivalent "High-Performance Product
	 * Storage" migration, products still live in wp_posts/wp_postmeta).
	 *
	 * @return array{with_cost:int,total:int}
	 */
	public function get_catalog_coverage() {
		global $wpdb;

		// If the COGS feature itself is off, WC_Product::get_cogs_value()
		// always returns null regardless of what's stored in postmeta (see
		// cogs_is_enabled() guards in WC_Product) — so no product can "have
		// a cost" from this source's point of view, even if old meta rows
		// are still sitting in the DB from when the feature was enabled.
		// The loop-based get_product_cost() path reflects this for free;
		// this raw-SQL path has to check it explicitly since it reads
		// postmeta straight past that feature gate.
		$cogs_available = $this->is_available();

		// Published, non-variable products (simple/grouped/external/etc.) —
		// each counts on its own. Variable products themselves aren't
		// sellable and are excluded here in favor of their variations below,
		// matching ProfitLens_Profit_Engine::get_catalog_coverage()'s loop.
		$cost_condition = "own.meta_value IS NOT NULL AND own.meta_value != '' AND (own.meta_value + 0) != 0";

		$products_sql = "
			SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN {$cost_condition} THEN 1 ELSE 0 END) AS with_cost
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} own ON own.post_id = p.ID AND own.meta_key = '_cogs_total_value'
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND p.ID NOT IN (
				SELECT tr.object_id
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'product_type' AND t.slug = 'variable'
			)
		";

		// $products_sql has no external input: every interpolated piece is a
		// $wpdb table property or a hardcoded meta_key/taxonomy/slug literal
		// defined a few lines above, never a request/user value. wpdb::prepare()
		// has nothing to placeholder here.
		$products = $wpdb->get_row( $products_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- see comment above, no external input.

		// Variations: "has a cost" if their OWN meta says so, OR (when their
		// own value is unset) the PARENT's own meta says so — the exact
		// fallback has_defined_cost() applies via wc_get_product($parent_id).
		// Additive mode isn't relevant here: it changes the resolved cost
		// AMOUNT, never whether a cost is considered "defined" at all.
		//
		// The variation's OWN condition deliberately does NOT exclude '0' —
		// see the docblock above: WC_Product_Variation disables the
		// zero-collapses-to-null behavior for its own value. The PARENT's
		// value is a top-level product's own value, which keeps the
		// exclusion (reusing $cost_condition against the `parent` alias).
		$own_variation_cost_condition = "own.meta_value IS NOT NULL AND own.meta_value != ''";
		$parent_cost_condition        = str_replace( 'own.', 'parent.', $cost_condition );

		$variations_sql = "
			SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN ({$own_variation_cost_condition}) OR ({$parent_cost_condition}) THEN 1 ELSE 0 END) AS with_cost
			FROM {$wpdb->posts} v
			INNER JOIN {$wpdb->posts} pp ON pp.ID = v.post_parent AND pp.post_status = 'publish'
			LEFT JOIN {$wpdb->postmeta} own ON own.post_id = v.ID AND own.meta_key = '_cogs_total_value'
			LEFT JOIN {$wpdb->postmeta} parent ON parent.post_id = v.post_parent AND parent.meta_key = '_cogs_total_value'
			WHERE v.post_type = 'product_variation'
			AND v.post_status = 'publish'
		";

		// $variations_sql has no external input either, same reasoning as
		// $products_sql above: table properties and hardcoded meta_key literals
		// only, nothing user-supplied.
		$variations = $wpdb->get_row( $variations_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- see comment above, no external input.

		$total = (int) $products->total + (int) $variations->total;

		if ( ! $cogs_available ) {
			return array(
				'with_cost' => 0,
				'total'     => $total,
			);
		}

		return array(
			'with_cost' => (int) $products->with_cost + (int) $variations->with_cost,
			'total'     => $total,
		);
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
