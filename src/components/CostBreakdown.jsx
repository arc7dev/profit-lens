import { formatCurrency } from '../utils/currency';

/**
 * Cost breakdown as horizontal bars. Entries flagged is_estimated (e.g.
 * gateway fees without an exact figure) carry an "est." label — it's a
 * cost, not an alarm, so it doesn't use the loss color.
 *
 * Amounts always render with 2 decimals (unlike the KPI cards, which round
 * to the dollar) — this is a reconciliation view, not a headline figure.
 *
 * @param {Object}                                                              props
 * @param {Array<{key:string,label:string,amount:number,is_estimated:boolean}>} props.items
 */
export default function CostBreakdown( { items } ) {
	const total = items.reduce( ( sum, item ) => sum + item.amount, 0 );

	return (
		<div className="pl-card pl-cost-card">
			<div className="pl-row__label">Cost Breakdown</div>

			<div className="pl-cost-list">
				{ items.map( ( item, i ) => {
					const pct = total > 0 ? ( item.amount / total ) * 100 : 0;
					// Same opacity ramp as the reference design: a single
					// hue, fading further down the list.
					const opacity = [ 1, 0.55, 0.38, 0.22 ][ i ] ?? 0.22;

					return (
						<div key={ item.key }>
							<div className="pl-cost-item__row">
								<span className="pl-cost-item__label">
									{ item.label }
									{ item.is_estimated && (
										<span className="pl-cost-item__estimated">
											est.
										</span>
									) }
								</span>
								<span className="pl-cost-item__amount pl-mono">
									{ formatCurrency( item.amount, 2 ) }
								</span>
							</div>
							<div className="pl-cost-item__track">
								<div
									className="pl-cost-item__fill"
									style={ { width: `${ pct }%`, opacity } }
								/>
							</div>
							<div className="pl-cost-item__pct pl-mono">
								{ pct.toFixed( 1 ) }% of costs
							</div>
						</div>
					);
				} ) }
			</div>

			<div className="pl-cost-total">
				<span className="pl-cost-total__label">Total</span>
				<span className="pl-cost-total__amount pl-mono">
					{ formatCurrency( total, 2 ) }
				</span>
			</div>
		</div>
	);
}
