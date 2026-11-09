import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * WordPress.org Guideline 5 review request: dismissible, no incentive, no
 * filtering by sentiment (every visitor who meets the "has been using it
 * a while, has real data" bar sees the exact same neutral text and all
 * three actions — ProfitLens_Review_Notice::should_show() on the PHP side
 * is the only gate, there's no "are you happy?" branch here).
 *
 * window.profitLensData.reviewNotice.show already reflects the full
 * should_show() decision (14 days since first activation, real dashboard
 * data, not already dismissed/snoozed for this WordPress user) — this
 * component doesn't re-derive any of that, it only renders the decision
 * and reports which action the merchant took.
 *
 * Local `dismissed` state hides the notice the instant an action is
 * clicked, rather than waiting on the REST round trip — the request
 * still fires (fire-and-forget: a failed request means it might show
 * again next page load, which is an acceptable, non-destructive failure
 * mode for a dismiss preference, not something worth blocking the UI or
 * retrying for).
 */
export default function ReviewNotice() {
	const reviewNotice = window.profitLensData?.reviewNotice;
	const [ dismissed, setDismissed ] = useState( false );

	if ( ! reviewNotice?.show || dismissed ) {
		return null;
	}

	/**
	 * @param {'later'|'dismiss'|'reviewed'} action
	 */
	function sendAction( action ) {
		setDismissed( true );

		apiFetch( {
			path: `/${ window.profitLensData.restNamespace }/review-notice`,
			method: 'POST',
			data: { action },
		} ).catch( () => {
			// See this file's own docblock — intentionally not retried or
			// surfaced to the merchant.
		} );
	}

	return (
		<div
			className="pl-review-notice"
			role="region"
			aria-label={ __( 'Review request', 'profit-lens' ) }
		>
			<span className="pl-review-notice__text">
				{ __(
					'Is Profit Lens useful for your store? A short review on WordPress.org helps other store owners find it.',
					'profit-lens'
				) }
			</span>
			<div className="pl-review-notice__actions">
				<a
					href={ reviewNotice.reviewUrl }
					target="_blank"
					rel="noopener noreferrer"
					className="pl-review-notice__link"
					onClick={ () => sendAction( 'reviewed' ) }
				>
					{ __( 'Leave a review', 'profit-lens' ) }
				</a>
				<button
					type="button"
					className="pl-review-notice__btn"
					onClick={ () => sendAction( 'later' ) }
				>
					{ __( 'Maybe later', 'profit-lens' ) }
				</button>
				<button
					type="button"
					className="pl-review-notice__btn"
					onClick={ () => sendAction( 'dismiss' ) }
				>
					{ __( "Don't ask again", 'profit-lens' ) }
				</button>
			</div>
		</div>
	);
}
