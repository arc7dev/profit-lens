import { _n, sprintf } from '@wordpress/i18n';

import { formatForeignCurrency } from '../utils/currency';

/**
 * Issue #42: orders in a currency other than the store's base currency are
 * excluded from every figure on the dashboard — WooCommerce doesn't store
 * an exchange rate per order, so converting isn't possible, only excluding
 * and disclosing. One line per excluded currency, reusing CostCoverageNotice's
 * bare `.pl-coverage` block (no severity modifier) on purpose: same
 * reasoning as that component's own snapshot-provenance line — this is
 * informational, not a warning, and CLAUDE.md's rule against alarm-colored
 * cost/data-scope information applies here too. Not a merged component with
 * CostCoverageNotice: cost coverage is "can this number be trusted", this is
 * "is this number even complete" — different questions, same visual
 * language.
 *
 * @param {Object}                                                                             props
 * @param {{base_currency:string,items:Array<{currency:string,orders:number,revenue:number}>}} props.currencyExclusions
 */
export default function CurrencyExclusionNotice( { currencyExclusions } ) {
	if (
		! currencyExclusions ||
		! currencyExclusions.items ||
		0 === currencyExclusions.items.length
	) {
		return null;
	}

	const { base_currency: baseCurrency, items } = currencyExclusions;

	return (
		<>
			{ items.map( ( item ) => (
				<div className="pl-coverage" key={ item.currency }>
					<span className="pl-coverage__text">
						{ sprintf(
							/* translators: 1: number of orders, 2: currency code (e.g. EUR), 3: formatted amount in that currency, 4: store's base currency code */
							_n(
								'%1$d order in %2$s (%3$s) isn’t included. Profit Lens calculates profit in your store currency (%4$s).',
								'%1$d orders in %2$s (%3$s) aren’t included. Profit Lens calculates profit in your store currency (%4$s).',
								item.orders,
								'profit-lens'
							),
							item.orders,
							item.currency,
							formatForeignCurrency(
								item.revenue,
								item.currency
							),
							baseCurrency
						) }
					</span>
				</div>
			) ) }
		</>
	);
}
