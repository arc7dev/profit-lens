<?php
/**
 * Contrato para una fuente de costo.
 *
 * FREE (este plugin) implementa una sola fuente: el campo COGS nativo de
 * WooCommerce (10.3+). PRO añade fuentes que exigen conectar una cuenta
 * externa (gasto de Meta Ads, Google Ads) — pero eso vive en arc7.dev, no
 * en este repo (ver "LA FRONTERA FREE / PRO" en CLAUDE.md).
 *
 * @package ProfitLens\Calculation
 */

defined( 'ABSPATH' ) || exit;

interface ProfitLens_Cost_Source {

	/**
	 * Costo unitario de un producto según esta fuente.
	 *
	 * @param WC_Product $product Producto de WooCommerce.
	 * @return float|null Costo unitario, o null si esta fuente no tiene dato
	 *                     para el producto (p. ej. COGS sin configurar).
	 */
	public function get_product_cost( WC_Product $product );

	/**
	 * Identificador estable de la fuente (p. ej. 'woocommerce_cogs'),
	 * usado como `key` en cost_breakdown de la respuesta REST.
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Etiqueta legible de la fuente para mostrar en la UI
	 * (p. ej. "Product Cost").
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Si esta fuente da un costo exacto (dato cargado explícitamente) o
	 * estimado (calculado con una tasa o supuesto). Alimenta el campo
	 * `is_estimated` de cada entrada de cost_breakdown.
	 *
	 * @return bool
	 */
	public function is_estimated();

	/**
	 * Si la fuente tiene datos disponibles para operar (p. ej. si WooCommerce
	 * 10.3+ con COGS está presente). Cuando ninguna fuente está disponible,
	 * el endpoint responde status "empty".
	 *
	 * @return bool
	 */
	public function is_available();
}
