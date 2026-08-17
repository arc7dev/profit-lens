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
 * @param {'7d'|'30d'|'month'|'custom'} rangeKey
 * @return {{isLoading: boolean, data: Object|null, error: Object|null}} Current fetch state for the range.
 */
export function useSummary( rangeKey ) {
	const [ state, setState ] = useState( {
		isLoading: true,
		data: null,
		error: null,
	} );

	useEffect( () => {
		let isCurrent = true;

		setState( { isLoading: true, data: null, error: null } );

		apiFetch( {
			path: `/${ window.profitLensData.restNamespace }/summary?range=${ rangeKey }`,
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
	}, [ rangeKey ] );

	return state;
}
