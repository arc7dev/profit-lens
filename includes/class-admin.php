<?php
/**
 * Registers the admin page under the WooCommerce menu.
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Admin {

	/**
	 * Admin page slug. Reused by ProfitLens_Assets (to know which screen
	 * to enqueue on) and by the menu registration itself.
	 */
	const PAGE_SLUG = 'profit-lens';

	/**
	 * Hook suffix returned by add_submenu_page(), needed by
	 * ProfitLens_Assets to enqueue only on this screen.
	 *
	 * @var string
	 */
	public $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Hangs "Profit Lens" off the WooCommerce menu, not the top-level
	 * menu — the tool lives where the user already expects to find their
	 * store's analytics.
	 *
	 * Gated on ProfitLens_Access::current_user_has_access(): when Pro's
	 * User Control tab has restricted access to specific users, someone
	 * outside that list gets no menu item at all, not a menu item that
	 * leads to a 403 — the plugin is meant to be invisible to them, the
	 * same way a page they can't manage_woocommerce for already is.
	 */
	public function register_menu() {
		if ( ! ProfitLens_Access::current_user_has_access() ) {
			return;
		}

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
	 * Mount point for React. The bundle in src/index.js resolves the
	 * whole layout — this method only prints the container.
	 */
	public function render_page() {
		?>
		<div id="profitlens-root" class="profitlens-root">
			<noscript>
				<?php esc_html_e( 'Profit Lens needs JavaScript enabled to show the dashboard.', 'profit-lens' ); ?>
			</noscript>
		</div>
		<?php
	}
}
