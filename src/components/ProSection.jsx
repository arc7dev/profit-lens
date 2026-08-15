/**
 * Blurred preview of the Pro metrics (ROAS by campaign, ad spend vs.
 * profit) with an upsell overlay. This is NOT gated functionality in the
 * WordPress.org Guideline 5 sense — it's a visual preview of something
 * that requires connecting an external account (Meta/Google Ads), not a
 * calculation the plugin already did and is hiding.
 *
 * Campaign data is hardcoded, only for the blurred preview; it's never
 * calculated or shown in full in the free plugin.
 */
const CAMPAIGNS = [
	{ label: 'Meta — Retargeting', roas: 4.2, spend: 340 },
	{ label: 'Google Shopping — All Products', roas: 2.1, spend: 580 },
	{ label: 'Meta — Lookalike Audience', roas: 3.8, spend: 210 },
	{ label: 'Google Search — Brand', roas: 6.7, spend: 95 },
];

export default function ProSection() {
	const totalSpend = CAMPAIGNS.reduce( ( sum, c ) => sum + c.spend, 0 );

	return (
		<div className="pl-card pl-pro">
			<div className="pl-pro__preview" aria-hidden="true">
				<div className="pl-pro__col">
					<div className="pl-pro__col-label">ROAS by Campaign</div>
					{ CAMPAIGNS.map( ( c ) => (
						<div className="pl-pro__campaign" key={ c.label }>
							<span className="pl-pro__campaign-name">
								{ c.label }
							</span>
							<div className="pl-pro__campaign-track">
								<div
									className="pl-pro__campaign-fill"
									style={ {
										width: `${ ( c.roas / 7 ) * 100 }%`,
									} }
								/>
							</div>
							<span className="pl-pro__campaign-value pl-mono">
								{ c.roas }× ROAS
							</span>
						</div>
					) ) }
				</div>
				<div className="pl-pro__col">
					<div className="pl-pro__col-label">Ad Spend vs Profit</div>
					{ CAMPAIGNS.map( ( c ) => (
						<div className="pl-pro__campaign" key={ c.label }>
							<span className="pl-pro__campaign-name">
								{ c.label }
							</span>
							<span className="pl-mono">${ c.spend } spend</span>
						</div>
					) ) }
					<div className="pl-cost-total">
						<span className="pl-cost-total__label">
							Total ad spend
						</span>
						<span className="pl-cost-total__amount pl-mono">
							${ totalSpend }
						</span>
					</div>
				</div>
			</div>

			<div className="pl-pro__overlay">
				<div className="pl-pro__eyebrow pl-mono">Pro feature</div>
				<div className="pl-pro__title">Profit after ad spend</div>
				<p className="pl-pro__copy">
					See true profit per campaign once ad spend is subtracted —
					so you know which channels actually pay.
				</p>
				<a
					className="pl-pro__cta pl-mono"
					href="https://arc7.dev/profit-lens/pro"
					target="_blank"
					rel="noreferrer"
				>
					Connect Meta &amp; Google Ads
				</a>
			</div>
		</div>
	);
}
