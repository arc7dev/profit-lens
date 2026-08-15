/**
 * Example data with the same shape returned by GET /profit-lens/v1/summary
 * (see ProfitLens_REST_Controller::build_ready_response() in PHP — same
 * numbers, same shape). Used while the calculation engine doesn't exist
 * yet; once useSummary() replaces this file with the real fetch, the
 * components don't change.
 */

const PRODUCTS = [
	{
		id: 1,
		name: 'Merino Wool Crew Neck',
		units: 47,
		revenue: 2180.0,
		cost: 1090.0,
		profit: 784.0,
		margin_pct: 36.0,
	},
	{
		id: 2,
		name: 'Canvas Tote — Natural',
		units: 62,
		revenue: 1860.0,
		cost: 870.0,
		profit: 729.0,
		margin_pct: 39.2,
	},
	{
		id: 3,
		name: 'Organic Cotton Tee',
		units: 82,
		revenue: 1640.0,
		cost: 1140.0,
		profit: 270.0,
		margin_pct: 16.5,
	},
	{
		id: 4,
		name: 'Leather Card Holder',
		units: 31,
		revenue: 1240.0,
		cost: 600.0,
		profit: 466.0,
		margin_pct: 37.6,
	},
	{
		id: 5,
		name: 'Recycled Denim Jacket',
		units: 10,
		revenue: 1150.0,
		cost: 860.0,
		profit: 129.0,
		margin_pct: 11.2,
	},
	{
		id: 6,
		name: 'Bamboo Knit Socks (3pk)',
		units: 44,
		revenue: 880.0,
		cost: 590.0,
		profit: 166.0,
		margin_pct: 18.9,
	},
	{
		id: 7,
		name: 'Linen Shirt — Off White',
		units: 10,
		revenue: 620.0,
		cost: 430.0,
		profit: 103.0,
		margin_pct: 16.6,
	},
	{
		id: 8,
		name: 'Waxed Canvas Backpack',
		units: 6,
		revenue: 454.0,
		cost: 690.0,
		profit: -300.0,
		margin_pct: -66.1,
	},
];

const COST_BREAKDOWN = [
	{
		key: 'product_cost',
		label: 'Product Cost',
		amount: 6270.0,
		is_estimated: false,
	},
	{ key: 'shipping', label: 'Shipping', amount: 487.0, is_estimated: false },
	{ key: 'refunds', label: 'Refunds', amount: 599.0, is_estimated: false },
	{
		key: 'gateway_fees',
		label: 'Gateway Fees',
		amount: 321.0,
		is_estimated: true,
	},
];

const COST_COVERAGE = {
	products_with_cost: 2612,
	products_total: 2800,
	pct: 93.3,
	revenue_covered_pct: 97.1,
	revenue_uncovered: 291.0,
};

const INSIGHT = {
	type: 'loss_making_product',
	message:
		'Waxed Canvas Backpack lost $300 this period — 6 units sold below cost.',
	product_id: 8,
};

const RANGES = {
	'7d': {
		label: 'Aug 7 – Aug 13, 2026',
		after: '2026-08-07',
		before: '2026-08-13',
		orders: 14,
		revenue: 2847.0,
		costs: 2190.0,
		net_profit: 657.0,
		margin_pct: 23.1,
		change_pct: 8.2,
		days: [
			'Aug 7',
			'Aug 8',
			'Aug 9',
			'Aug 10',
			'Aug 11',
			'Aug 12',
			'Aug 13',
		],
		values: [ 68, 134, 112, 89, 78, 95, 81 ],
	},
	'30d': {
		label: 'Jul 15 – Aug 13, 2026',
		after: '2026-07-15',
		before: '2026-08-13',
		orders: 292,
		revenue: 10024.0,
		costs: 6270.0,
		net_profit: 2347.0,
		margin_pct: 23.4,
		change_pct: 12.4,
		days: [
			'Jul 15',
			'Jul 18',
			'Jul 21',
			'Jul 24',
			'Jul 27',
			'Jul 30',
			'Aug 2',
			'Aug 5',
			'Aug 8',
			'Aug 11',
			'Aug 13',
		],
		values: [ 54, 98, 112, 134, 89, 201, 143, 178, 220, 262, 247 ],
	},
	month: {
		label: 'Aug 1 – Aug 13, 2026',
		after: '2026-08-01',
		before: '2026-08-13',
		orders: 47,
		revenue: 4312.0,
		costs: 3290.0,
		net_profit: 1022.0,
		margin_pct: 23.7,
		change_pct: 4.1,
		days: [
			'Aug 1',
			'Aug 3',
			'Aug 5',
			'Aug 7',
			'Aug 9',
			'Aug 11',
			'Aug 13',
		],
		values: [ 68, 112, 201, 143, 178, 198, 247 ],
	},
	custom: {
		label: 'Jun 1 – Jul 14, 2026',
		after: '2026-06-01',
		before: '2026-07-14',
		orders: 209,
		revenue: 19880.0,
		costs: 15240.0,
		net_profit: 4640.0,
		margin_pct: 23.3,
		change_pct: 9.7,
		days: [
			'Jun 1',
			'Jun 8',
			'Jun 15',
			'Jun 22',
			'Jun 29',
			'Jul 6',
			'Jul 14',
		],
		values: [ 110, 180, 210, 154, 290, 240, 310 ],
	},
};

/**
 * Builds a "ready" response with the same shape as the REST endpoint, for
 * a given range.
 *
 * @param {'7d'|'30d'|'month'|'custom'} rangeKey
 * @return {Object} Response with the same shape as GET /profit-lens/v1/summary.
 */
export function getMockSummary( rangeKey = '30d' ) {
	const range = RANGES[ rangeKey ] || RANGES[ '30d' ];

	const chartSeries = range.days.map( ( label, i ) => ( {
		date: null,
		label,
		profit: range.values[ i ],
	} ) );

	return {
		range: {
			key: rangeKey,
			label: range.label,
			after: range.after,
			before: range.before,
		},
		status: 'ready',
		kpis: {
			net_profit: {
				amount: range.net_profit,
				currency: 'USD',
				change_pct: range.change_pct,
			},
			net_margin_pct: range.margin_pct,
			revenue: {
				amount: range.revenue,
				currency: 'USD',
				orders_count: range.orders,
			},
			total_costs: {
				amount: range.costs,
				currency: 'USD',
			},
		},
		insight: INSIGHT,
		cost_coverage: COST_COVERAGE,
		chart: { series: chartSeries },
		cost_breakdown: COST_BREAKDOWN,
		products: PRODUCTS,
		products_meta: {
			total: PRODUCTS.length,
			totals: {
				units: range.orders,
				revenue: range.revenue,
				cost: range.costs,
				profit: range.net_profit,
				margin_pct: range.margin_pct,
			},
		},
	};
}

/**
 * "empty" response — product costs not configured yet.
 *
 * @param {'7d'|'30d'|'month'|'custom'} rangeKey
 * @return {Object} Response with status "empty" and the same shape as the REST endpoint.
 */
export function getMockEmptySummary( rangeKey = '30d' ) {
	const range = RANGES[ rangeKey ] || RANGES[ '30d' ];

	return {
		range: {
			key: rangeKey,
			label: range.label,
			after: range.after,
			before: range.before,
		},
		status: 'empty',
		kpis: null,
		insight: null,
		cost_coverage: {
			products_with_cost: 0,
			products_total: 0,
			pct: 0,
			revenue_covered_pct: 0,
			revenue_uncovered: 0,
		},
		chart: { series: [] },
		cost_breakdown: [],
		products: [],
		products_meta: {
			total: 0,
			totals: { units: 0, revenue: 0, cost: 0, profit: 0, margin_pct: 0 },
		},
	};
}

export const RANGE_KEYS = Object.keys( RANGES );
