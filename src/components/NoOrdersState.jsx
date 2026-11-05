import { __, _n, sprintf } from '@wordpress/i18n';

import { formatForeignCurrency } from '../utils/currency';

/**
 * "No orders in this period" — a legitimate ready state (costs ARE
 * configured, status: "ready") that just had zero qualifying orders in
 * the requested range. Deliberately distinct from EmptyState
 * (status: "empty", meaning no product anywhere has a cost defined): an
 * all-zero dashboard with no explanation reads as a broken plugin, but
 * "no costs configured yet" and "a quiet period" are different problems
 * with different fixes, so they get different messages rather than
 * collapsing into one generic blank state.
 *
 * Issue #42: `orders_count === 0` (what triggers this component at all,
 * see Dashboard.jsx) collapses two different situations into one number —
 * a genuinely quiet period, and a period where every order that DID exist
 * was excluded for being in another currency. Showing "nothing sold" for
 * the second case is actively misleading (orders existed, this dashboard
 * just can't include them), so `currencyExclusions` — the same prop
 * CurrencyExclusionNotice reads — branches the copy instead of silently
 * reusing the first message for both. Copy doesn't assume a
 * multi-currency plugin as the cause: the same wording is accurate
 * whether that's it, or the store simply changed its base currency at
 * some point and older orders are still recorded in the old one.
 */

function NoticeIcon() {
	return (
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
	);
}

export default function NoOrdersState( {
	rangeLabel,
	onExpandRange,
	currencyExclusions,
} ) {
	const items =
		currencyExclusions && currencyExclusions.items
			? currencyExclusions.items
			: [];

	if ( items.length > 0 ) {
		const totalExcludedOrders = items.reduce(
			( sum, item ) => sum + item.orders,
			0
		);

		const perCurrency = items
			.map( ( item ) =>
				sprintf(
					/* translators: 1: order count, 2: currency code, 3: formatted amount in that currency */
					_n(
						'%1$d order in %2$s (%3$s)',
						'%1$d orders in %2$s (%3$s)',
						item.orders,
						'profit-lens'
					),
					item.orders,
					item.currency,
					formatForeignCurrency( item.revenue, item.currency )
				)
			)
			.join( ', ' );

		return (
			<div className="pl-notice-wrap">
				<div className="pl-notice__card">
					<div className="pl-notice__icon" aria-hidden="true">
						<NoticeIcon />
					</div>

					<h2 className="pl-notice__title">
						{ sprintf(
							/* translators: %d: number of orders */
							_n(
								'%d order in this period is in another currency',
								'%d orders in this period are in another currency',
								totalExcludedOrders,
								'profit-lens'
							),
							totalExcludedOrders
						) }
					</h2>

					<p className="pl-notice__copy">
						{ sprintf(
							/* translators: 1: per-currency breakdown (e.g. "3 orders in EUR (€450.00)"), 2: store's base currency code */
							__(
								"Profit Lens doesn't convert between currencies: %1$s aren't included. Totals here are in your store currency (%2$s).",
								'profit-lens'
							),
							perCurrency,
							currencyExclusions.base_currency
						) }
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="pl-notice-wrap">
			<div className="pl-notice__card">
				<div className="pl-notice__icon" aria-hidden="true">
					<NoticeIcon />
				</div>

				<h2 className="pl-notice__title">
					{ __( 'No orders in this period', 'profit-lens' ) }
				</h2>

				<p className="pl-notice__copy">
					{ rangeLabel
						? sprintf(
								/* translators: %s: date range label */
								__(
									'Nothing sold between %s. Try a wider date range.',
									'profit-lens'
								),
								rangeLabel
						  )
						: __(
								'Nothing sold in this date range. Try a wider one.',
								'profit-lens'
						  ) }
				</p>

				{ onExpandRange && (
					<button
						type="button"
						className="pl-notice__cta pl-mono"
						onClick={ onExpandRange }
					>
						{ __( 'View last 30 days', 'profit-lens' ) }
					</button>
				) }
			</div>
		</div>
	);
}
