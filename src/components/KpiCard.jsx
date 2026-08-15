/**
 * A single KPI card (Net Profit, Net Margin, Revenue, Total Costs).
 *
 * @param {Object}                    props
 * @param {string}                    props.label            Short uppercase label (e.g. "Net Profit").
 * @param {string}                    props.value            Already-formatted value (e.g. "$2,347").
 * @param {string}                    props.sub              Secondary line (e.g. "292 orders · Jul 15 – Aug 13").
 * @param {boolean}                   [props.hero=false]     Whether this is the large card (Net Profit).
 * @param {'neutral'|'profit'|'loss'} [props.tone='neutral'] Color of the value/sub when hero=true.
 */
export default function KpiCard( {
	label,
	value,
	sub,
	hero = false,
	tone = 'neutral',
} ) {
	const cardClass = [
		'pl-card',
		'pl-kpi',
		hero && 'pl-kpi--hero',
		hero && tone === 'loss' && 'pl-kpi--loss',
	]
		.filter( Boolean )
		.join( ' ' );

	const subClass = [
		'pl-kpi__sub',
		hero && tone === 'profit' && 'pl-kpi__sub--profit',
		hero && tone === 'loss' && 'pl-kpi__sub--loss',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ cardClass }>
			<div className="pl-kpi__label pl-mono">{ label }</div>
			<div className="pl-kpi__value pl-mono">{ value }</div>
			{ sub && <div className={ subClass + ' pl-mono' }>{ sub }</div> }
		</div>
	);
}
