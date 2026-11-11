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
		'ProfitLens_Access'                      => 'class-access.php',
		'ProfitLens_Admin'                       => 'class-admin.php',
		'ProfitLens_Assets'                      => 'class-assets.php',
		'ProfitLens_REST_Controller'             => 'class-rest-controller.php',
		'ProfitLens_CLI_Verify'                  => 'class-cli-verify.php',
		'ProfitLens_Cost_Snapshotter'            => 'class-cost-snapshotter.php',
		'ProfitLens_Pro_Links'                   => 'class-pro-links.php',
		'ProfitLens_Review_Notice'               => 'class-review-notice.php',
		'ProfitLens_Profit_Engine'               => 'calculation/class-profit-engine.php',
		'ProfitLens_Cost_Source'                 => 'calculation/interface-cost-source.php',
		'ProfitLens_Cost_Source_Cogs'            => 'calculation/class-cost-source-cogs.php',
		'ProfitLens_Cost_Component'              => 'calculation/interface-cost-component.php',
		'ProfitLens_Cost_Component_Product'      => 'calculation/class-cost-component-product.php',
		'ProfitLens_Cost_Component_Refunds'      => 'calculation/class-cost-component-refunds.php',
		'ProfitLens_Cost_Component_Shipping'     => 'calculation/class-cost-component-shipping.php',
		'ProfitLens_Cost_Component_Gateway_Fees' => 'calculation/class-cost-component-gateway-fees.php',
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
	 * @var ProfitLens_Cost_Snapshotter
	 */
	public $cost_snapshotter;

	/**
	 * @var ProfitLens_Review_Notice
	 */
	public $review_notice;

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
		if ( ! $this->woocommerce_is_active() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		$this->init();
	}

	/**
	 * Registers the autoloader. Called from profit-lens.php as soon as this
	 * file is loaded, NOT from the constructor: the constructor only runs on
	 * 'plugins_loaded', and when WordPress activates a plugin it includes the
	 * plugin file and fires the activation hook in the same request, after
	 * 'plugins_loaded' has already fired. Registering here was the 1.3.0
	 * activation fatal (activate() needs ProfitLens_Review_Notice, and no
	 * autoloader existed yet). Idempotent.
	 */
	public static function register_autoloader() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		$registered = true;
	}

	/**
	 * Autoloader for the plugin's classes.
	 *
	 * @param string $class_name Requested class name.
	 */
	public static function autoload( $class_name ) {
		if ( ! isset( self::$class_map[ $class_name ] ) ) {
			return;
		}

		require_once PROFITLENS_PLUGIN_DIR . 'includes/' . self::$class_map[ $class_name ];
	}

	/**
	 * Instantiates the plugin's pieces. Each class wires up its own hooks
	 * in its constructor.
	 */
	private function init() {
		$this->admin            = new ProfitLens_Admin();
		$this->assets           = new ProfitLens_Assets();
		$this->rest_controller  = new ProfitLens_REST_Controller();
		$this->cost_snapshotter = new ProfitLens_Cost_Snapshotter();
		$this->review_notice    = new ProfitLens_Review_Notice();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			ProfitLens_CLI_Verify::register();
		}
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
	 * prepares the ground (flushing rewrite rules for the REST endpoints,
	 * and starting the review notice's 14-day clock — see
	 * ProfitLens_Review_Notice::record_first_activation()'s own docblock
	 * for why an existing install that updates without a fresh
	 * activation also needs its own fallback, handled separately by that
	 * same class).
	 */
	public static function activate() {
		flush_rewrite_rules();
		ProfitLens_Review_Notice::record_first_activation();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
