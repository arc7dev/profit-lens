import {
	Area,
	AreaChart,
	CartesianGrid,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from 'recharts';

const MINT = '#98dbaf';
const PROFIT = '#1f8a5b';
const MUTED = '#64757f';
const BORDER = '#e3e9ee';

function ChartTooltip( { active, payload, label } ) {
	if ( ! active || ! payload || ! payload.length ) {
		return null;
	}

	const value = payload[ 0 ].value;
	const isLoss = value < 0;

	return (
		<div className="pl-chart__tooltip">
			<div className="pl-chart__tooltip-day">{ label }</div>
			<div
				className={
					'pl-chart__tooltip-value' +
					( isLoss ? ' pl-chart__tooltip-value--loss' : '' )
				}
			>
				{ isLoss ? '−' : '' }${ Math.abs( value ).toLocaleString() }
			</div>
		</div>
	);
}

/**
 * Gráfico de área "Profit over time". Recharts en vez del SVG a mano del
 * export de Figma — mismo look (relleno menta con gradiente, línea fina).
 *
 * @param {Object}                                               props
 * @param {string}                                               props.rangeLabel Etiqueta del rango (p. ej. "Jul 15 – Aug 13, 2026").
 * @param {Array<{date:string|null,label:string,profit:number}>} props.series
 */
export default function ProfitChart( { rangeLabel, series } ) {
	return (
		<div className="pl-card pl-chart-card">
			<div className="pl-row__label">
				Profit over time — { rangeLabel }
			</div>

			<ResponsiveContainer width="100%" height={ 190 }>
				<AreaChart
					data={ series }
					margin={ { top: 10, right: 10, left: 0, bottom: 0 } }
				>
					<defs>
						<linearGradient
							id="plMintFill"
							x1="0"
							y1="0"
							x2="0"
							y2="1"
						>
							<stop
								offset="0%"
								stopColor={ MINT }
								stopOpacity={ 0.28 }
							/>
							<stop
								offset="100%"
								stopColor={ MINT }
								stopOpacity={ 0.02 }
							/>
						</linearGradient>
					</defs>

					<CartesianGrid
						vertical={ false }
						stroke={ BORDER }
						strokeDasharray="2 4"
					/>

					<XAxis
						dataKey="label"
						axisLine={ false }
						tickLine={ false }
						tick={ {
							fontSize: 9,
							fontFamily: 'var(--pl-font-mono)',
							fill: MUTED,
						} }
					/>

					<YAxis
						axisLine={ false }
						tickLine={ false }
						allowDecimals={ false }
						tick={ {
							fontSize: 9,
							fontFamily: 'var(--pl-font-mono)',
							fill: MUTED,
						} }
						tickFormatter={ ( v ) => `$${ v }` }
						width={ 40 }
					/>

					<Tooltip
						content={ <ChartTooltip /> }
						cursor={ { stroke: BORDER } }
					/>

					<Area
						type="monotone"
						dataKey="profit"
						stroke={ MINT }
						strokeWidth={ 1.5 }
						fill="url(#plMintFill)"
						activeDot={ { r: 4, fill: PROFIT } }
					/>
				</AreaChart>
			</ResponsiveContainer>
		</div>
	);
}
