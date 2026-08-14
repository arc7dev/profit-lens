import { useState } from '@wordpress/element';

const COLUMNS = [
	{ key: 'name', label: 'Product', align: 'left' },
	{ key: 'units', label: 'Units', align: 'right' },
	{ key: 'revenue', label: 'Revenue', align: 'right' },
	{ key: 'cost', label: 'Cost', align: 'right' },
	{ key: 'profit', label: 'Net Profit', align: 'right' },
	{ key: 'margin_pct', label: 'Margin %', align: 'right' },
];

function marginClass( row ) {
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
 * Tabla de "Profit by Product": ordenable por columna, con fila de totales.
 *
 * @param {Object}                                                                    props
 * @param {Array}                                                                     props.products
 * @param {{units:number,revenue:number,cost:number,profit:number,margin_pct:number}} props.totals
 * @param {string}                                                                    props.rangeLabel
 */
export default function ProductTable( { products, totals, rangeLabel } ) {
	const [ sortKey, setSortKey ] = useState( 'profit' );
	const [ sortDir, setSortDir ] = useState( 'desc' );

	function handleSort( key ) {
		if ( key === sortKey ) {
			setSortDir( ( dir ) => ( dir === 'asc' ? 'desc' : 'asc' ) );
		} else {
			setSortKey( key );
			setSortDir( 'desc' );
		}
	}

	const sorted = [ ...products ].sort( ( a, b ) => {
		const av = a[ sortKey ];
		const bv = b[ sortKey ];

		if ( typeof av === 'string' ) {
			return sortDir === 'asc'
				? av.localeCompare( bv )
				: bv.localeCompare( av );
		}

		return sortDir === 'asc' ? av - bv : bv - av;
	} );

	return (
		<div className="pl-card pl-table-card">
			<div className="pl-table-card__header">
				<div className="pl-table-card__title">Profit by Product</div>
				<div className="pl-table-card__actions">
					<button
						type="button"
						className="pl-table-card__export pl-mono"
					>
						Export CSV
					</button>
					<div className="pl-table-card__count pl-mono">
						{ products.length } products · { rangeLabel }
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
						{ sorted.map( ( row ) => {
							const isLoss = row.profit < 0;

							return (
								<tr key={ row.id }>
									<td className="pl-table__col--label">
										{ row.name }
									</td>
									<td>{ row.units }</td>
									<td>${ row.revenue.toLocaleString() }</td>
									<td>${ row.cost.toLocaleString() }</td>
									<td
										className={
											isLoss
												? 'pl-table__profit--loss'
												: 'pl-table__profit--profit'
										}
									>
										{ isLoss
											? `−$${ Math.abs(
													row.profit
											  ).toLocaleString() }`
											: `$${ row.profit.toLocaleString() }` }
									</td>
									<td className={ marginClass( row ) }>
										{ row.margin_pct.toFixed( 1 ) }%
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
							<td>${ totals.revenue.toLocaleString() }</td>
							<td>${ totals.cost.toLocaleString() }</td>
							<td className="pl-table__profit--profit">
								${ totals.profit.toLocaleString() }
							</td>
							<td>{ totals.margin_pct.toFixed( 1 ) }%</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	);
}
