/**
 * Insight bar — the callout with the most relevant finding for the range
 * (for now: the product that lost the most money, so it uses the loss
 * accent; see .pl-insight in dashboard.css).
 *
 * @param {Object}                                     props
 * @param {{message: string, product_id: number}|null} props.insight
 */
export default function InsightBar( { insight } ) {
	if ( ! insight ) {
		return null;
	}

	return (
		<div className="pl-insight">
			<span className="pl-insight__text">{ insight.message }</span>
			<a
				href={ `post.php?post=${ insight.product_id }&action=edit` }
				className="pl-insight__link pl-mono"
			>
				View product
			</a>
		</div>
	);
}
