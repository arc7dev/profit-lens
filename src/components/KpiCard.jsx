/**
 * A single KPI card (Net Profit, Net Margin, Revenue, Total Costs).
 *
 * `tone` and `subTone` are deliberately separate: `tone` colors the big
 * value itself (whether the CURRENT period's absolute figure is a profit
 * or a loss), while `subTone` colors the secondary line, which for Net
 * Profit is a TREND indicator (up vs. down from the prior period) — those
 * two things can disagree (e.g. a $0 current profit that's still an
 * improvement over a prior loss), so one prop controlling both was
 * actively wrong, not just imprecise: it showed a downward change_pct in
 * the same green used for an upward one, because it was reusing the
 * absolute-profit tone instead of the trend's own sign.
 *
 * @param {Object}                    props
 * @param {string}                    props.label            Short uppercase label (e.g. "Net Profit").
 * @param {string}                    props.value            Already-formatted value (e.g. "$2,347").
 * @param {string}                    props.sub              Secondary line (e.g. "292 orders · Jul 15 – Aug 13").
 * @param {boolean}                   [props.hero=false]     Whether this is the large card (Net Profit).
 * @param {'neutral'|'profit'|'loss'} [props.tone='neutral'] Color of the big value when hero=true.
 * @param {'neutral'|'profit'|'loss'} [props.subTone=tone]   Color of the sub line when hero=true; defaults to `tone` for cards that don't need the distinction.
 */
export default function KpiCard( {
	label,
	value,
	sub,
	hero = false,
	tone = 'neutral',
	subTone = tone,
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
		hero && subTone === 'profit' && 'pl-kpi__sub--profit',
		hero && subTone === 'loss' && 'pl-kpi__sub--loss',
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
