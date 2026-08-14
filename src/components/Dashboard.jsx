import { useState } from '@wordpress/element';

import { getMockEmptySummary, getMockSummary } from '../data/mock';
import CostBreakdown from './CostBreakdown';
import CostCoverageNotice from './CostCoverageNotice';
import EmptyState from './EmptyState';
import InsightBar from './InsightBar';
import KpiCard from './KpiCard';
import LoadingState from './LoadingState';
import ProfitChart from './ProfitChart';
import ProSection from './ProSection';
import ProductTable from './ProductTable';

const RANGES = [
	{ key: '7d', label: 'Last 7 days' },
	{ key: '30d', label: 'Last 30 days' },
	{ key: 'month', label: 'This month' },
	{ key: 'custom', label: 'Custom' },
];

// Solo para QA visual durante esta fase de andamiaje — window.profitLensData
// lo trae class-assets.php únicamente cuando WP_DEBUG está activo, así que
// nunca aparece para un usuario real. Cuando el motor de cálculo esté
// conectado, este switcher y la dependencia de src/data/mock.js se van.
const PREVIEW_STATES = [ 'ready', 'empty', 'loading' ];

function DevStateSwitcher( { state, onChange } ) {
	return (
		<div className="pl-dev-switcher">
			<span>Preview (WP_DEBUG only):</span>
			{ PREVIEW_STATES.map( ( s ) => (
				<button
					key={ s }
					type="button"
					className={
						'pl-dev-switcher__btn' +
						( state === s ? ' pl-dev-switcher__btn--active' : '' )
					}
					onClick={ () => onChange( s ) }
				>
					{ s }
				</button>
			) ) }
		</div>
	);
}

function formatCurrency( amount ) {
	const isLoss = amount < 0;
	return `${ isLoss ? '−' : '' }$${ Math.abs( amount ).toLocaleString() }`;
}

export default function Dashboard() {
	const [ rangeKey, setRangeKey ] = useState( '30d' );
	const [ previewState, setPreviewState ] = useState( 'ready' );

	const isDebug = Boolean(
		window.profitLensData && window.profitLensData.isDebug
	);

	if ( previewState === 'loading' ) {
		return (
			<>
				{ isDebug && (
					<DevStateSwitcher
						state={ previewState }
						onChange={ setPreviewState }
					/>
				) }
				<LoadingState />
			</>
		);
	}

	const data =
		previewState === 'empty'
			? getMockEmptySummary( rangeKey )
			: getMockSummary( rangeKey );

	if ( data.status === 'empty' ) {
		return (
			<>
				{ isDebug && (
					<DevStateSwitcher
						state={ previewState }
						onChange={ setPreviewState }
					/>
				) }
				<EmptyState />
			</>
		);
	}

	const {
		kpis,
		insight,
		cost_coverage: coverage,
		chart,
		cost_breakdown: costBreakdown,
		products,
		products_meta: productsMeta,
		range,
	} = data;
	const isNetLoss = kpis.net_profit.amount < 0;

	return (
		<div>
			{ isDebug && (
				<DevStateSwitcher
					state={ previewState }
					onChange={ setPreviewState }
				/>
			) }

			<div className="pl-header">
				<div>
					<div className="pl-header__eyebrow pl-mono">
						Profit Lens
					</div>
					<h1 className="pl-header__title">Profit</h1>
				</div>

				<div className="pl-range">
					{ RANGES.map( ( { key, label } ) => (
						<button
							key={ key }
							type="button"
							className={
								'pl-range__btn' +
								( rangeKey === key
									? ' pl-range__btn--active'
									: '' )
							}
							onClick={ () => setRangeKey( key ) }
						>
							{ label }
						</button>
					) ) }
				</div>
			</div>

			<div className="pl-kpis">
				<KpiCard
					hero
					tone={ isNetLoss ? 'loss' : 'profit' }
					label="Net Profit"
					value={ formatCurrency( kpis.net_profit.amount ) }
					sub={ `▲ ${ kpis.net_profit.change_pct }% vs prior period` }
				/>
				<KpiCard
					label="Net Margin"
					value={ `${ kpis.net_margin_pct.toFixed( 1 ) }%` }
					sub="Revenue minus all costs"
				/>
				<KpiCard
					label="Revenue"
					value={ formatCurrency( kpis.revenue.amount ) }
					sub={ `${ kpis.revenue.orders_count } orders · ${ range.label }` }
				/>
				<KpiCard
					label="Total Costs"
					value={ formatCurrency( kpis.total_costs.amount ) }
					sub="COGS · fees · shipping · refunds"
				/>
			</div>

			<CostCoverageNotice coverage={ coverage } />
			<InsightBar insight={ insight } />

			<div className="pl-row">
				<ProfitChart
					rangeLabel={ range.label }
					series={ chart.series }
				/>
				<CostBreakdown items={ costBreakdown } />
			</div>

			<ProductTable
				products={ products }
				totals={ productsMeta.totals }
				rangeLabel={ range.label }
			/>

			<ProSection />
		</div>
	);
}
