/**
 * Shimmering skeleton shown while waiting on the response from
 * GET /profit-lens/v1/summary. Same shape as the real dashboard so there's
 * no layout shift once the data arrives.
 */

function Skeleton( { width = '100%', height = 14, radius = 3, style = {} } ) {
	return (
		<div
			className="pl-skeleton"
			style={ { width, height, borderRadius: radius, ...style } }
		/>
	);
}

export default function LoadingState() {
	return (
		<div>
			<div className="pl-header">
				<div
					style={ {
						display: 'flex',
						flexDirection: 'column',
						gap: 6,
					} }
				>
					<Skeleton width={ 60 } height={ 10 } />
					<Skeleton width={ 80 } height={ 22 } />
				</div>
				<Skeleton width={ 320 } height={ 34 } radius={ 4 } />
			</div>

			<div className="pl-kpis">
				{ [ 0, 1, 2, 3 ].map( ( i ) => (
					<div
						className="pl-card"
						key={ i }
						style={ { flex: 1, padding: '20px 24px 18px' } }
					>
						<Skeleton
							width={ 90 }
							height={ 10 }
							style={ { marginBottom: 14 } }
						/>
						<Skeleton
							width={ 120 }
							height={ 32 }
							style={ { marginBottom: 10 } }
						/>
						<Skeleton width={ 140 } height={ 10 } />
					</div>
				) ) }
			</div>

			<div className="pl-row" style={ { marginTop: 20 } }>
				<div className="pl-card" style={ { padding: 20 } }>
					<Skeleton
						width={ 180 }
						height={ 10 }
						style={ { marginBottom: 20 } }
					/>
					<div
						style={ {
							display: 'flex',
							alignItems: 'flex-end',
							gap: 6,
							height: 200,
							paddingBottom: 20,
						} }
					>
						{ [
							60, 90, 75, 110, 95, 130, 80, 140, 100, 160, 120,
							150,
						].map( ( h, i ) => (
							<div
								key={ i }
								className="pl-skeleton"
								style={ {
									flex: 1,
									height: h,
									borderRadius: '2px 2px 0 0',
								} }
							/>
						) ) }
					</div>
					<div
						style={ {
							display: 'flex',
							gap: 6,
							justifyContent: 'space-between',
						} }
					>
						{ [ 0, 1, 2, 3, 4, 5 ].map( ( i ) => (
							<Skeleton key={ i } width={ 32 } height={ 8 } />
						) ) }
					</div>
				</div>

				<div className="pl-card" style={ { padding: 20 } }>
					<Skeleton
						width={ 120 }
						height={ 10 }
						style={ { marginBottom: 20 } }
					/>
					<div
						style={ {
							display: 'flex',
							flexDirection: 'column',
							gap: 20,
						} }
					>
						{ [ 0, 1, 2, 3 ].map( ( i ) => (
							<div key={ i }>
								<div
									style={ {
										display: 'flex',
										justifyContent: 'space-between',
										marginBottom: 6,
									} }
								>
									<Skeleton width={ 100 } height={ 12 } />
									<Skeleton width={ 50 } height={ 12 } />
								</div>
								<Skeleton
									width="100%"
									height={ 4 }
									radius={ 2 }
								/>
							</div>
						) ) }
					</div>
				</div>
			</div>

			<div
				className="pl-card"
				style={ { marginBottom: 20, overflow: 'hidden' } }
			>
				<div
					style={ {
						padding: '18px 20px 14px',
						borderBottom: '1px solid #e3e9ee',
					} }
				>
					<Skeleton width={ 140 } height={ 10 } />
				</div>
				<div style={ { padding: '0 20px' } }>
					<div
						style={ {
							display: 'flex',
							gap: 16,
							padding: '12px 0',
							borderBottom: '1px solid #e3e9ee',
						} }
					>
						{ [ 200, 60, 80, 70, 90, 70 ].map( ( w, i ) => (
							<Skeleton key={ i } width={ w } height={ 9 } />
						) ) }
					</div>
					{ [ 0, 1, 2, 3, 4, 5, 6, 7 ].map( ( i ) => (
						<div
							key={ i }
							style={ {
								display: 'flex',
								gap: 16,
								padding: '12px 0',
								borderBottom:
									i < 7 ? '1px solid #f1f4f7' : 'none',
								alignItems: 'center',
							} }
						>
							{ [ 200, 60, 80, 70, 90, 70 ].map( ( w, j ) => (
								<Skeleton key={ j } width={ w } height={ 12 } />
							) ) }
						</div>
					) ) }
				</div>
			</div>

			<div className="pl-card" style={ { padding: 20 } }>
				<Skeleton
					width={ 200 }
					height={ 10 }
					style={ { marginBottom: 16 } }
				/>
				{ [ 0, 1, 2, 3 ].map( ( i ) => (
					<div
						key={ i }
						style={ {
							display: 'flex',
							gap: 12,
							marginBottom: 12,
							alignItems: 'center',
						} }
					>
						<Skeleton width={ 220 } height={ 12 } />
						<Skeleton width="100%" height={ 6 } radius={ 2 } />
						<Skeleton width={ 70 } height={ 12 } />
					</div>
				) ) }
			</div>
		</div>
	);
}
