<?php
/**
 * Plugin bootstrap. Singleton, registers the plugin's own autoloader, and
 * wires up the pieces (admin, assets, REST) once we know WooCommerce is
 * active.
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
	 * Class → file map, relative to includes/. Explicit autoloader instead
	 * of PSR-4, to keep WordPress-core file naming (class-admin.php, not
	 * Admin.php) — the same includes pattern WooCommerce core uses.
	 *
	 * @var array<string,string>
	 */
	private static $class_map = array(
		'ProfitLens_Admin'                      => 'class-admin.php',
		'ProfitLens_Assets'                     => 'class-assets.php',
		'ProfitLens_REST_Controller'             => 'class-rest-controller.php',
		'ProfitLens_CLI_Verify'                  => 'class-cli-verify.php',
		'ProfitLens_Profit_Engine'               => 'calculation/class-profit-engine.php',
		'ProfitLens_Cost_Source'                 => 'calculation/interface-cost-source.php',
		'ProfitLens_Cost_Source_Cogs'             => 'calculation/class-cost-source-cogs.php',
		'ProfitLens_Cost_Component'               => 'calculation/interface-cost-component.php',
		'ProfitLens_Cost_Component_Product'       => 'calculation/class-cost-component-product.php',
		'ProfitLens_Cost_Component_Refunds'       => 'calculation/class-cost-component-refunds.php',
		'ProfitLens_Cost_Component_Shipping'      => 'calculation/class-cost-component-shipping.php',
		'ProfitLens_Cost_Component_Gateway_Fees'  => 'calculation/class-cost-component-gateway-fees.php',
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
	 * Returns (and creates, if needed) the plugin's single instance.
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
	 * Autoloader for the plugin's classes.
	 *
	 * @param string $class Requested class name.
	 */
	public function autoload( $class ) {
		if ( ! isset( self::$class_map[ $class ] ) ) {
			return;
		}

		require_once PROFITLENS_PLUGIN_DIR . 'includes/' . self::$class_map[ $class ];
	}

	/**
	 * Instantiates the plugin's pieces. Each class wires up its own hooks
	 * in its constructor.
	 */
	private function init() {
		$this->admin           = new ProfitLens_Admin();
		$this->assets          = new ProfitLens_Assets();
		$this->rest_controller = new ProfitLens_REST_Controller();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			ProfitLens_CLI_Verify::register();
		}
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
					'Profit Lens requires WooCommerce to be active.',
					'profit-lens'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Activation callback. Doesn't run the calculation engine: it only
	 * prepares the ground (flushing rewrite rules for the REST endpoints).
	 */
	public static function activate() {
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
