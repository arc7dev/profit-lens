/**
 * Currency formatting, driven entirely by WooCommerce's own configured
 * format (symbol, decimal/thousand separators, symbol position — localized
 * once per page load via window.profitLensData, see class-assets.php).
 * Never hardcode "$" or rely on Number.prototype.toLocaleString(): the
 * browser's default locale has nothing to do with the store's currency
 * settings, and a store using a comma as the decimal separator (common
 * outside the US) would render "$39,891.8" as if it meant something
 * completely different from what the merchant configured.
 *
 * Two fixed decimal rules apply across the dashboard (not WooCommerce's own
 * configured precision, which this deliberately overrides):
 * - KPI cards (large, headline figures): 0 decimals — cents are noise at
 *   that scale, and rounding to the nearest dollar there does not lose any
 *   accuracy the merchant would act on.
 * - Everything else (table rows/totals, cost breakdown): 2 decimals,
 *   always — those are the numbers a merchant reconciles against an order
 *   or an invoice, where a rounded-off decimal actually hides information.
 *
 * @package
 */

/**
 * @param {number} amount   Signed amount, in the store's currency.
 * @param {number} decimals Decimal places to render — pass explicitly at
 *                          every call site (0 for KPIs, 2 for table/
 *                          breakdown figures) rather than relying on a
 *                          default, so the two rules above can't drift.
 * @return {string} e.g. "$29,459", "−$517.11", "39.891,80 €" depending on
 *                   the store's configured symbol/separators/position.
 */
export function formatCurrency( amount, decimals ) {
	const config = window.profitLensData || {};
	const symbol = config.currencySymbol ?? '$';
	const decimalSeparator = config.decimalSeparator ?? '.';
	const thousandSeparator = config.thousandSeparator ?? ',';
	const position = config.currencyPosition ?? 'left';

	const isNegative = amount < 0;
	const fixed = Math.abs( amount ).toFixed( decimals );
	const [ intPart, decPart ] = fixed.split( '.' );
	const withThousands = intPart.replace(
		/\B(?=(\d{3})+(?!\d))/g,
		thousandSeparator
	);
	const number = decPart
		? `${ withThousands }${ decimalSeparator }${ decPart }`
		: withThousands;

	let formatted;
	switch ( position ) {
		case 'right':
			formatted = `${ number }${ symbol }`;
			break;
		case 'left_space':
			formatted = `${ symbol } ${ number }`;
			break;
		case 'right_space':
			formatted = `${ number } ${ symbol }`;
			break;
		case 'left':
		default:
			formatted = `${ symbol }${ number }`;
	}

	// The minus sign always goes in front of the whole thing, regardless of
	// symbol position — "−$99.99", never "$−99.99" or "99.99−$" — matching
	// the convention already used for loss figures across the dashboard
	// (ProfitChart's tooltip, the old ad hoc formatCurrency() in
	// Dashboard.jsx this replaces).
	return isNegative ? `−${ formatted }` : formatted;
}
