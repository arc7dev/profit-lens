import { useEffect, useRef } from '@wordpress/element';

import CostBreakdown from './CostBreakdown';

/**
 * Same swap-or-mount pattern as ProSection.jsx, applied to the Cost
 * Breakdown card instead of the upsell card: when Pro has ad spend data
 * on file, Free's own Cost Breakdown card steps aside and hands the same
 * slot to Pro's extended version (Free's own cost items plus an Ad Spend
 * row and a combined total) instead of rendering two separate "costs"
 * cards with two disagreeing totals on screen at once.
 *
 * Deliberately NOT the other way around (Free fetching ad spend and
 * building the extended list itself) — that would mean Free's engine or
 * its React tree needs to know ad spend exists at all, which is exactly
 * what the free/pro boundary says it shouldn't.
 *
 * @param {Object}                                                              props
 * @param {Array<{key:string,label:string,amount:number,is_estimated:boolean}>} props.items Free's own cost_breakdown, unchanged — forwarded to Pro as-is when it takes over this slot.
 * @param {{key:string,label:string,after:string,before:string}}                props.range Currently selected period, forwarded alongside items so Pro's fetch matches what's on screen.
 */
export default function CostBreakdownSlot( { items, range } ) {
	const hasAdSpendData = Boolean( window.profitLensData?.hasAdSpendData );
	const mountRef = useRef( null );

	useEffect( () => {
		if ( hasAdSpendData ) {
			window.profitLensPro?.mountCostBreakdown?.( mountRef.current, {
				items,
				range,
			} );
		}
	}, [ hasAdSpendData, items, range ] );

	if ( hasAdSpendData ) {
		return <div className="pl-card" ref={ mountRef } />;
	}

	return <CostBreakdown items={ items } />;
}
