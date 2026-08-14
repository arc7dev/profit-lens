/**
 * Avisa qué tan confiable es la cifra de ganancia mostrada: qué porcentaje
 * del INGRESO del periodo está respaldado por un costo de producto
 * conocido. Se apoya en revenue_covered_pct, no en pct por catálogo — un
 * catálogo mayormente cubierto puede esconder que faltan los costos de los
 * productos más vendidos, y eso es lo que de verdad compromete la cifra de
 * ganancia.
 *
 * @param {Object}                                                                                                               props
 * @param {{products_with_cost:number, products_total:number, pct:number, revenue_covered_pct:number, revenue_uncovered:number}} props.coverage
 */
export default function CostCoverageNotice( { coverage } ) {
	if ( ! coverage || ! coverage.products_total ) {
		return null;
	}

	const isFullyCovered = coverage.revenue_covered_pct >= 99.95;
	const isLow = coverage.revenue_covered_pct < 90;

	if ( isFullyCovered ) {
		return null;
	}

	return (
		<div className={ 'pl-coverage' + ( isLow ? ' pl-coverage--low' : '' ) }>
			<span>
				<span className="pl-coverage__figure pl-mono">
					{ coverage.revenue_covered_pct.toFixed( 1 ) }%
				</span>{ ' ' }
				of this period&rsquo;s revenue has a known product cost.{ ' ' }
				<span className="pl-coverage__figure pl-mono">
					{ coverage.products_total - coverage.products_with_cost }
				</span>{ ' ' }
				product
				{ coverage.products_total - coverage.products_with_cost === 1
					? ''
					: 's' }{ ' ' }
				still need
				{ coverage.products_total - coverage.products_with_cost === 1
					? 's'
					: '' }{ ' ' }
				a cost set.
			</span>
		</div>
	);
}
