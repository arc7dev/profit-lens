import { useState } from '@wordpress/element';

import {
	getMockEmptySummary,
	getMockErrorSummary,
	getMockSummary,
} from '../data/mock';
import { useSummary } from '../hooks/useSummary';
import CostBreakdown from './CostBreakdown';
import CostCoverageNotice from './CostCoverageNotice';
import EmptyState from './EmptyState';
import ErrorState from './ErrorState';
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

// Dev-only override on top of the real dashboard (useSummary(), fetched
// from the REST endpoint) — lets anyone with WP_DEBUG on force a specific
// view for visual QA/screenshots without needing a store actually in that
// state. Never active on load: a fresh page always shows live data first;
// clicking an override button is what turns this on, and clicking the same
// button again turns it back off.
const PREVIEW_STATES = [ 'ready', 'empty', 'loading', 'error' ];

function DevStateSwitcher( { override, onChange } ) {
	return (
		<div className="pl-dev-switcher">
			<span>Preview override (WP_DEBUG only):</span>
			{ PREVIEW_STATES.map( ( s ) => (
				<button
					key={ s }
					type="button"
					className={
						'pl-dev-switcher__btn' +
						( override === s
							? ' pl-dev-switcher__btn--active'
							: '' )
					}
					onClick={ () => onChange( override === s ? null : s ) }
				>
					{ s }
				</button>
			) ) }
			{ ! override && <span>(showing live data)</span> }
		</div>
	);
}

function formatCurrency( amount ) {
	const isLoss = amount < 0;
	return `${ isLoss ? '−' : '' }$${ Math.abs( amount ).toLocaleString() }`;
}

export default function Dashboard() {
	const [ rangeKey, setRangeKey ] = useState( '30d' );
	const [ previewOverride, setPreviewOverride ] = useState( null );

	const isDebug = Boolean(
		window.profitLensData && window.profitLensData.isDebug
	);

	// Always called (rules of hooks) — its result is simply unused while a
	// preview override is active. The background fetch it triggers is
	// harmless in that case; this is a WP_DEBUG-only affordance, not
	// something a real user ever hits.
	const live = useSummary( rangeKey );

	const switcher = isDebug && (
		<DevStateSwitcher
			override={ previewOverride }
			onChange={ setPreviewOverride }
		/>
	);

	const isLoading = previewOverride
		? previewOverride === 'loading'
		: live.isLoading;

	if ( isLoading ) {
		return (
			<>
				{ switcher }
				<LoadingState />
			</>
		);
	}

	// live.error is a transport-level failure (network, permissions, a
	// non-2xx response) — distinct from the REST contract's own
	// status: "error", which arrives as an ordinary 200 response and is
	// handled below via `data.status`.
	if ( ! previewOverride && live.error ) {
		return (
			<>
				{ switcher }
				<ErrorState message="Couldn't load profit data. Please try again." />
			</>
		);
	}

	let data;

	if ( previewOverride === 'empty' ) {
		data = getMockEmptySummary( rangeKey );
	} else if ( previewOverride === 'ready' ) {
		data = getMockSummary( rangeKey );
	} else if ( previewOverride === 'error' ) {
		data = getMockErrorSummary();
	} else {
		data = live.data;
	}

	if ( ! data ) {
		return (
			<>
				{ switcher }
				<LoadingState />
			</>
		);
	}

	if ( data.status === 'error' ) {
		return (
			<>
				{ switcher }
				<ErrorState message={ data.error && data.error.message } />
			</>
		);
	}

	if ( data.status === 'empty' ) {
		return (
			<>
				{ switcher }
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
			{ switcher }

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
					sub={
						null === kpis.net_profit.change_pct
							? 'vs prior period'
							: `▲ ${ kpis.net_profit.change_pct }% vs prior period`
					}
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
