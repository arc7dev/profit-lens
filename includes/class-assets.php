<?php
/**
 * Encola el bundle de React y las fuentes auto-alojadas, solo en la pantalla
 * de Profit Lens.
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
	 * @param string $hook_suffix Pantalla de admin actual.
	 */
	public function enqueue( $hook_suffix ) {
		$admin = ProfitLens_Plugin::instance()->admin;

		if ( empty( $admin->hook_suffix ) || $hook_suffix !== $admin->hook_suffix ) {
			return;
		}

		$asset_file = PROFITLENS_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// El bundle todavía no se generó (falta `npm run build`).
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
				'restNamespace'  => ProfitLens_REST_Controller::REST_NAMESPACE,
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'currencyCode'   => get_woocommerce_currency(),
				// El dev switcher de estados (Dashboard.jsx) solo se muestra
				// cuando esto es true — nunca en un sitio de producción.
				'isDebug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);

		// @wordpress/api-fetch toma el nonce y la raíz de la REST API de acá
		// automáticamente — así no hay que pasarlos a mano en cada fetch.
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
					'Profit Lens: falta compilar el dashboard (npm run build).',
					'profit-lens'
				);
				?>
			</p>
		</div>
		<?php
	}
}
