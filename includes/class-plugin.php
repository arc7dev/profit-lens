<?php
/**
 * Bootstrap del plugin. Singleton, registra el autoloader propio y arma
 * las piezas (admin, assets, REST) una vez que sabemos que WooCommerce
 * está activo.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Plugin {

	/**
	 * @var ProfitLens_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Mapa clase → archivo relativo a includes/. Autoloader explícito en vez
	 * de PSR-4 por convención de nombres (class-admin.php, no Admin.php) —
	 * el mismo patrón de includes que usa WooCommerce core.
	 *
	 * @var array<string,string>
	 */
	private static $class_map = array(
		'ProfitLens_Admin'           => 'class-admin.php',
		'ProfitLens_Assets'          => 'class-assets.php',
		'ProfitLens_REST_Controller' => 'class-rest-controller.php',
		'ProfitLens_Profit_Engine'   => 'calculation/class-profit-engine.php',
		'ProfitLens_Cost_Source'     => 'calculation/interface-cost-source.php',
	);

	/**
	 * @var ProfitLens_Admin
	 */
	public $admin;

	/**
	 * @var ProfitLens_Assets
	 */
	public $assets;

	/**
	 * @var ProfitLens_REST_Controller
	 */
	public $rest_controller;

	/**
	 * Devuelve (y crea si hace falta) la instancia única del plugin.
	 *
	 * @return ProfitLens_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		spl_autoload_register( array( $this, 'autoload' ) );

		if ( ! $this->woocommerce_is_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		$this->load_textdomain();
		$this->init();
	}

	/**
	 * Autoloader de clases del plugin.
	 *
	 * @param string $class Nombre de la clase solicitada.
	 */
	public function autoload( $class ) {
		if ( ! isset( self::$class_map[ $class ] ) ) {
			return;
		}

		require_once PROFITLENS_PLUGIN_DIR . 'includes/' . self::$class_map[ $class ];
	}

	/**
	 * Instancia las piezas del plugin. Cada clase engancha sus propios hooks
	 * en su constructor.
	 */
	private function init() {
		$this->admin           = new ProfitLens_Admin();
		$this->assets          = new ProfitLens_Assets();
		$this->rest_controller = new ProfitLens_REST_Controller();
	}

	private function load_textdomain() {
		load_plugin_textdomain(
			PROFITLENS_TEXT_DOMAIN,
			false,
			dirname( PROFITLENS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * @return bool
	 */
	private function woocommerce_is_active() {
		return class_exists( 'WooCommerce' );
	}

	public function render_missing_woocommerce_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e(
					'Profit Lens requiere WooCommerce activo para funcionar.',
					'profit-lens'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Callback de activación. No corre el motor de cálculo: solo prepara el
	 * terreno (flush de rewrite rules para los endpoints REST).
	 */
	public static function activate() {
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
