/**
 * "Couldn't calculate profit" state — WooCommerce inactive, or a
 * calculation error server-side (see ProfitLens_REST_Controller's
 * status: "error"). The response only ever carries a generic message; no
 * internal error details reach this component to display, by design.
 */

export default function ErrorState( { message } ) {
	return (
		<div>
			<div className="pl-header">
				<div>
					<div className="pl-header__eyebrow pl-mono">
						Profit Lens
					</div>
					<h1 className="pl-header__title">Profit</h1>
				</div>
			</div>

			<div className="pl-notice-wrap">
				<div className="pl-notice__card">
					<div className="pl-notice__icon" aria-hidden="true">
						<svg
							width="36"
							height="36"
							viewBox="0 0 36 36"
							fill="none"
						>
							<circle
								cx="18"
								cy="18"
								r="15"
								stroke="#c0392b"
								strokeWidth="1.5"
							/>
							<line
								x1="18"
								y1="11"
								x2="18"
								y2="20"
								stroke="#c0392b"
								strokeWidth="1.5"
								strokeLinecap="round"
							/>
							<circle cx="18" cy="25" r="1.25" fill="#c0392b" />
						</svg>
					</div>

					<h2 className="pl-notice__title">
						Couldn&rsquo;t calculate profit
					</h2>

					<p className="pl-notice__copy">
						{ message ||
							'Profit Lens could not calculate profit for this period.' }
					</p>

					<button
						type="button"
						className="pl-notice__cta pl-notice__cta--danger pl-mono"
						onClick={ () => window.location.reload() }
					>
						Try again
					</button>
				</div>
			</div>
		</div>
	);
}
