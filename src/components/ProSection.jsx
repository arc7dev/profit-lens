import { useEffect, useRef, useState } from '@wordpress/element';

import ProUpgradeModal from './ProUpgradeModal';

/**
 * Blurred preview of the Pro metrics (ROAS by campaign, ad spend vs.
 * profit) with an upsell overlay. This is NOT gated functionality in the
 * WordPress.org Guideline 5 sense — it's a visual preview of something
 * that requires connecting an external account (Meta/Google Ads), not a
 * calculation the plugin already did and is hiding.
 *
 * Campaign data is hardcoded, only for the blurred preview; it's never
 * calculated or shown in full in the free plugin.
 *
 * Once Pro actually has ad spend data on file (profitLensData.
 * hasAdSpendData, see class-assets.php's profitlens_has_ad_spend_data
 * filter), this upsell is pointless — the merchant already owns the
 * feature it's advertising. In that case this renders an empty mount
 * div instead and hands it to Pro's own bridge script
 * (window.profitLensPro.mountProfitAfterAdSpend(), assets/js/
 * profit-after-ad-spend.js in profit-lens-pro/ — same
 * enqueue-on-Free's-screen-only pattern as the existing Export CSV
 * bridge, ProductTable.jsx/class-assets-pro.php's
 * enqueue_export_bridge()) to fill in. Free never renders Pro's actual
 * content itself — same reason it doesn't scrape/duplicate Pro's own
 * React tree for the export bridge either.
 */
const CAMPAIGNS = [
	{ label: 'Meta — Retargeting', roas: 4.2, spend: 340 },
	{ label: 'Google Shopping — All Products', roas: 2.1, spend: 580 },
	{ label: 'Meta — Lookalike Audience', roas: 3.8, spend: 210 },
	{ label: 'Google Search — Brand', roas: 6.7, spend: 95 },
];

export default function ProSection() {
	const totalSpend = CAMPAIGNS.reduce( ( sum, c ) => sum + c.spend, 0 );
	const [ showProModal, setShowProModal ] = useState( false );
	// Set only when Profit Lens Pro is active AND its own license is
	// valid (see ProfitLensPro_Plugin::provide_csv_import_url()) — Free
	// never checks for Pro itself, it only reads whatever this filter
	// resolved to server-side (class-assets.php).
	const csvImportUrl = window.profitLensData?.csvImportUrl ?? '';
	// Same shape/rationale as csvImportUrl above: false unless Pro is
	// active, licensed, AND actually has ad spend data on file (see
	// ProfitLensPro_Ad_Spend_Registry::has_connected_source()) —
	// Pro's own filter callback already folds the license check in, so
	// this is the one flag this component needs to branch on.
	const hasAdSpendData = Boolean( window.profitLensData?.hasAdSpendData );
	const mountRef = useRef( null );

	useEffect( () => {
		if ( hasAdSpendData ) {
			window.profitLensPro?.mountProfitAfterAdSpend?.( mountRef.current );
		}
	}, [ hasAdSpendData ] );

	if ( hasAdSpendData ) {
		// Bare .pl-card only (not .pl-pro — that class exists for this
		// component's own blur+overlay upsell trick, meaningless without
		// the specific child markup it's built against). Padding/layout
		// for whatever's inside is Pro's own bridge script's job, not
		// something Free pre-supplies for content it doesn't know the
		// shape of.
		return <div className="pl-card" ref={ mountRef } />;
	}

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
				{ csvImportUrl ? (
					<a href={ csvImportUrl } className="pl-pro__cta pl-mono">
						Upload CSV
					</a>
				) : (
					<button
						type="button"
						className="pl-pro__cta pl-mono"
						data-pro-href="https://arc7.dev/profit-lens/pro"
						onClick={ () => setShowProModal( true ) }
					>
						Connect Meta &amp; Google Ads
					</button>
				) }
			</div>

			<ProUpgradeModal
				isOpen={ showProModal }
				onClose={ () => setShowProModal( false ) }
				feature="Ads Integration"
			/>
		</div>
	);
}
