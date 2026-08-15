/*
 * Data hook — NOT WIRED UP YET. Dashboard.jsx currently reads from
 * src/data/mock.js directly; this file documents the replacement that
 * lands once the calculation engine exists.
 *
 * When the engine gets wired up, the change is: Dashboard.jsx imports
 * `useSummary` instead of `getMockSummary`, and the rest of the components
 * don't change — they consume the same shape (see the contract in
 * includes/class-rest-controller.php).
 *
 * Future implementation, sketched out (not active yet):
 *
 *     import apiFetch from '@wordpress/api-fetch';
 *     import { useState, useEffect } from '@wordpress/element';
 *
 *     export function useSummary( rangeKey ) {
 *         const [ state, setState ] = useState( { isLoading: true, data: null, error: null } );
 *
 *         useEffect( () => {
 *             setState( { isLoading: true, data: null, error: null } );
 *
 *             apiFetch( { path: `/${ window.profitLensData.restNamespace }/summary?range=${ rangeKey }` } )
 *                 .then( ( data ) => setState( { isLoading: false, data, error: null } ) )
 *                 .catch( ( error ) => setState( { isLoading: false, data: null, error } ) );
 *         }, [ rangeKey ] );
 *
 *         return state;
 *     }
 */

/**
 * @param {string} rangeKey '7d' | '30d' | 'month' | 'custom'.
 */
export function useSummary( rangeKey ) {
	throw new Error(
		`useSummary( "${ rangeKey }" ) isn't wired up to the calculation engine yet. Use src/data/mock.js in the meantime.`
	);
}
