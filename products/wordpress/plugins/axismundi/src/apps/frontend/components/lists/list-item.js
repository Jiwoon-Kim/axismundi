/**
 * Material 3 ListItem.
 *
 * A row is either static, one native link, or one native button. Generic
 * trailing actions are intentionally absent: adding them would silently turn
 * a single-action row into a multi-action row without its keyboard contract.
 */

import { forwardRef } from '@wordpress/element';
import warning from '@wordpress/warning';

const LINE_COUNTS = [ 1, 2, 3 ];
const LEADING_TYPES = [ 'icon', 'avatar', 'image', 'video' ];

function getLineCount( lines, overline, supportingText ) {
	if ( undefined !== lines && ! LINE_COUNTS.includes( lines ) ) {
		warning( `ListItem: unsupported line count "${ lines }". Inferring the row height from its text slots.` );
	}

	if ( LINE_COUNTS.includes( lines ) ) {
		return lines;
	}

	return overline && supportingText ? 3 : overline || supportingText ? 2 : 1;
}

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.headline Required label text.
 * @param {import('@wordpress/element').ReactNode} [props.overline] Optional overline.
 * @param {import('@wordpress/element').ReactNode} [props.supportingText] Optional supporting text.
 * @param {import('@wordpress/element').ReactNode} [props.trailingSupportingText] Optional trailing metadata.
 * @param {import('@wordpress/element').ReactNode} [props.leading] Non-interactive leading visual.
 * @param {'icon'|'avatar'|'image'|'video'} [props.leadingType='icon'] Leading visual type.
 * @param {import('@wordpress/element').ReactNode} [props.trailingIcon] Non-interactive trailing icon.
 * @param {1|2|3} [props.lines] Minimum-height form; inferred when omitted.
 * @param {string} [props.href] Makes the primary action a native link.
 * @param {Function} [props.onClick] Makes the primary action a native button without href.
 * @param {string} [props.className] Additional row class name.
 * @return {import('@wordpress/element').ReactNode} List row.
 */
export const ListItem = forwardRef(
	function ListItem(
		{
			className,
			disabled,
			headline,
			href,
			leading,
			leadingType = 'icon',
			lines,
			onClick,
			overline,
			supportingText,
			trailingIcon,
			trailingSupportingText,
			...props
		},
		ref
	) {
		const rowLines = getLineCount( lines, overline, supportingText );
		const hasLink = undefined !== href;
		const hasButton = ! hasLink && 'function' === typeof onClick;
		const isDisabled = true === disabled;
		const Host = hasLink ? 'a' : hasButton ? 'button' : 'div';
		const visualLeadingType = LEADING_TYPES.includes( leadingType ) ? leadingType : 'icon';

		if ( ! headline ) {
			warning( 'ListItem: headline is required by the M3 list anatomy.' );
		}

		if ( visualLeadingType !== leadingType ) {
			warning( `ListItem: unsupported leadingType "${ leadingType }". Using "icon".` );
		}

		if ( hasLink && 'function' === typeof onClick ) {
			warning( 'ListItem: href selects the native link host; onClick remains an ordinary link handler, not a second action.' );
		}

		return (
			<li
				className={ [ 'ax-list-item', className ].filter( Boolean ).join( ' ' ) }
				data-lines={ rowLines }
			>
				<Host
					{ ...props }
					{ ...( hasLink
						? isDisabled
							? { 'aria-disabled': true }
							: { href, onClick }
						: hasButton
							? { disabled: isDisabled, onClick: isDisabled ? undefined : onClick, type: 'button' }
							: {} ) }
					ref={ ref }
					className="ax-list-item__action"
					data-disabled={ isDisabled || undefined }
				>
					{ leading ? (
						<span className="ax-list-item__leading" data-leading-type={ visualLeadingType }>
							{ leading }
						</span>
					) : null }
					<span className="ax-list-item__content">
						{ overline ? <span className="ax-list-item__overline">{ overline }</span> : null }
						<span className="ax-list-item__headline">{ headline }</span>
						{ supportingText ? <span className="ax-list-item__supporting-text">{ supportingText }</span> : null }
					</span>
					{ trailingSupportingText || trailingIcon ? (
						<span className="ax-list-item__trailing">
							{ trailingSupportingText ? (
								<span className="ax-list-item__trailing-supporting-text">{ trailingSupportingText }</span>
							) : null }
							{ trailingIcon ? <span className="ax-list-item__trailing-icon">{ trailingIcon }</span> : null }
						</span>
					) : null }
				</Host>
			</li>
		);
	}
);
