/**
 * "Custom" range button — opens a native-feeling admin dropdown with two
 * DatePicker calendars from the WordPress components package (start/end;
 * there's no DateRangePicker in this package's version) and an explicit
 * Apply step, rather than fetching on every single day clicked.
 *
 * Date format and localized month/day names come from @wordpress/date,
 * which WordPress core auto-localizes (site timezone/format, not the
 * browser's) on any admin page that loads the 'wp-date' script — true here
 * automatically once this file imports from '@wordpress/date' /
 * '@wordpress/components', no extra PHP wiring needed beyond what
 * class-assets.php already does for window.profitLensData (siteToday,
 * dateFormat, startOfWeek — the pieces DatePicker itself doesn't cover).
 */

import { Button, DatePicker, Dropdown, Notice } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { useState } from '@wordpress/element';

const MAX_RECOMMENDED_DAYS = 180;

/**
 * @param {string} isoDay 'Y-m-d'.
 * @return {Date} Local Date at midnight for that calendar day — only ever
 *                compared against other dates built the same way here, so
 *                the absolute timezone mapping doesn't matter, only the
 *                relative ordering/day-count between two of them.
 */
function toLocalMidnight( isoDay ) {
	return new Date( `${ isoDay }T00:00:00` );
}

/**
 * @param {string} isoDatetime DatePicker's onChange value ("yyyy-MM-dd'T'HH:mm:ss").
 * @return {string} Just the 'Y-m-d' part — what the REST endpoint expects.
 */
function toDay( isoDatetime ) {
	return isoDatetime.slice( 0, 10 );
}

/**
 * @param {Object}                                           props
 * @param {{after: string, before: string}|null}             props.appliedRange Currently active custom range, if any.
 * @param {(range: {after: string, before: string}) => void} props.onApply
 */
export default function CustomRangePicker( { appliedRange, onApply } ) {
	const { siteToday, dateFormat, startOfWeek } = window.profitLensData;

	const [ draftAfter, setDraftAfter ] = useState(
		appliedRange?.after || null
	);
	const [ draftBefore, setDraftBefore ] = useState(
		appliedRange?.before || null
	);

	const today = toLocalMidnight( siteToday );

	const isEndBeforeStart =
		draftAfter && draftBefore
			? toLocalMidnight( draftBefore ) < toLocalMidnight( draftAfter )
			: false;
	const isFuture =
		( draftAfter && toLocalMidnight( draftAfter ) > today ) ||
		( draftBefore && toLocalMidnight( draftBefore ) > today );
	const isIncomplete = ! draftAfter || ! draftBefore;

	const spanDays =
		draftAfter && draftBefore && ! isEndBeforeStart
			? Math.round(
					( toLocalMidnight( draftBefore ) -
						toLocalMidnight( draftAfter ) ) /
						86400000
			  ) + 1
			: 0;
	const isLargeRange = spanDays > MAX_RECOMMENDED_DAYS;

	const canApply = ! isIncomplete && ! isEndBeforeStart && ! isFuture;

	const buttonLabel = appliedRange
		? `${ dateI18n( dateFormat, appliedRange.after ) } – ${ dateI18n(
				dateFormat,
				appliedRange.before
		  ) }`
		: 'Custom';

	return (
		<Dropdown
			className="pl-range__custom"
			contentClassName="pl-custom-range__popover"
			onClose={ () => {
				// Discard an unapplied draft — reopening starts from
				// whatever range is actually active, not a half-picked one.
				setDraftAfter( appliedRange?.after || null );
				setDraftBefore( appliedRange?.before || null );
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<button
					type="button"
					className={
						'pl-range__btn' +
						( appliedRange ? ' pl-range__btn--active' : '' )
					}
					aria-expanded={ isOpen }
					onClick={ onToggle }
				>
					{ buttonLabel }
				</button>
			) }
			renderContent={ ( { onClose } ) => (
				<div className="pl-custom-range">
					<div className="pl-custom-range__calendars">
						<div>
							<div className="pl-custom-range__label pl-mono">
								Start
							</div>
							<DatePicker
								currentDate={ draftAfter }
								startOfWeek={ startOfWeek }
								isInvalidDate={ ( date ) => date > today }
								onChange={ ( value ) =>
									setDraftAfter( toDay( value ) )
								}
							/>
						</div>
						<div>
							<div className="pl-custom-range__label pl-mono">
								End
							</div>
							<DatePicker
								currentDate={ draftBefore }
								startOfWeek={ startOfWeek }
								isInvalidDate={ ( date ) => date > today }
								onChange={ ( value ) =>
									setDraftBefore( toDay( value ) )
								}
							/>
						</div>
					</div>

					{ isEndBeforeStart && (
						<Notice status="error" isDismissible={ false }>
							End date can&rsquo;t be before the start date.
						</Notice>
					) }
					{ isFuture && ! isEndBeforeStart && (
						<Notice status="error" isDismissible={ false }>
							Future dates aren&rsquo;t available yet.
						</Notice>
					) }
					{ isLargeRange && canApply && (
						<Notice status="warning" isDismissible={ false }>
							{ spanDays }-day range — this may take longer to
							load.
						</Notice>
					) }

					<div className="pl-custom-range__actions">
						<Button variant="tertiary" onClick={ onClose }>
							Cancel
						</Button>
						<Button
							variant="primary"
							disabled={ ! canApply }
							onClick={ () => {
								onApply( {
									after: draftAfter,
									before: draftBefore,
								} );
								onClose();
							} }
						>
							Apply
						</Button>
					</div>
				</div>
			) }
		/>
	);
}
