/**
 * Warns how trustworthy the profit figure shown actually is: what
 * percentage of the period's REVENUE is backed by a known product cost. It
 * relies on revenue_covered_pct, not the catalog pct — a mostly-covered
 * catalog can hide the fact that the best sellers' costs are missing, and
 * that's what actually compromises the profit figure.
 *
 * Three severity tiers below "fully covered" (hidden), each rendering the
 * same data + consequence + action copy but with escalating visual
 * weight — same idea as InsightBar standing out from a plain KPI, just
 * with its own further gradient: a store at 92% coverage needs a nudge, a
 * store at 40% (the common case right after installing the plugin, before
 * any cost has been entered) needs something that doesn't read as
 * skippable.
 *
 * @param {Object}                                                                                                               props
 * @param {{products_with_cost:number, products_total:number, pct:number, revenue_covered_pct:number, revenue_uncovered:number}} props.coverage
 */

const PRODUCTS_URL = 'edit.php?post_type=product';

/**
 * @param {number} pct revenue_covered_pct.
 * @return {'full'|'moderate'|'low'|'critical'} The severity tier this
 *                                               percentage falls into.
 */
function getTier( pct ) {
	if ( pct >= 99.95 ) {
		return 'full';
	}

	if ( pct >= 90 ) {
		return 'moderate';
	}

	if ( pct >= 60 ) {
		return 'low';
	}

	return 'critical';
}

export default function CostCoverageNotice( { coverage } ) {
	if ( ! coverage || ! coverage.products_total ) {
		return null;
	}

	const tier = getTier( coverage.revenue_covered_pct );

	if ( 'full' === tier ) {
		return null;
	}

	const uncoveredCount =
		coverage.products_total - coverage.products_with_cost;

	return (
		<div className={ `pl-coverage pl-coverage--${ tier }` }>
			{ 'critical' === tier && (
				<span className="pl-coverage__icon" aria-hidden="true">
					⚠
				</span>
			) }
			<span className="pl-coverage__text">
				<span className="pl-coverage__figure pl-mono">
					{ coverage.revenue_covered_pct.toFixed( 1 ) }%
				</span>{ ' ' }
				of this period&rsquo;s revenue has a known product cost.{ ' ' }
				<span className="pl-coverage__figure pl-mono">
					{ uncoveredCount }
				</span>{ ' ' }
				{ 1 === uncoveredCount ? 'product has' : 'products have' } no
				cost set, so profit may be overstated.
			</span>
			<a href={ PRODUCTS_URL } className="pl-coverage__link pl-mono">
				Review products
			</a>
		</div>
	);
}
