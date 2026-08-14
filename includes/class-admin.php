<?php
/**
 * Registra la página de admin bajo el menú de WooCommerce.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Admin {

	/**
	 * Slug de la página de admin. Lo reutilizan ProfitLens_Assets (para saber
	 * en qué pantalla encolar) y el propio menú.
	 */
	const PAGE_SLUG = 'profit-lens';

	/**
	 * Hook suffix que devuelve add_submenu_page(), lo necesita
	 * ProfitLens_Assets para encolar solo en esta pantalla.
	 *
	 * @var string
	 */
	public $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Cuelga "Profit Lens" del menú de WooCommerce, no del menú raíz — la
	 * herramienta vive donde el usuario ya espera encontrar analítica de
	 * su tienda.
	 */
	public function register_menu() {
		$this->hook_suffix = add_submenu_page(
			'woocommerce',
			__( 'Profit Lens', 'profit-lens' ),
			__( 'Profit Lens', 'profit-lens' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Punto de montaje para React. Todo el layout lo resuelve el bundle de
	 * src/index.js — este método solo imprime el contenedor.
	 */
	public function render_page() {
		?>
		<div id="profitlens-root" class="profitlens-root">
			<noscript>
				<?php esc_html_e( 'Profit Lens necesita JavaScript habilitado para mostrar el dashboard.', 'profit-lens' ); ?>
			</noscript>
		</div>
		<?php
	}
}
