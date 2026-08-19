/**
 * "No orders in this period" — a legitimate ready state (costs ARE
 * configured, status: "ready") that just had zero qualifying orders in
 * the requested range. Deliberately distinct from EmptyState
 * (status: "empty", meaning no product anywhere has a cost defined): an
 * all-zero dashboard with no explanation reads as a broken plugin, but
 * "no costs configured yet" and "a quiet period" are different problems
 * with different fixes, so they get different messages rather than
 * collapsing into one generic blank state.
 */

export default function NoOrdersState( { rangeLabel, onExpandRange } ) {
	return (
		<div className="pl-notice-wrap">
			<div className="pl-notice__card">
				<div className="pl-notice__icon" aria-hidden="true">
					<svg width="36" height="36" viewBox="0 0 36 36" fill="none">
						<rect
							x="6"
							y="10"
							width="24"
							height="20"
							rx="2"
							stroke="#b0c4ce"
							strokeWidth="1.5"
						/>
						<line
							x1="6"
							y1="16"
							x2="30"
							y2="16"
							stroke="#b0c4ce"
							strokeWidth="1.5"
						/>
						<line
							x1="12"
							y1="6"
							x2="12"
							y2="12"
							stroke="#b0c4ce"
							strokeWidth="1.5"
							strokeLinecap="round"
						/>
						<line
							x1="24"
							y1="6"
							x2="24"
							y2="12"
							stroke="#b0c4ce"
							strokeWidth="1.5"
							strokeLinecap="round"
						/>
					</svg>
				</div>

				<h2 className="pl-notice__title">No orders in this period</h2>

				<p className="pl-notice__copy">
					{ rangeLabel
						? `Nothing sold between ${ rangeLabel }. Try a wider date range.`
						: 'Nothing sold in this date range. Try a wider one.' }
				</p>

				{ onExpandRange && (
					<button
						type="button"
						className="pl-notice__cta pl-mono"
						onClick={ onExpandRange }
					>
						View last 30 days
					</button>
				) }
			</div>
		</div>
	);
}
