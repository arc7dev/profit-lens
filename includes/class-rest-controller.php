<?php
/**
 * Endpoint REST de Profit Lens.
 *
 * Por ahora devuelve datos de ejemplo armados en PHP — la forma de la
 * respuesta es la definitiva. Cuando ProfitLens_Profit_Engine tenga lógica
 * real, get_summary() cambia de "arma un array mock" a "le pregunta al
 * engine", pero el contrato que consume React no se toca.
 *
 * Override de QA sin parámetros de URL (los debug params por querystring
 * tienden a sobrevivir hasta producción): usar los filtros
 * `profitlens_demo_status` / `profitlens_demo_error`, por ejemplo desde un
 * mu-plugin local:
 *
 *     add_filter( 'profitlens_demo_status', fn() => 'empty' );
 *
 * @package ProfitLens
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_REST_Controller {

	const REST_NAMESPACE = 'profit-lens/v1';
	const ROUTE_SUMMARY  = '/summary';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_SUMMARY,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'range'  => array(
						'type'    => 'string',
						'enum'    => array( '7d', '30d', 'month', 'custom' ),
						'default' => '30d',
					),
					'after'  => array(
						'type'   => 'string',
						'format' => 'date',
					),
					'before' => array(
						'type'   => 'string',
						'format' => 'date',
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_summary( WP_REST_Request $request ) {
		$status = apply_filters( 'profitlens_demo_status', 'ready', $request );

		if ( 'error' === $status ) {
			return rest_ensure_response( $this->build_error_response( $request ) );
		}

		$range = $this->get_range_config( $request->get_param( 'range' ) );

		if ( 'empty' === $status ) {
			return rest_ensure_response( $this->build_empty_response( $range ) );
		}

		return rest_ensure_response( $this->build_ready_response( $range ) );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function build_error_response( WP_REST_Request $request ) {
		$error = apply_filters(
			'profitlens_demo_error',
			array(
				'code'    => 'calculation_failed',
				'message' => __( 'Profit Lens could not calculate profit for this period.', 'profit-lens' ),
			),
			$request
		);

		return array(
			'status' => 'error',
			'error'  => $error,
		);
	}

	/**
	 * @param array $range Config de rango (ver get_range_config()).
	 * @return array
	 */
	private function build_empty_response( array $range ) {
		return array(
			'range'          => $range['meta'],
			'status'         => 'empty',
			'kpis'           => null,
			'insight'        => null,
			'cost_coverage'  => array(
				'products_with_cost'  => 0,
				'products_total'      => 0,
				'pct'                 => 0.0,
				'revenue_covered_pct' => 0.0,
				'revenue_uncovered'   => 0.0,
			),
			'chart'          => array( 'series' => array() ),
			'cost_breakdown' => array(),
			'products'       => array(),
			'products_meta'  => array(
				'total'  => 0,
				'totals' => array(
					'units'      => 0,
					'revenue'    => 0.0,
					'cost'       => 0.0,
					'profit'     => 0.0,
					'margin_pct' => 0.0,
				),
			),
		);
	}

	/**
	 * @param array $range Config de rango (ver get_range_config()).
	 * @return array
	 */
	private function build_ready_response( array $range ) {
		$currency = get_woocommerce_currency();
		$products = $this->get_mock_products();

		return array(
			'range'          => $range['meta'],
			'status'         => 'ready',
			'kpis'           => array(
				'net_profit'     => array(
					'amount'     => $range['net_profit'],
					'currency'   => $currency,
					'change_pct' => $range['change_pct'],
				),
				'net_margin_pct' => $range['margin_pct'],
				'revenue'        => array(
					'amount'       => $range['revenue'],
					'currency'     => $currency,
					'orders_count' => $range['orders'],
				),
				'total_costs'    => array(
					'amount'   => $range['costs'],
					'currency' => $currency,
				),
			),
			'insight'        => array(
				'type'       => 'loss_making_product',
				'message'    => __( 'Waxed Canvas Backpack lost $300 this period — 6 units sold below cost.', 'profit-lens' ),
				'product_id' => 8,
			),
			// Cobertura por catálogo Y por ingreso: el % de productos con
			// costo puede verse alto mientras el % de ingreso cubierto es
			// bajo, si justo faltan los costos de los productos más
			// vendidos. revenue_covered_pct es la cifra que dice cuánto
			// confiar en la ganancia mostrada.
			'cost_coverage'  => array(
				'products_with_cost'  => 2612,
				'products_total'      => 2800,
				'pct'                 => 93.3,
				'revenue_covered_pct' => 97.1,
				'revenue_uncovered'   => 291.00,
			),
			'chart'          => array(
				'series' => $range['chart_series'],
			),
			'cost_breakdown' => array(
				array(
					'key'          => 'product_cost',
					'label'        => __( 'Product Cost', 'profit-lens' ),
					'amount'       => 6270.00,
					'is_estimated' => false,
				),
				array(
					'key'          => 'shipping',
					'label'        => __( 'Shipping', 'profit-lens' ),
					'amount'       => 487.00,
					'is_estimated' => false,
				),
				array(
					'key'          => 'refunds',
					'label'        => __( 'Refunds', 'profit-lens' ),
					'amount'       => 599.00,
					'is_estimated' => false,
				),
				array(
					// WooCommerce no siempre guarda la comisión real de la
					// pasarela en el pedido; cuando el engine tiene que
					// estimarla con una tasa asumida, is_estimated marca
					// esa cifra para que la UI la pinte como "est.".
					'key'          => 'gateway_fees',
					'label'        => __( 'Gateway Fees', 'profit-lens' ),
					'amount'       => 321.00,
					'is_estimated' => true,
				),
			),
			'products'       => $products,
			'products_meta'  => array(
				'total'  => count( $products ),
				'totals' => array(
					'units'      => $range['orders'],
					'revenue'    => $range['revenue'],
					'cost'       => $range['costs'],
					'profit'     => $range['net_profit'],
					'margin_pct' => $range['margin_pct'],
				),
			),
		);
	}

	/**
	 * Tabla de productos de ejemplo. Idéntica en todos los rangos por
	 * ahora — el engine real la recalculará por rango.
	 *
	 * @return array[]
	 */
	private function get_mock_products() {
		return array(
			array( 'id' => 1, 'name' => 'Merino Wool Crew Neck', 'units' => 47, 'revenue' => 2180.00, 'cost' => 1090.00, 'profit' => 784.00, 'margin_pct' => 36.0 ),
			array( 'id' => 2, 'name' => 'Canvas Tote — Natural', 'units' => 62, 'revenue' => 1860.00, 'cost' => 870.00, 'profit' => 729.00, 'margin_pct' => 39.2 ),
			array( 'id' => 3, 'name' => 'Organic Cotton Tee', 'units' => 82, 'revenue' => 1640.00, 'cost' => 1140.00, 'profit' => 270.00, 'margin_pct' => 16.5 ),
			array( 'id' => 4, 'name' => 'Leather Card Holder', 'units' => 31, 'revenue' => 1240.00, 'cost' => 600.00, 'profit' => 466.00, 'margin_pct' => 37.6 ),
			array( 'id' => 5, 'name' => 'Recycled Denim Jacket', 'units' => 10, 'revenue' => 1150.00, 'cost' => 860.00, 'profit' => 129.00, 'margin_pct' => 11.2 ),
			array( 'id' => 6, 'name' => 'Bamboo Knit Socks (3pk)', 'units' => 44, 'revenue' => 880.00, 'cost' => 590.00, 'profit' => 166.00, 'margin_pct' => 18.9 ),
			array( 'id' => 7, 'name' => 'Linen Shirt — Off White', 'units' => 10, 'revenue' => 620.00, 'cost' => 430.00, 'profit' => 103.00, 'margin_pct' => 16.6 ),
			array( 'id' => 8, 'name' => 'Waxed Canvas Backpack', 'units' => 6, 'revenue' => 454.00, 'cost' => 690.00, 'profit' => -300.00, 'margin_pct' => -66.1 ),
		);
	}

	/**
	 * Config de ejemplo por rango — valores dados, no recalculados (calcan
	 * los del export de Figma Make usado como referencia visual).
	 *
	 * @param string $range_key '7d' | '30d' | 'month' | 'custom'.
	 * @return array
	 */
	private function get_range_config( $range_key ) {
		$ranges = array(
			'7d'     => array(
				'label'      => 'Aug 7 – Aug 13, 2026',
				'orders'     => 14,
				'revenue'    => 2847.00,
				'costs'      => 2190.00,
				'net_profit' => 657.00,
				'margin_pct' => 23.1,
				'change_pct' => 8.2,
				'days'       => array( 'Aug 7', 'Aug 8', 'Aug 9', 'Aug 10', 'Aug 11', 'Aug 12', 'Aug 13' ),
				'values'     => array( 68, 134, 112, 89, 78, 95, 81 ),
			),
			'30d'    => array(
				'label'      => 'Jul 15 – Aug 13, 2026',
				'orders'     => 292,
				'revenue'    => 10024.00,
				'costs'      => 6270.00,
				'net_profit' => 2347.00,
				'margin_pct' => 23.4,
				'change_pct' => 12.4,
				'days'       => array( 'Jul 15', 'Jul 18', 'Jul 21', 'Jul 24', 'Jul 27', 'Jul 30', 'Aug 2', 'Aug 5', 'Aug 8', 'Aug 11', 'Aug 13' ),
				'values'     => array( 54, 98, 112, 134, 89, 201, 143, 178, 220, 262, 247 ),
			),
			'month'  => array(
				'label'      => 'Aug 1 – Aug 13, 2026',
				'orders'     => 47,
				'revenue'    => 4312.00,
				'costs'      => 3290.00,
				'net_profit' => 1022.00,
				'margin_pct' => 23.7,
				'change_pct' => 4.1,
				'days'       => array( 'Aug 1', 'Aug 3', 'Aug 5', 'Aug 7', 'Aug 9', 'Aug 11', 'Aug 13' ),
				'values'     => array( 68, 112, 201, 143, 178, 198, 247 ),
			),
			'custom' => array(
				'label'      => 'Jun 1 – Jul 14, 2026',
				'orders'     => 209,
				'revenue'    => 19880.00,
				'costs'      => 15240.00,
				'net_profit' => 4640.00,
				'margin_pct' => 23.3,
				'change_pct' => 9.7,
				'days'       => array( 'Jun 1', 'Jun 8', 'Jun 15', 'Jun 22', 'Jun 29', 'Jul 6', 'Jul 14' ),
				'values'     => array( 110, 180, 210, 154, 290, 240, 310 ),
			),
		);

		$range = isset( $ranges[ $range_key ] ) ? $ranges[ $range_key ] : $ranges['30d'];

		$chart_series = array();
		foreach ( $range['days'] as $i => $day_label ) {
			$chart_series[] = array(
				'date'    => $this->label_to_iso_date( $day_label ),
				'label'   => $day_label,
				'profit'  => $range['values'][ $i ],
			);
		}

		$range['chart_series'] = $chart_series;
		$range['meta']         = array(
			'key'    => $range_key,
			'label'  => $range['label'],
			'after'  => $chart_series[0]['date'],
			'before' => $chart_series[ count( $chart_series ) - 1 ]['date'],
		);

		return $range;
	}

	/**
	 * Convierte una etiqueta corta tipo "Aug 7" en fecha ISO 8601, asumiendo
	 * el año de la config de rango de ejemplo (2026). Solo para los datos
	 * mock — el engine real trabaja con fechas de pedidos reales.
	 *
	 * @param string $label
	 * @return string
	 */
	private function label_to_iso_date( $label ) {
		$date = DateTime::createFromFormat( 'M j Y', $label . ' 2026' );

		return $date ? $date->format( 'Y-m-d' ) : '';
	}
}
