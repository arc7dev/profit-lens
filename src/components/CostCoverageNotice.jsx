/**
 * Warns how trustworthy the profit figure shown actually is: what
 * percentage of the period's REVENUE is backed by a known product cost.
 * It relies on revenue_covered_pct, not the catalog pct — a mostly-covered
 * catalog can hide the fact that the best sellers' costs are missing, and
 * that's what actually compromises the profit figure.
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
