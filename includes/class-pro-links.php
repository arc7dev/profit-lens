<?php
/**
 * Builds the plugin's own links into arc7.dev's Pro click-attribution
 * routes (https://arc7.dev/profit-lens/pro/from/{source}/). Every URL
 * the plugin ever points a merchant at toward Pro goes through here —
 * no component hardcodes arc7.dev itself — so the whitelist, UTM shape,
 * and trailing-slash convention live in exactly one place.
 *
 * `{source}` must be one of the site's own whitelisted values (see
 * arc7-site's src/routes/profit-lens/pro/from/[source]/+page.server.ts);
 * anything else 302s there to /profit-lens/pro/ anyway, so this class
 * collapses an unrecognized source to 'other' before building the URL
 * rather than sending a merchant through an extra redirect.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Pro_Links {

	const BASE_URL = 'https://arc7.dev/profit-lens/pro/from/';

	/**
	 * Mirrors arc7-site's own whitelist exactly — see this file's
	 * docblock. Keep in sync by hand; there's no shared source of truth
	 * across the two repos.
	 *
	 * @var string[]
	 */
	const SOURCES = array(
		'export-csv',
		'product-modal',
		'ad-spend',
		'settings',
		'dashboard',
		'other',
	);

	/**
	 * One full URL, already including the trailing slash before the
	 * query string (same convention as the rest of arc7.dev — avoids a
	 * 301/302 round trip the site would otherwise add).
	 *
	 * @param string $source One of self::SOURCES; anything else becomes 'other'.
	 * @return string
	 */
	public static function build_url( $source ) {
		$source = in_array( $source, self::SOURCES, true ) ? $source : 'other';

		return add_query_arg(
			array(
				'utm_source'   => 'plugin',
				'utm_medium'   => $source,
				'utm_campaign' => 'free-' . PROFITLENS_VERSION,
			),
			self::BASE_URL . $source . '/'
		);
	}

	/**
	 * Every whitelisted source's URL at once, keyed by source — what
	 * ProfitLens_Assets::enqueue() hands to window.profitLensData.proLinks
	 * so the frontend never has to call build_url() (or know the URL
	 * shape) itself.
	 *
	 * @return array<string,string>
	 */
	public static function build_all() {
		$urls = array();

		foreach ( self::SOURCES as $source ) {
			$urls[ $source ] = self::build_url( $source );
		}

		return $urls;
	}
}
