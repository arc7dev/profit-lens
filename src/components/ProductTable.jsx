import { useMemo, useRef, useState } from '@wordpress/element';

import { formatCurrency } from '../utils/currency';

// Fixed pixel widths for the five NUMERIC columns only — name is
// deliberately left unset here and instead gets `width: 100%` in CSS
// (.pl-table__cell--name), absorbing whatever's left after these five.
// table-layout stays the browser default (auto), not `fixed`: fixed
// layout ignores content/hints entirely and only obeys <col> widths as
// absolute, which is exactly what broke this the first time round — a
// flexible column has no way to actually receive "the rest of the
// table" under fixed layout without a matching, hand-kept-in-sync
// number on the <table> element itself (tried it: works, but leaves a
// dead gap on any viewport wider than that number, since the table
// stops growing with its container). Auto layout uses <col> widths as
// real per-column hints while still letting width: 100% on the flexible
// cell take the remainder — the two other properties actually working
// together here, rather than one substituting for the other.
//
// Each numeric width below is sized off REAL measured worst cases
// (wp-cli against the seeded catalog's full history, then measured in
// the browser in the exact production font — see the PR discussion),
// not guessed: a single product's own figures top out around $24.5K/
// -$6.9K, but an ALL-TIME totals row (a real, reachable custom range,
// not a hypothetical) reaches $545,522.12 revenue / −$257,716.71 profit
// — the totals row has to fit too, not just individual product rows.
// Net Profit also has to fit the "No cost set" chip (measured ~116px
// with padding), which turned out wider than any currency figure a
// single product row will ever show in that column.
const COLUMNS = [
	{ key: 'name', label: 'Product', align: 'left', sticky: 'left' },
	{ key: 'units', label: 'Units', align: 'right', width: 72 },
	{ key: 'revenue', label: 'Revenue', align: 'right', width: 118 },
	{ key: 'cost', label: 'Cost', align: 'right', width: 118 },
	{
		key: 'profit',
		label: 'Net Profit',
		align: 'right',
		width: 130,
		sticky: 'right',
	},
	{ key: 'margin_pct', label: 'Margin %', align: 'right', width: 96 },
];

/**
 * @param {{align:string, sticky?: 'left'|'right'}} col
 * @return {string} Class names for a <th> in this column (used by the
 *                   generic header row below — body rows aren't generated
 *                   from COLUMNS, so they use the constants underneath
 *                   this function directly instead).
 */
function columnClass( col ) {
	const classes = [];

	if ( col.align === 'left' ) {
		classes.push( 'pl-table__col--label' );
	}

	if ( col.sticky ) {
		classes.push( `pl-table__col--sticky-${ col.sticky }` );
	}

	return classes.join( ' ' );
}

// Body rows are written by hand per field (not mapped from COLUMNS), so
// the two sticky columns' classes are named directly here rather than
// looked up by array index into COLUMNS.
const NAME_CELL_CLASS =
	'pl-table__col--label pl-table__cell--name pl-table__col--sticky-left';
const PROFIT_CELL_STICKY_CLASS = 'pl-table__col--sticky-right';

const PAGE_SIZE = 25;

function marginClass( row ) {
	if ( ! row.has_cost ) {
		return '';
	}

	if ( row.profit < 0 ) {
		return 'pl-table__margin--loss';
	}

	return row.margin_pct > 40 ? 'pl-table__margin--high' : '';
}

function SortIcon( { active, dir } ) {
	return (
		<span
			className={
				'pl-table__sort-icon' +
				( active ? ' pl-table__sort-icon--active' : '' )
			}
		>
			<svg width="6" height="4" viewBox="0 0 6 4" fill="none">
				<path
					d="M3 0L6 4H0L3 0Z"
					fill={ active && dir === 'asc' ? '#12212E' : '#64757F' }
				/>
			</svg>
			<svg width="6" height="4" viewBox="0 0 6 4" fill="none">
				<path
					d="M3 4L0 0H6L3 4Z"
					fill={ active && dir === 'desc' ? '#12212E' : '#64757F' }
				/>
			</svg>
		</span>
	);
}

/**
 * "Profit by Product" table: search + sortable by column (over the full
 * result set, not just the visible page) + client-side pagination.
 *
 * Client-side, deliberately, not server-side: the REST endpoint already
 * computes every product's figures in one pass over the period's orders
 * (ProfitLens_Profit_Engine::aggregate() — see its docblock) regardless of
 * how many rows the UI will ever show, so paginating server-side would
 * only shrink the JSON payload, not the calculation cost, at the price of
 * a request per page/sort/search change and a parallel PHP implementation
 * of exactly the search+sort logic below. It also comes for free with the
 * one requirement server-side pagination would have fought: sorting by a
 * column has to reorder the ENTIRE product set, not just whichever 20-25
 * rows happen to be on screen — with the full array already in hand,
 * that's just Array.prototype.sort() over `products`, no extra request.
 *
 * MEASURED, not assumed (wp-cli eval against this project's real seeded
 * catalog — 2,602 products, 1,297 of which have ever sold): an all-time
 * `/summary` response (the worst case — every range this endpoint serves
 * is a subset of it) is ~780KB of JSON, ~75KB gzipped. Client-side
 * sort/filter/JSON.parse over that many rows are each well under 2ms in
 * a browser — synthetically pushed to 10,000 rows (roughly 4x this
 * project's entire catalog, not just its sold slice) and still under
 * 10ms combined. Client-side COMPUTE is not the constraint at any catalog
 * size a WooCommerce Small-tier store (CLAUDE.md's actual buyer,
 * $50k–250k/year revenue) is realistic to reach.
 *
 * The real constraint is PAYLOAD TRANSFER TIME, and it bites everywhere
 * at once, not just this table: useSummary.js fetches kpis/chart/insight/
 * cost_coverage/products in a single request, so a large products array
 * delays the KPIs and chart rendering too, not merely this table's first
 * paint. The symptom to watch for is a slower "still loading" spinner on
 * dashboard open specifically for large/old stores on a slow connection —
 * not a sluggish table once data has already arrived (that part won't
 * degrade; see the compute numbers above). The fix at that point is NOT
 * server-side pagination (this docblock's whole argument above still
 * holds — compute isn't the bottleneck) but splitting `products` out of
 * the single get_summary() response into its own separate, genuinely
 * paginated request, so a big catalog only slows the table's own fetch
 * and leaves the KPIs/chart/insight loading independently and fast.
 * Revisit if/when a real store's `/summary` payload is measured
 * materially past what's documented here — this is a threshold to watch,
 * not a hard number to hit before acting.
 *
 * @param {Object}                                                                    props
 * @param {Array}                                                                     props.products
 * @param {{units:number,revenue:number,cost:number,profit:number,margin_pct:number}} props.totals
 * @param {string}                                                                    props.rangeLabel
 */
export default function ProductTable( { products, totals, rangeLabel } ) {
	const [ sortKey, setSortKey ] = useState( 'profit' );
	const [ sortDir, setSortDir ] = useState( 'desc' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 0 );
	const searchInputRef = useRef( null );

	function handleSort( key ) {
		if ( key === sortKey ) {
			setSortDir( ( dir ) => ( dir === 'asc' ? 'desc' : 'asc' ) );
		} else {
			setSortKey( key );
			setSortDir( 'desc' );
		}
		setPage( 0 );
	}

	function handleSearchChange( value ) {
		setSearch( value );
		setPage( 0 );
	}

	function handleClearSearch() {
		handleSearchChange( '' );
		// Standard "clear search" UX — leaves focus where the user's
		// attention already is instead of dropping it back to the top of
		// the page (the button disappears the instant this runs, so
		// without this the browser would fall back to <body>).
		searchInputRef.current?.focus();
	}

	const filtered = useMemo( () => {
		const query = search.trim().toLowerCase();

		if ( ! query ) {
			return products;
		}

		return products.filter( ( row ) =>
			row.name.toLowerCase().includes( query )
		);
	}, [ products, search ] );

	// Sorted over the full filtered set, before pagination slices it — a
	// "lowest profit" sort has to surface the single worst performer out of
	// all 313 products, not just whichever page was already on screen.
	const sorted = useMemo( () => {
		return [ ...filtered ].sort( ( a, b ) => {
			const av = a[ sortKey ];
			const bv = b[ sortKey ];

			if ( typeof av === 'string' ) {
				return sortDir === 'asc'
					? av.localeCompare( bv )
					: bv.localeCompare( av );
			}

			return sortDir === 'asc' ? av - bv : bv - av;
		} );
	}, [ filtered, sortKey, sortDir ] );

	const pageCount = Math.max( 1, Math.ceil( sorted.length / PAGE_SIZE ) );
	const currentPage = Math.min( page, pageCount - 1 );
	const start = currentPage * PAGE_SIZE;
	const paged = sorted.slice( start, start + PAGE_SIZE );
	const rangeStart = sorted.length === 0 ? 0 : start + 1;
	const rangeEnd = Math.min( start + PAGE_SIZE, sorted.length );

	return (
		<div className="pl-card pl-table-card">
			<div className="pl-table-card__header">
				<div className="pl-table-card__title">Profit by Product</div>

				{ /* A separate flex child from .pl-table-card__actions, not
				 * nested inside it — that's what lets it float in the
				 * middle and absorb space up to its own max-width instead
				 * of being squeezed against Export CSV/the count on the
				 * right (see .pl-table-card__header's justify-content:
				 * space-between in dashboard.css, which is what turns the
				 * leftover room into blank space on both sides of this
				 * box rather than trailing space at the end of the row). */ }
				<div className="pl-table-card__search-wrap">
					<svg
						className="pl-table-card__search-icon"
						width="13"
						height="13"
						viewBox="0 0 13 13"
						fill="none"
						aria-hidden="true"
					>
						<circle
							cx="5.5"
							cy="5.5"
							r="4.5"
							stroke="currentColor"
							strokeWidth="1.3"
						/>
						<path
							d="M8.8 8.8L12 12"
							stroke="currentColor"
							strokeWidth="1.3"
							strokeLinecap="round"
						/>
					</svg>
					<input
						ref={ searchInputRef }
						type="search"
						className="pl-table-card__search pl-mono"
						placeholder="Search products…"
						aria-label="Search products"
						value={ search }
						onChange={ ( e ) =>
							handleSearchChange( e.target.value )
						}
					/>
					{ search && (
						<button
							type="button"
							className="pl-table-card__search-clear"
							aria-label="Clear search"
							onClick={ handleClearSearch }
						>
							×
						</button>
					) }
				</div>

				<div className="pl-table-card__actions">
					<button type="button" className="pl-table-card__export">
						Export CSV
					</button>
					<div className="pl-table-card__count pl-mono">
						Showing { rangeStart }–{ rangeEnd } of { sorted.length }{ ' ' }
						products · { rangeLabel }
					</div>
				</div>
			</div>

			<div className="pl-table-wrap">
				<table className="pl-table">
					<colgroup>
						{ COLUMNS.map( ( col ) => (
							<col
								key={ col.key }
								style={ { width: col.width } }
							/>
						) ) }
					</colgroup>
					<thead>
						<tr>
							{ COLUMNS.map( ( col ) => (
								<th
									key={ col.key }
									className={ columnClass( col ) }
									onClick={ () => handleSort( col.key ) }
								>
									{ col.label }
									<SortIcon
										active={ sortKey === col.key }
										dir={ sortDir }
									/>
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ paged.length === 0 && (
							<tr>
								<td
									className="pl-table__empty"
									colSpan={ COLUMNS.length }
								>
									No products match &ldquo;{ search }
									&rdquo;.
								</td>
							</tr>
						) }

						{ paged.map( ( row ) => {
							const isLoss = row.has_cost && row.profit < 0;

							return (
								<tr key={ row.id }>
									<td className={ NAME_CELL_CLASS }>
										<div
											className="pl-table__name-text"
											title={ row.name }
										>
											{ row.name }
										</div>
									</td>
									<td>{ row.units }</td>
									<td>
										{ formatCurrency( row.revenue, 2 ) }
									</td>
									<td>{ formatCurrency( row.cost, 2 ) }</td>
									{ row.has_cost ? (
										<td
											className={ `${ PROFIT_CELL_STICKY_CLASS } ${
												isLoss
													? 'pl-table__profit--loss'
													: 'pl-table__profit--profit'
											}` }
										>
											{ formatCurrency( row.profit, 2 ) }
										</td>
									) : (
										<td
											className={
												PROFIT_CELL_STICKY_CLASS
											}
										>
											<span className="pl-table__chip pl-mono">
												No cost set
											</span>
										</td>
									) }
									<td className={ marginClass( row ) }>
										{ row.has_cost
											? `${ row.margin_pct.toFixed(
													1
											  ) }%`
											: '—' }
									</td>
								</tr>
							);
						} ) }

						<tr className="pl-table__totals">
							<td className={ NAME_CELL_CLASS }>
								<span className="pl-table__totals-label">
									Total
								</span>
							</td>
							<td>{ totals.units }</td>
							<td>{ formatCurrency( totals.revenue, 2 ) }</td>
							<td>{ formatCurrency( totals.cost, 2 ) }</td>
							<td
								className={ `${ PROFIT_CELL_STICKY_CLASS } pl-table__profit--profit` }
							>
								{ formatCurrency( totals.profit, 2 ) }
							</td>
							<td>{ totals.margin_pct.toFixed( 1 ) }%</td>
						</tr>
					</tbody>
				</table>
			</div>

			{ pageCount > 1 && (
				<div className="pl-table-card__pagination">
					<button
						type="button"
						className="pl-table-card__page-btn pl-mono"
						disabled={ currentPage === 0 }
						onClick={ () => setPage( currentPage - 1 ) }
					>
						← Previous
					</button>
					<span className="pl-table-card__page-indicator pl-mono">
						Page { currentPage + 1 } of { pageCount }
					</span>
					<button
						type="button"
						className="pl-table-card__page-btn pl-mono"
						disabled={ currentPage >= pageCount - 1 }
						onClick={ () => setPage( currentPage + 1 ) }
					>
						Next →
					</button>
				</div>
			) }
		</div>
	);
}
