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

/**
 * Formats an amount in a currency OTHER than the store's own — issue #42's
 * currency-exclusion notice, the one place this dashboard shows a figure
 * that isn't in the store's base currency. formatCurrency() above can't do
 * this: it's hardwired to WooCommerce's configured symbol/separators for
 * the store's ONE currency (window.profitLensData), which has no symbol or
 * separator rules for any other ISO code. Intl.NumberFormat does, for any
 * valid currency code, without this plugin maintaining its own table of
 * currency symbols/decimal conventions.
 *
 * @param {number} amount       Unsigned amount, in `currencyCode`.
 * @param {string} currencyCode ISO 4217 code, e.g. "EUR" — same value
 *                              WooCommerce's $order->get_currency() and
 *                              this endpoint's currency_exclusions.items[].currency
 *                              already use.
 * @return {string} e.g. "€1,234.50", "CA$80.00" — formatted per the
 *                   currency's own real-world convention, not the store's.
 */
export function formatForeignCurrency( amount, currencyCode ) {
	const locale = window.profitLensData?.locale || 'en-US';

	try {
		return new Intl.NumberFormat( locale, {
			style: 'currency',
			currency: currencyCode,
		} ).format( amount );
	} catch ( error ) {
		// An invalid/unrecognized locale (malformed get_user_locale()
		// output on some site, for instance) throws a RangeError —
		// Intl.NumberFormat gives no "unsupported locale" fallback of
		// its own, unlike an unsupported currency code, which it
		// already degrades gracefully for instead of throwing. Retry
		// once with the same fallback formatCurrency() above never
		// needs, since it doesn't go through Intl at all.
		return new Intl.NumberFormat( 'en-US', {
			style: 'currency',
			currency: currencyCode,
		} ).format( amount );
	}
}
