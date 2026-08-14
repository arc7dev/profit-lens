/*
 * Hook de datos — TODAVÍA NO CONECTADO. Por ahora Dashboard.jsx lee de
 * src/data/mock.js directamente; este archivo documenta el reemplazo que
 * entra cuando el motor de cálculo exista.
 *
 * Al conectar el motor, el cambio es: Dashboard.jsx importa `useSummary`
 * en vez de `getMockSummary`, y el resto de los componentes no se toca —
 * consumen el mismo shape (ver el contrato en
 * includes/class-rest-controller.php).
 *
 * Implementación futura, esquemática (no activa todavía):
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
		`useSummary( "${ rangeKey }" ) todavía no está conectado al motor de cálculo. Usá src/data/mock.js mientras tanto.`
	);
}
