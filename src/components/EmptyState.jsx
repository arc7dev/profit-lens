import { __ } from '@wordpress/i18n';

/**
 * "Costs not configured" state. Dashboard layout in the background,
 * blurred, with a centered setup card — matching the reference Figma
 * export.
 *
 * Two distinct setups collapse into the same REST status ("empty"), so
 * the copy/CTA here is driven by `setupStatus`
 * (class-rest-controller.php's `setup_status`, 'cogs_disabled' |
 * 'no_costs'): WooCommerce's Cost of Goods Sold feature being off
 * entirely is a different problem (and fix) from it being on with no
 * product priced yet — sending both cases to "go look at a product"
 * stranded a merchant with COGS disabled, since that screen has no cost
 * field to find at all. Defaults to 'cogs_disabled' when the prop is
 * missing (older cached response shape, or a bug upstream) — the more
 * complete, two-step onboarding — rather than rendering nothing.
 */

const SETUP_CONTENT = {
	cogs_disabled: {
		title: __( 'Set up product costs to see your profit', 'profit-lens' ),
		intro: __(
			"Profit Lens calculates your profit from the cost of each product. WooCommerce includes this feature, but it's turned off by default.",
			'profit-lens'
		),
		steps: [
			{
				key: 'enable',
				status: 'current',
				title: __( 'Enable Cost of Goods Sold', 'profit-lens' ),
				description: __(
					'WooCommerce › Settings › Advanced › Features',
					'profit-lens'
				),
			},
			{
				key: 'add-cost',
				status: 'upcoming',
				title: __( 'Add a cost to your products', 'profit-lens' ),
				description: __(
					"In each product's edit screen, including variations",
					'profit-lens'
				),
			},
			{
				key: 'return',
				status: 'upcoming',
				title: __( 'Come back here', 'profit-lens' ),
				description: __(
					'Your profit dashboard will load automatically',
					'profit-lens'
				),
			},
		],
		note: null,
		cta: {
			label: __( 'Enable Cost of Goods Sold', 'profit-lens' ),
			urlKey: 'cogsSettingsUrl',
		},
		secondaryLink: {
			label: __( 'Learn how it works', 'profit-lens' ),
			href: 'https://woocommerce.com/document/woocommerce-cost-of-goods-sold/',
		},
	},
	no_costs: {
		title: __( 'Add costs to your products', 'profit-lens' ),
		intro: null,
		steps: [
			{
				key: 'enable',
				status: 'completed',
				title: __( 'Cost of Goods Sold enabled', 'profit-lens' ),
				description: null,
			},
			{
				key: 'add-cost',
				status: 'current',
				title: __( 'Add a cost to your products', 'profit-lens' ),
				description: __(
					"In each product's edit screen, including variations",
					'profit-lens'
				),
			},
		],
		note: __(
			"You don't need to do every product at once — start with your best sellers. The dashboard will show how much of your revenue has a cost.",
			'profit-lens'
		),
		cta: {
			label: __( 'Go to Products', 'profit-lens' ),
			urlKey: 'productsUrl',
		},
		secondaryLink: null,
	},
};

function Bar( { w, h, opacity = 1, color = '#12212e' } ) {
	return (
		<div
			style={ {
				width: w,
				height: h,
				borderRadius: 2,
				background: color,
				opacity,
			} }
		/>
	);
}

/**
 * Simplified, static replica of the dashboard layout, used purely as
 * background decoration — doesn't read real data.
 */
function DashboardPreview() {
	return (
		<div style={ { padding: '24px 24px 32px' } }>
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					marginBottom: 24,
				} }
			>
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: 6,
					} }
				>
					<Bar w="56px" h={ 8 } opacity={ 0.3 } />
					<Bar w="80px" h={ 16 } opacity={ 0.5 } />
				</div>
				<Bar w="280px" h={ 30 } opacity={ 0.25 } />
			</div>

			<div style={ { display: 'flex', gap: 10, marginBottom: 12 } }>
				<div
					className="pl-card"
					style={ { flex: 1.45, padding: '18px 20px' } }
				>
					<Bar w="60px" h={ 8 } opacity={ 0.3 } />
					<div style={ { marginTop: 10 } }>
						<Bar
							w="110px"
							h={ 36 }
							opacity={ 0.4 }
							color="#1f8a5b"
						/>
					</div>
					<div style={ { marginTop: 8 } }>
						<Bar
							w="80px"
							h={ 8 }
							opacity={ 0.25 }
							color="#1f8a5b"
						/>
					</div>
				</div>
				{ [ 1, 2, 3 ].map( ( i ) => (
					<div
						className="pl-card"
						key={ i }
						style={ { flex: 1, padding: '18px 20px' } }
					>
						<Bar w="50px" h={ 8 } opacity={ 0.3 } />
						<div style={ { marginTop: 10 } }>
							<Bar w="80px" h={ 24 } opacity={ 0.35 } />
						</div>
						<div style={ { marginTop: 8 } }>
							<Bar w="90px" h={ 7 } opacity={ 0.2 } />
						</div>
					</div>
				) ) }
			</div>

			<div
				style={ {
					height: 34,
					borderLeft: '3px solid #98dbaf',
					background: 'rgba(152,219,175,0.08)',
					borderRadius: '0 4px 4px 0',
					marginBottom: 18,
					display: 'flex',
					alignItems: 'center',
					padding: '0 14px',
				} }
			>
				<Bar w="260px" h={ 8 } opacity={ 0.3 } />
			</div>

			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: '2fr 1fr',
					gap: 10,
					marginBottom: 18,
				} }
			>
				<div
					className="pl-card"
					style={ { padding: '18px 18px 14px' } }
				>
					<Bar w="140px" h={ 8 } opacity={ 0.3 } />
					<div
						style={ {
							marginTop: 14,
							height: 130,
							display: 'flex',
							alignItems: 'flex-end',
							gap: 5,
						} }
					>
						{ [
							40, 65, 55, 80, 90, 50, 70, 95, 75, 110, 85, 100,
						].map( ( h, i ) => (
							<div
								key={ i }
								style={ {
									flex: 1,
									height: h,
									background: 'rgba(152,219,175,0.35)',
									borderRadius: '2px 2px 0 0',
								} }
							/>
						) ) }
					</div>
				</div>
				<div className="pl-card" style={ { padding: 18 } }>
					<Bar w="100px" h={ 8 } opacity={ 0.3 } />
					{ [ 1, 0.55, 0.38, 0.22 ].map( ( op, i ) => (
						<div key={ i } style={ { marginTop: 14 } }>
							<div
								style={ {
									display: 'flex',
									justifyContent: 'space-between',
									marginBottom: 5,
								} }
							>
								<Bar w="70px" h={ 8 } opacity={ 0.25 } />
								<Bar w="36px" h={ 8 } opacity={ 0.2 } />
							</div>
							<div
								style={ {
									height: 4,
									background: '#f1f4f7',
									borderRadius: 2,
								} }
							>
								<div
									style={ {
										width: `${ [ 82, 27, 34, 18 ][ i ] }%`,
										height: '100%',
										background: `rgba(18,33,46,${ op })`,
										borderRadius: 2,
									} }
								/>
							</div>
						</div>
					) ) }
				</div>
			</div>

			<div className="pl-card" style={ { padding: '14px 18px' } }>
				<Bar w="110px" h={ 8 } opacity={ 0.3 } />
				<div
					style={ {
						marginTop: 12,
						display: 'flex',
						flexDirection: 'column',
						gap: 10,
					} }
				>
					{ [ 1, 1, 1, 1, 1, 0.5 ].map( ( _row, i ) => (
						<div
							key={ i }
							style={ {
								display: 'flex',
								gap: 14,
								alignItems: 'center',
							} }
						>
							<Bar w="160px" h={ 9 } opacity={ 0.25 } />
							<Bar w="28px" h={ 9 } opacity={ 0.2 } />
							<Bar w="40px" h={ 9 } opacity={ 0.2 } />
							<Bar w="40px" h={ 9 } opacity={ 0.2 } />
							<Bar
								w="44px"
								h={ 9 }
								opacity={ 0.2 }
								color={ i === 5 ? '#c0392b' : '#12212e' }
							/>
							<Bar w="34px" h={ 9 } opacity={ 0.2 } />
						</div>
					) ) }
				</div>
			</div>
		</div>
	);
}

export default function EmptyState( { setupStatus } ) {
	const content = SETUP_CONTENT[ setupStatus ] || SETUP_CONTENT.cogs_disabled;
	const ctaUrl = window.profitLensData?.[ content.cta.urlKey ] ?? '';

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

			<div className="pl-empty-wrap">
				<div className="pl-empty__preview" aria-hidden="true">
					<DashboardPreview />
				</div>

				<div className="pl-empty__overlay">
					<div className="pl-empty__card">
						<div className="pl-empty__icon">
							<svg
								width="36"
								height="36"
								viewBox="0 0 36 36"
								fill="none"
							>
								<rect
									x="6"
									y="4"
									width="22"
									height="28"
									rx="2"
									stroke="#b0c4ce"
									strokeWidth="1.5"
								/>
								<line
									x1="11"
									y1="12"
									x2="23"
									y2="12"
									stroke="#b0c4ce"
									strokeWidth="1.5"
									strokeLinecap="round"
								/>
								<line
									x1="11"
									y1="17"
									x2="23"
									y2="17"
									stroke="#b0c4ce"
									strokeWidth="1.5"
									strokeLinecap="round"
								/>
								<line
									x1="11"
									y1="22"
									x2="19"
									y2="22"
									stroke="#b0c4ce"
									strokeWidth="1.5"
									strokeLinecap="round"
								/>
								<circle
									cx="27"
									cy="27"
									r="6"
									fill="#f7f9fb"
									stroke="#e3e9ee"
									strokeWidth="1"
								/>
								<polyline
									points="24,27 26.5,29.5 30,25"
									stroke="#98dbaf"
									strokeWidth="1.5"
									strokeLinecap="round"
									strokeLinejoin="round"
									fill="none"
								/>
							</svg>
						</div>

						<h2 className="pl-empty__title">{ content.title }</h2>

						{ content.intro && (
							<p className="pl-empty__copy">{ content.intro }</p>
						) }

						<ol className="pl-setup-steps">
							{ content.steps.map( ( step ) => (
								<li
									key={ step.key }
									className={ `pl-setup-step pl-setup-step--${ step.status }` }
									aria-current={
										step.status === 'current'
											? 'step'
											: undefined
									}
								>
									<span
										className="pl-setup-step__marker"
										aria-hidden="true"
									>
										{ step.status === 'completed' ? (
											<svg
												width="12"
												height="12"
												viewBox="0 0 12 12"
												fill="none"
											>
												<polyline
													points="2,6.5 5,9.5 10,3"
													stroke="currentColor"
													strokeWidth="1.6"
													strokeLinecap="round"
													strokeLinejoin="round"
													fill="none"
												/>
											</svg>
										) : null }
									</span>
									<span className="pl-setup-step__text">
										<span className="pl-setup-step__title">
											{ step.title }
										</span>
										{ step.description && (
											<span className="pl-setup-step__description">
												{ step.description }
											</span>
										) }
									</span>
								</li>
							) ) }
						</ol>

						{ content.note && (
							<p className="pl-empty__note">{ content.note }</p>
						) }

						<a className="pl-btn-primary" href={ ctaUrl }>
							{ content.cta.label }
						</a>

						{ content.secondaryLink && (
							<a
								className="pl-empty__link"
								href={ content.secondaryLink.href }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ content.secondaryLink.label }
							</a>
						) }
					</div>
				</div>
			</div>
		</div>
	);
}
