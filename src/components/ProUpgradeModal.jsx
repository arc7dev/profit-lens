import { Modal } from '@wordpress/components';

/**
 * Reusable Pro upsell modal — lives 100% in Free. Nothing here checks a
 * license or gates functionality this plugin actually implements; it's
 * only ever wired to actions that already require an external account
 * (see CLAUDE.md's Free/Pro line: internal data is Free, an external
 * account is Pro). Pro replaces the caller's onClick with the real
 * action instead of opening this — nothing in this file changes for that.
 *
 * `contentLabel`, not `title`, on the underlying Modal: a visible `title`
 * would render @wordpress/components' own header row above this
 * component's custom icon/title/copy layout, duplicating it. contentLabel
 * still satisfies Modal's accessibility requirement (every modal needs a
 * title for assistive tech) without rendering one.
 *
 * @param {Object}     props
 * @param {boolean}    props.isOpen
 * @param {() => void} props.onClose
 * @param {string}     props.feature Short name of the gated feature, e.g.
 *                                   "Ads Integration" — used in the
 *                                   "Unlock {feature}" subtitle.
 * @param {string}     props.source  One of ProfitLens_Pro_Links::SOURCES
 *                                   (PHP) — looks up the matching
 *                                   pre-built URL in
 *                                   window.profitLensData.proLinks rather
 *                                   than deriving it from `feature`'s
 *                                   display text, which is free-form and
 *                                   not meant to double as an attribution
 *                                   key. Falls back to the 'other' link
 *                                   if the caller passes something
 *                                   outside that set, or if proLinks
 *                                   itself is missing for any reason
 *                                   (e.g. an old cached bundle talking to
 *                                   a newer server isn't expected, but
 *                                   this still shouldn't render a dead
 *                                   link if it happens).
 */
export default function ProUpgradeModal( {
	isOpen,
	onClose,
	feature,
	source,
} ) {
	if ( ! isOpen ) {
		return null;
	}

	const proLinks = window.profitLensData?.proLinks ?? {};
	const upgradeHref =
		proLinks[ source ] ??
		proLinks.other ??
		'https://arc7.dev/profit-lens/pro/';

	return (
		<Modal
			className="pl-pro-modal"
			contentLabel="Profit Lens Pro"
			onRequestClose={ onClose }
		>
			<svg
				className="pl-pro-modal__icon"
				width="32"
				height="32"
				viewBox="0 0 24 24"
				fill="none"
				aria-hidden="true"
			>
				<rect
					x="5"
					y="11"
					width="14"
					height="10"
					rx="2"
					stroke="currentColor"
					strokeWidth="1.6"
				/>
				<path
					d="M8 11V7a4 4 0 0 1 8 0v4"
					stroke="currentColor"
					strokeWidth="1.6"
					strokeLinecap="round"
				/>
			</svg>

			<div className="pl-pro-modal__title">Profit Lens Pro</div>
			<div className="pl-pro-modal__subtitle">Unlock { feature }</div>
			<ul className="pl-pro-modal__features">
				<li>
					Ad spend from a CSV file, or synced from Google Ads (Meta
					Ads coming soon)
				</li>
				<li>Profit and margin after ad spend</li>
				<li>Ad spend broken down by platform</li>
				<li>Export the profit-by-product table to CSV</li>
				<li>Detailed cost and profit view for each product</li>
				<li>Control which WordPress users can see the dashboard</li>
			</ul>

			<div className="pl-pro-modal__actions">
				<a
					className="pl-btn-primary"
					href={ upgradeHref }
					target="_blank"
					rel="noopener noreferrer"
				>
					Upgrade to Pro
				</a>
				<button
					type="button"
					className="pl-pro-modal__cta-secondary"
					onClick={ onClose }
				>
					Maybe later
				</button>
			</div>
		</Modal>
	);
}
