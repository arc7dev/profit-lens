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
 * @param {Object}      props
 * @param {boolean}     props.isOpen
 * @param {() => void}  props.onClose
 * @param {string}      props.feature Short name of the gated feature, e.g.
 *                                     "Ads Integration" — used in the
 *                                     "Unlock {feature}" subtitle.
 */
export default function ProUpgradeModal( { isOpen, onClose, feature } ) {
	if ( ! isOpen ) {
		return null;
	}

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
			<div className="pl-pro-modal__subtitle">
				Unlock { feature }
			</div>
			<p className="pl-pro-modal__copy">
				Get CSV exports, ad spend tracking, and deeper analytics to
				make smarter decisions about your store.
			</p>

			<div className="pl-pro-modal__actions">
				<a
					className="pl-pro-modal__cta-primary"
					href="https://arc7.dev/profit-lens/pro"
					target="_blank"
					rel="noreferrer"
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
