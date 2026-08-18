import { useMemo, useState } from '@wordpress/element';

import { formatCurrency } from '../utils/currency';

const COLUMNS = [
	{ key: 'name', label: 'Product', align: 'left' },
	{ key: 'units', label: 'Units', align: 'right' },
	{ key: 'revenue', label: 'Revenue', align: 'right' },
	{ key: 'cost', label: 'Cost', align: 'right' },
	{ key: 'profit', label: 'Net Profit', align: 'right' },
	{ key: 'margin_pct', label: 'Margin %', align: 'right' },
];

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
 * of exactly the search+sort logic below. At the catalog sizes this
 * product actually targets (a few hundred to a few thousand products for
 * the Small-tier store this plugin is built for — see CLAUDE.md), that
 * payload is at most a few hundred KB of JSON, which is cheap to hold in
 * memory and slice/sort/filter instantly in the browser. It also comes
 * for free with the one requirement server-side pagination would have
 * fought: sorting by a column has to reorder the ENTIRE 313-product set,
 * not just whichever 20-25 rows happen to be on screen — with the full
 * array already in hand, that's just Array.prototype.sort() over
 * `products`, no extra request. Revisit if a store's catalog ever grows
 * large enough that the payload itself becomes the bottleneck — that's a
 * different problem (shrinking the response) from the one pagination here
 * solves (not rendering 313 <tr>s at once).
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
				<div className="pl-table-card__actions">
					<input
						type="search"
						className="pl-table-card__search pl-mono"
						placeholder="Search products…"
						value={ search }
						onChange={ ( e ) =>
							handleSearchChange( e.target.value )
						}
					/>
					<button
						type="button"
						className="pl-table-card__export pl-mono"
					>
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
					<thead>
						<tr>
							{ COLUMNS.map( ( col ) => (
								<th
									key={ col.key }
									className={
										col.align === 'left'
											? 'pl-table__col--label'
											: ''
									}
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
									<td className="pl-table__col--label">
										{ row.name }
									</td>
									<td>{ row.units }</td>
									<td>
										{ formatCurrency( row.revenue, 2 ) }
									</td>
									<td>{ formatCurrency( row.cost, 2 ) }</td>
									{ row.has_cost ? (
										<td
											className={
												isLoss
													? 'pl-table__profit--loss'
													: 'pl-table__profit--profit'
											}
										>
											{ formatCurrency( row.profit, 2 ) }
										</td>
									) : (
										<td>
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
							<td className="pl-table__col--label">
								<span className="pl-table__totals-label">
									Total
								</span>
							</td>
							<td>{ totals.units }</td>
							<td>{ formatCurrency( totals.revenue, 2 ) }</td>
							<td>{ formatCurrency( totals.cost, 2 ) }</td>
							<td className="pl-table__profit--profit">
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
