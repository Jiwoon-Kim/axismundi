/**
 * What a button opens.
 *
 * A button is the same control whatever it opens, so this is one setting with
 * a target rather than a family of blocks. The kind decides where the targets
 * come from: template parts live in areas, and an area is exactly "the parts
 * that belong in this kind of surface".
 *
 *   Navigation overlay   area `navigation-overlay` - the same parts core's
 *                        Navigation block lists under Overlay template, and
 *                        the same name for them here
 *
 * The list is read from the same place core reads it, `wp_template_part` over
 * core-data, so a part added in the Site Editor appears here without anything
 * being registered.
 *
 * Deliberately smaller than core's own selector: no Default entry, no Edit
 * button, no create-an-overlay flow. Those belong to the block that owns the
 * overlay; this one only points at it.
 */
import { __ } from '@wordpress/i18n';
import { ACTION_AREAS } from './action-controls';
import { useMemo } from '@wordpress/element';
import { useEntityRecords } from '@wordpress/core-data';
import { SelectControl, Spinner, Notice } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { BlockPreview } from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';

/**
 * The template parts a trigger kind can point at.
 *
 * @param {string} action The chosen action, or '' for none.
 * @return {{parts: Array, isResolving: boolean}} The parts and whether they are still loading.
 */
export function useActionTargets( action ) {
	const area = ACTION_AREAS[ action ];
	const { records, isResolving } = useEntityRecords( 'postType', 'wp_template_part', {
		per_page: -1,
	} );
	const parts = useMemo(
		() => ( area && records ? records.filter( ( part ) => part.area === area ) : [] ),
		[ records, area ]
	);
	return { parts, isResolving: !! area && isResolving };
}

/**
 * The target picker and its preview, for actions that open something.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.action      The chosen action.
 * @param {string}   props.value       The selected part's slug.
 * @param {Function} props.onChange    Called with the new slug.
 * @return {Element|null} The controls, or null when no kind is chosen.
 */
export function ActionTargetControls( { action, value, onChange } ) {
	const { parts, isResolving } = useActionTargets( action );

	const selected = useMemo(
		() => parts.find( ( part ) => part.slug === value ) ?? null,
		[ parts, value ]
	);

	// Parsed once per part, not per render: a preview parses the whole surface.
	const blocks = useMemo(
		() => ( selected?.content?.raw ? parse( selected.content.raw ) : [] ),
		[ selected ]
	);

	if ( ! ACTION_AREAS[ action ] ) {
		return null;
	}

	if ( isResolving ) {
		return <Spinner />;
	}

	if ( ! parts.length ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'This theme has no overlay template part yet. Add one in the Site Editor and it will appear here.',
					'axismundi-dialogs'
				) }
			</Notice>
		);
	}

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Overlay template', 'axismundi-dialogs' ) }
				value={ value ?? '' }
				options={ [
					{ label: __( 'Choose…', 'axismundi-dialogs' ), value: '' },
					...parts.map( ( part ) => ( {
						label: part.title?.rendered
							? decodeEntities( part.title.rendered )
							: part.slug,
						value: part.slug,
					} ) ),
				] }
				onChange={ ( next ) => onChange( next || undefined ) }
			/>
			{ !! blocks.length && (
				<div className="axismundi-trigger__preview">
					<BlockPreview blocks={ blocks } viewportWidth={ 480 } />
				</div>
			) }
		</>
	);
}
