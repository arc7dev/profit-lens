<?php
/**
 * Motor de cálculo de ganancia.
 *
 * VACÍO por diseño en esta fase: solo firmas de método documentadas. El
 * endpoint REST (ProfitLens_REST_Controller) hoy arma la respuesta con
 * datos de ejemplo directamente; cuando este engine tenga lógica, el
 * controller pasa a llamar get_summary() y el contrato de la respuesta no
 * cambia — así conectar el motor no exige tocar el frontend.
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

class ProfitLens_Profit_Engine {

	/**
	 * Fuentes de costo activas (FREE: solo COGS de WooCommerce; PRO añade
	 * más vía este mismo contrato, sin tocar el engine).
	 *
	 * @var ProfitLens_Cost_Source[]
	 */
	private $cost_sources;

	/**
	 * @param ProfitLens_Cost_Source[] $cost_sources
	 */
	public function __construct( array $cost_sources = array() ) {
		$this->cost_sources = $cost_sources;
	}

	/**
	 * Ganancia de un pedido individual: revenue - costo de producto -
	 * comisión de pasarela - envío + reembolsos.
	 *
	 * OJO al implementar: WooCommerce puede generar un WC_Order_Refund
	 * automático de reconciliación (reason "Order fully refunded.") apenas
	 * un pedido pasa a status "refunded", sin que haya un reembolso real
	 * cargado a mano — no asumir "1 pedido refunded = 1 WC_Order_Refund
	 * confiable". Sumar reembolsos siempre vía
	 * $order->get_total_refunded(), nunca iterando y confiando en que hay
	 * como máximo un refund "real". Ver CLAUDE.md, sección de
	 * comportamiento de WooCommerce.
	 *
	 * @param WC_Order $order
	 * @return array{revenue:float,product_cost:float,gateway_fee:float,shipping:float,refunded:float,profit:float}
	 */
	public function calculate_order_profit( WC_Order $order ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Ganancia agregada de un producto en un rango de fechas: suma de
	 * unidades, revenue, costo y margen % a través de todos los pedidos que
	 * incluyen ese producto en el rango.
	 *
	 * @param int               $product_id
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{product_id:int,name:string,units:int,revenue:float,cost:float,profit:float,margin_pct:float}
	 */
	public function calculate_product_profit( $product_id, DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Desglose de costos totales del rango por categoría (costo de
	 * producto, envío, reembolsos, comisiones de pasarela), en el orden y
	 * shape que espera `cost_breakdown` en la respuesta REST. Cada entrada
	 * marca is_estimated según lo que reporte la fuente de costo
	 * correspondiente (ver ProfitLens_Cost_Source::is_estimated()).
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array<int,array{key:string,label:string,amount:float,is_estimated:bool}>
	 */
	public function get_cost_breakdown( DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Cobertura de costos del rango, en dos dimensiones:
	 * - por catálogo: % de productos vendidos que tienen costo configurado.
	 * - por ingreso: % del revenue del periodo generado por productos con
	 *   costo conocido. Es la cifra que importa de verdad — un catálogo
	 *   93% cubierto puede esconder que falta el costo de los productos más
	 *   vendidos, y ese es el escenario que hace que la ganancia mostrada
	 *   no sea confiable.
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{products_with_cost:int,products_total:int,pct:float,revenue_covered_pct:float,revenue_uncovered:float}
	 */
	public function get_cost_coverage( DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Serie diaria de ganancia neta para el gráfico de "Profit over time".
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array<int,array{date:string,label:string,profit:float}>
	 */
	public function get_chart_series( DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Detecta el insight más relevante del rango (por ahora: el producto
	 * que más ganancia negativa generó). Devuelve null si no hay ningún
	 * insight que valga la pena mostrar.
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array{type:string,message:string,product_id:int}|null
	 */
	public function get_insight( DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}

	/**
	 * Orquesta todo lo anterior en el shape completo que devuelve
	 * GET /profit-lens/v1/summary. Este es el método que
	 * ProfitLens_REST_Controller::get_summary() va a llamar una vez que
	 * el engine tenga lógica real, en lugar de armar el mock a mano.
	 *
	 * @param DateTimeInterface $after
	 * @param DateTimeInterface $before
	 * @return array
	 */
	public function get_summary( DateTimeInterface $after, DateTimeInterface $before ) {
		// TODO: sin implementar todavía.
		return null;
	}
}
