/**
 * Fetches GET /profit-lens/v1/summary for a given range. `@wordpress/api-fetch`
 * picks up the REST root and nonce from `window.wpApiSettings`
 * (class-assets.php localizes it via rest_url(), which resolves to the
 * right URL form — pretty `/wp-json/...` or the `?rest_route=` fallback —
 * for whatever permalink structure the site actually has; this hook never
 * hardcodes either form itself).
 */

import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

/**
 * @param {'7d'|'30d'|'month'|'custom'}          rangeKey
 * @param {{after: string, before: string}|null} [customRange] Required (and only used) when rangeKey === 'custom' — Y-m-d dates.
 * @return {{isLoading: boolean, data: Object|null, error: Object|null}} Current fetch state for the range.
 */
export function useSummary( rangeKey, customRange = null ) {
	const [ state, setState ] = useState( {
		isLoading: true,
		data: null,
		error: null,
	} );

	// 'custom' without a chosen range yet (the picker hasn't been applied)
	// has nothing to fetch — CustomRangePicker's trigger button covers this
	// case in the UI; the hook just stays out of an unnecessary/invalid
	// request rather than asking the endpoint to fall back to 30d behind
	// the user's back (see class-rest-controller.php's get_range_bounds()
	// custom-range fallback — that fallback is for a malformed request, not
	// meant to be relied on from a well-behaved client).
	const isCustomWithoutRange =
		'custom' === rangeKey &&
		! ( customRange?.after && customRange?.before );

	useEffect( () => {
		if ( isCustomWithoutRange ) {
			setState( { isLoading: false, data: null, error: null } );
			return;
		}

		let isCurrent = true;

		setState( { isLoading: true, data: null, error: null } );

		const query =
			'custom' === rangeKey
				? `range=custom&after=${ customRange.after }&before=${ customRange.before }`
				: `range=${ rangeKey }`;

		apiFetch( {
			path: `/${ window.profitLensData.restNamespace }/summary?${ query }`,
		} )
			.then( ( data ) => {
				if ( isCurrent ) {
					setState( { isLoading: false, data, error: null } );
				}
			} )
			.catch( ( error ) => {
				if ( isCurrent ) {
					setState( { isLoading: false, data: null, error } );
				}
			} );

		// Avoids setting state from a request for a range the user has
		// since navigated away from (e.g. clicking 7d then 30d quickly) —
		// without this, whichever request resolves LAST wins, even if it
		// was for a range that's no longer selected.
		return () => {
			isCurrent = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps -- customRange is destructured into the two primitives the effect actually depends on, deliberately, so a new-but-equal {after,before} object each render doesn't re-fetch.
	}, [
		rangeKey,
		customRange?.after,
		customRange?.before,
		isCustomWithoutRange,
	] );

	return state;
}
