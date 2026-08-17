import { useState } from '@wordpress/element';

import {
	getMockEmptySummary,
	getMockErrorSummary,
	getMockSummary,
} from '../data/mock';
import { useSummary } from '../hooks/useSummary';
import CostBreakdown from './CostBreakdown';
import CostCoverageNotice from './CostCoverageNotice';
import CustomRangePicker from './CustomRangePicker';
import EmptyState from './EmptyState';
import ErrorState from './ErrorState';
import InsightBar from './InsightBar';
import KpiCard from './KpiCard';
import LoadingState from './LoadingState';
import NoOrdersState from './NoOrdersState';
import ProfitChart from './ProfitChart';
import ProSection from './ProSection';
import ProductTable from './ProductTable';

const RANGES = [
	{ key: '7d', label: 'Last 7 days' },
	{ key: '30d', label: 'Last 30 days' },
	{ key: 'month', label: 'This month' },
];

// Forces a specific view with FAKE data (src/data/mock.js), for visual
// QA/screenshots without needing a store actually in that state —
// WP_DEBUG only, never active on load. Labeled deliberately as "mock",
// not as if these buttons were a view of the real dashboard's current
// state: an earlier version just said "ready"/"empty"/etc., which read
// like a status indicator rather than a data override, and it's easy to
// forget one is still active and mistake fake numbers for real ones.
const MOCK_OVERRIDES = [ 'ready', 'empty', 'loading', 'error' ];

function MockOverrideSwitcher( { override, onChange } ) {
	return (
		<div className="pl-dev-switcher">
			<span>⚠ Force fake data (WP_DEBUG only):</span>
			{ MOCK_OVERRIDES.map( ( s ) => (
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
					mock: { s }
				</button>
			) ) }
			{ ! override && <span>(showing real data)</span> }
		</div>
	);
}

function formatCurrency( amount ) {
	const isLoss = amount < 0;
	return `${ isLoss ? '−' : '' }$${ Math.abs( amount ).toLocaleString() }`;
}

export default function Dashboard() {
	const [ rangeKey, setRangeKey ] = useState( '30d' );
	const [ customRange, setCustomRange ] = useState( null );
	const [ mockOverride, setMockOverride ] = useState( null );

	const isDebug = Boolean(
		window.profitLensData && window.profitLensData.isDebug
	);

	// Always called (rules of hooks) — its result is simply unused while a
	// mock override is active. The background fetch it triggers is
	// harmless in that case; this is a WP_DEBUG-only affordance, not
	// something a real user ever hits.
	const live = useSummary( rangeKey, customRange );

	const switcher = isDebug && (
		<MockOverrideSwitcher
			override={ mockOverride }
			onChange={ setMockOverride }
		/>
	);

	function selectRange( key ) {
		setRangeKey( key );
		if ( 'custom' !== key ) {
			setCustomRange( null );
		}
	}

	const isLoading = mockOverride
		? mockOverride === 'loading'
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
	if ( ! mockOverride && live.error ) {
		return (
			<>
				{ switcher }
				<ErrorState message="Couldn't load profit data. Please try again." />
			</>
		);
	}

	let data;

	if ( mockOverride === 'empty' ) {
		data = getMockEmptySummary( rangeKey );
	} else if ( mockOverride === 'ready' ) {
		data = getMockSummary( rangeKey );
	} else if ( mockOverride === 'error' ) {
		data = getMockErrorSummary();
	} else {
		data = live.data;
	}

	if ( ! data ) {
		// 'custom' selected but no range applied yet (useSummary() doesn't
		// fetch in that case — see its own docblock) — same treatment as
		// still loading, since there's nothing to show either way.
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

	const changePct = kpis.net_profit.change_pct;
	// Independent of isNetLoss: this is a TREND indicator (better or worse
	// than the prior period), not the current period's own profit/loss —
	// those can disagree (e.g. a $0 period that's still an improvement
	// over a prior loss), and coloring the trend by the wrong sign is
	// exactly the bug that got reported (a negative change_pct rendered in
	// the profit-green used for positive ones, under an arrow that always
	// pointed up regardless of the number's sign).
	let changeTone = 'neutral';
	if ( null !== changePct ) {
		changeTone = changePct >= 0 ? 'profit' : 'loss';
	}
	const changeSub =
		null === changePct
			? 'vs prior period'
			: `${ changePct >= 0 ? '▲' : '▼' } ${ Math.abs(
					changePct
			  ) }% vs prior period`;

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
							onClick={ () => selectRange( key ) }
						>
							{ label }
						</button>
					) ) }
					<CustomRangePicker
						appliedRange={
							'custom' === rangeKey ? customRange : null
						}
						onApply={ ( applied ) => {
							setRangeKey( 'custom' );
							setCustomRange( applied );
						} }
					/>
				</div>
			</div>

			{ kpis.revenue.orders_count === 0 ? (
				<NoOrdersState
					rangeLabel={ range.label }
					onExpandRange={ () => selectRange( '30d' ) }
				/>
			) : (
				<>
					<div className="pl-kpis">
						<KpiCard
							hero
							tone={ isNetLoss ? 'loss' : 'profit' }
							subTone={ changeTone }
							label="Net Profit"
							value={ formatCurrency( kpis.net_profit.amount ) }
							sub={ changeSub }
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
				</>
			) }
		</div>
	);
}
