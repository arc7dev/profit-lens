<?php
/**
 * Enqueues the React bundle and the self-hosted fonts, only on the
 * Profit Lens screen.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Assets {

	const HANDLE = 'profitlens-dashboard';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue( $hook_suffix ) {
		$admin = ProfitLens_Plugin::instance()->admin;

		if ( empty( $admin->hook_suffix ) || $hook_suffix !== $admin->hook_suffix ) {
			return;
		}

		$asset_file = PROFITLENS_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// The bundle hasn't been generated yet (missing `npm run build`).
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			self::HANDLE . '-fonts',
			PROFITLENS_PLUGIN_URL . 'assets/css/fonts.css',
			array(),
			PROFITLENS_VERSION
		);

		wp_enqueue_style(
			self::HANDLE,
			PROFITLENS_PLUGIN_URL . 'build/index.css',
			array( self::HANDLE . '-fonts' ),
			$asset['version']
		);

		wp_enqueue_script(
			self::HANDLE,
			PROFITLENS_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( self::HANDLE, 'profit-lens', PROFITLENS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			self::HANDLE,
			'profitLensData',
			array(
				'restNamespace'     => ProfitLens_REST_Controller::REST_NAMESPACE,
				'currencySymbol'    => get_woocommerce_currency_symbol(),
				'currencyCode'      => get_woocommerce_currency(),
				// Store-configured formatting for everything else about a
				// price — not date-scoped, so this belongs here (localized
				// once per page load) rather than repeated on every /summary
				// response. formatCurrency() (src/utils/currency.js) is the
				// only place these combine into a rendered string; every
				// component goes through it instead of hardcoding "$" +
				// toLocaleString(), which breaks for a store using a comma
				// decimal separator or a symbol placed after the number.
				'decimalSeparator'  => wc_get_price_decimal_separator(),
				'thousandSeparator' => wc_get_price_thousand_separator(),
				'currencyPosition'  => get_option( 'woocommerce_currency_pos', 'left' ),
				// The dev state switcher (Dashboard.jsx) only shows up
				// when this is true. Double-gated on purpose: WP_DEBUG alone
				// isn't a reliable "this is a dev install" signal — plenty of
				// real stores run it in production for logging/other plugins'
				// debugging without meaning to expose this plugin's own dev
				// tools. PROFITLENS_DEV is a second, plugin-specific constant
				// a developer defines in their own wp-config.php (see
				// CLAUDE.md) — both have to be true.
				'isDebug'           => defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'PROFITLENS_DEV' ) && PROFITLENS_DEV,
				// "Today", computed in the site's configured timezone —
				// the same current_datetime() the REST controller itself
				// anchors named ranges to (class-rest-controller.php's
				// get_range_bounds()). The custom date picker uses this,
				// not the browser's local date, to decide what counts as
				// "a future date" — a browser in a different timezone than
				// the site must not get a different validation answer.
				'siteToday'         => current_datetime()->format( 'Y-m-d' ),
				// @wordpress/components' DatePicker already renders month/
				// day names through @wordpress/date's own i18n (localized
				// automatically wherever 'wp-date' is enqueued — no action
				// needed here for that part); dateFormat and startOfWeek
				// are for the parts DatePicker leaves to the caller: how
				// CustomRangePicker prints the applied range on its own
				// trigger button, and which day the calendar week starts on.
				'dateFormat'        => get_option( 'date_format' ),
				'startOfWeek'       => (int) get_option( 'start_of_week', 0 ),
				// Empty by default — no external code registered. Pro (if
				// active) hooks this to provide a real URL, gated on its own
				// license validity; ProSection.jsx renders a real "Upload
				// CSV" link when this is non-empty, and its existing
				// ProUpgradeModal upsell otherwise. Free itself never
				// assumes Pro exists, never checks for it, and doesn't
				// change if Pro is absent — this is read-only surface Pro
				// opts into from its own side, same shape as the
				// profitlens_demo_status/profitlens_demo_error filters
				// class-rest-controller.php already exposes.
				'csvImportUrl'      => apply_filters( 'profitlens_csv_import_url', '' ),
			)
		);

		// @wordpress/api-fetch picks up the nonce and REST API root from
		// here automatically — no need to pass them by hand on every fetch.
		wp_localize_script(
			'wp-api-fetch',
			'wpApiSettings',
			array(
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public function render_missing_build_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				esc_html_e(
					'Profit Lens: the dashboard hasn\'t been built yet (npm run build).',
					'profit-lens'
				);
				?>
			</p>
		</div>
		<?php
	}
}
