/**
 * Desglose de costos en barras horizontales. Las entradas marcadas
 * is_estimated (p. ej. comisiones de pasarela sin dato exacto) llevan una
 * etiqueta "est." — un costo, no una alarma, así que no usa color de
 * pérdida.
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
					// Misma rampa de opacidad que el diseño de referencia:
					// un solo tono, más tenue mientras más abajo está en la lista.
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
									${ item.amount.toLocaleString() }
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
					${ total.toLocaleString() }
				</span>
			</div>
		</div>
	);
}
