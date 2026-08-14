/**
 * Una tarjeta de KPI (Net Profit, Net Margin, Revenue, Total Costs).
 *
 * @param {Object}                    props
 * @param {string}                    props.label            Etiqueta corta en mayúsculas (p. ej. "Net Profit").
 * @param {string}                    props.value            Valor ya formateado (p. ej. "$2,347").
 * @param {string}                    props.sub              Línea secundaria (p. ej. "292 orders · Jul 15 – Aug 13").
 * @param {boolean}                   [props.hero=false]     Si es la tarjeta grande (Net Profit).
 * @param {'neutral'|'profit'|'loss'} [props.tone='neutral'] Color del valor/sub cuando hero=true.
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
