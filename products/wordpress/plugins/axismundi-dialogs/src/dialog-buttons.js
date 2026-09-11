/**
 * axismundi/dialog-buttons - core/buttons copy, as WordPress 7.1 ships it.
 *
 * Copied from the wp/7.1 branch of Gutenberg, not trunk, because 7.1 is what
 * runs this block. The two differ here: 7.1 passes the template to
 * useInnerBlocksProps, while trunk moved it into the block-type settings - a
 * fallback 7.1's block editor does not have, so a trunk copy inserted an empty
 * group.
 *
 * One deliberate difference from core: the wrapper also carries
 * `wp-block-buttons`. Core gets that class from its block name; this block has
 * another name, and the class is what lets the theme's button-group styles
 * reach it.
 *
 * Group size and shape (M3 Standard button group). The group states them and
 * its buttons inherit through CSS; a button that stores its own is an
 * exception. The default variation stores Small and Round explicitly - this is
 * where the explicit default lives, not on each button.
 *
 * Togglable (the buttons' default) and Selection (how toggles relate) are the
 * group's, and reach the buttons as block context; see shared/selection.js.
 */
import { buttons as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';
import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	SelectControl,
	ToggleControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from '../blocks/dialog-buttons/block.json';
import { isTogglable, selectionChanges } from './shared/selection';

// core 7.1 copies these onto a button added after another. Only className (the
// style variation) exists on this block's buttons. Size and shape are left out
// on purpose: the group's value is the default and a button's own is an
// exception, so copying a neighbour's exception into every new button would
// quietly undo "same size by default".
const DEFAULT_BLOCK = {
	name: 'axismundi/dialog-button',
	attributesToCopy: [ 'className' ],
};

const SIZE_OPTIONS = [
	{ label: __( 'Extra small', 'axismundi-dialogs' ), value: 'xsmall' },
	{ label: __( 'Small (default)', 'axismundi-dialogs' ), value: 'small' },
	{ label: __( 'Medium', 'axismundi-dialogs' ), value: 'medium' },
	{ label: __( 'Large', 'axismundi-dialogs' ), value: 'large' },
	{ label: __( 'Extra large', 'axismundi-dialogs' ), value: 'xlarge' },
];

const SHAPE_OPTIONS = [
	{ label: __( 'Round (default)', 'axismundi-dialogs' ), value: 'round' },
	{ label: __( 'Square', 'axismundi-dialogs' ), value: 'square' },
];

// Multiple is the fallback: toggles that do not constrain each other.
const SELECTION_OPTIONS = [
	{ label: __( 'Single', 'axismundi-dialogs' ), value: 'single' },
	{ label: __( 'Multiple (default)', 'axismundi-dialogs' ), value: 'multiple' },
];

// Keeps the toggles inside the group's rule. The rule is enforced here, as the
// buttons change, rather than only when a setting changes: a duplicated
// selected toggle or a removed last one breaks it just the same. The fix is
// folded into the change that caused it, so undo takes both back together
// instead of undoing the fix alone and having it reapplied at once.
//
// Only in answer to an edit. A fix made while the editor loads has no change
// to fold into: it becomes the post's first edit on its own, and undoing it
// emptied the canvas (measured). Content saved outside the rule stays as it is
// until it is edited; the front end reads it defensively.
//
// "An edit" is a change to what the rule reads, not a new `buttons` array: the
// editor hands the same blocks over again, in a fresh array, just after they
// mount (measured), so skipping only the first run still fixed on load.
function ruleInput( buttons, togglable, selection, selectionRequired ) {
	return JSON.stringify( [
		togglable,
		selection,
		selectionRequired,
		buttons.map( ( { attributes } ) => [ attributes.togglable, attributes.selected, attributes.tagName ] ),
	] );
}

function useSelectionInvariant( buttons, togglable, selection, selectionRequired ) {
	const { updateBlockAttributes, __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );
	const lastInput = useRef();

	useEffect( () => {
		const input = ruleInput( buttons, togglable, selection, selectionRequired );
		const isEdit = lastInput.current !== undefined && lastInput.current !== input;
		lastInput.current = input;
		if ( ! isEdit ) {
			return;
		}
		const changes = selectionChanges( buttons, { togglable, selection, selectionRequired } );
		const clientIds = Object.keys( changes );
		if ( ! clientIds.length ) {
			return;
		}
		__unstableMarkNextChangeAsNotPersistent();
		updateBlockAttributes( clientIds, changes, { uniqueByBlock: true } );
	}, [ buttons, togglable, selection, selectionRequired ] );
}

// Whether the group has an explicit block gap. Both block-gap axes count.
function hasBlockGap( style ) {
	const gap = style?.spacing?.blockGap;
	return typeof gap === 'string' ? gap !== '' : !! ( gap && ( gap.top || gap.left ) );
}

// The size's between-space only applies while no block gap is set. It cannot
// be a stylesheet rule: the layout support writes the default gap and an
// explicit one at the same specificity, and this plugin's stylesheet loads
// before both, so a rule here either loses to the default or beats the user's
// own gap. The attributes know which case it is; the value stays a CSS token.
// Mirrored in PHP (axismundi_dialogs_buttons_size_gap) for the front end.
function sizeGapStyle( attributes ) {
	return attributes.size && ! hasBlockGap( attributes.style )
		? { gap: 'var(--ax-button-group-between)' }
		: {};
}

function Edit( { attributes, setAttributes, clientId } ) {
	const { layout, selection, selectionRequired, shape, size, togglable } = attributes;
	const buttons = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);
	useSelectionInvariant( buttons, togglable, selection, selectionRequired );
	// Selection describes how toggles relate, so it is offered once there is a
	// toggle to relate: by the group's default or by one button's own choice.
	const hasToggles = buttons.some( ( { attributes: button } ) =>
		isTogglable( button, togglable )
	);
	const blockProps = useBlockProps( {
		className: 'wp-block-buttons',
		'data-size': size || undefined,
		'data-shape': shape || undefined,
		style: sizeGapStyle( attributes ),
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		defaultBlock: DEFAULT_BLOCK,
		template: [ [ 'axismundi/dialog-button' ] ],
		templateInsertUpdatesSelection: true,
		orientation: layout?.orientation ?? 'horizontal',
	} );

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					label={ __( 'Settings', 'axismundi-dialogs' ) }
					resetAll={ () =>
						setAttributes( {
							size: 'small',
							shape: 'round',
							togglable: undefined,
							selection: undefined,
							selectionRequired: undefined,
						} )
					}
				>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Size', 'axismundi-dialogs' ) }
						hasValue={ () => !! size && size !== 'small' }
						onDeselect={ () => setAttributes( { size: 'small' } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Size', 'axismundi-dialogs' ) }
							// A group saved before these attributes existed renders
							// Small; show it so.
							value={ size || 'small' }
							options={ SIZE_OPTIONS }
							onChange={ ( value ) => setAttributes( { size: value } ) }
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Shape', 'axismundi-dialogs' ) }
						hasValue={ () => !! shape && shape !== 'round' }
						onDeselect={ () => setAttributes( { shape: 'round' } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Shape', 'axismundi-dialogs' ) }
							value={ shape || 'round' }
							options={ SHAPE_OPTIONS }
							onChange={ ( value ) => setAttributes( { shape: value } ) }
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Togglable', 'axismundi-dialogs' ) }
						hasValue={ () => !! togglable }
						onDeselect={ () => setAttributes( { togglable: undefined } ) }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Togglable', 'axismundi-dialogs' ) }
							help={ __(
								'Buttons in this group are toggles unless a button says otherwise.',
								'axismundi-dialogs'
							) }
							checked={ !! togglable }
							onChange={ ( value ) => setAttributes( { togglable: value || undefined } ) }
						/>
					</ToolsPanelItem>
					{ hasToggles && (
						<ToolsPanelItem
							isShownByDefault
							label={ __( 'Selection', 'axismundi-dialogs' ) }
							hasValue={ () => !! selection }
							onDeselect={ () => setAttributes( { selection: undefined } ) }
						>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Selection', 'axismundi-dialogs' ) }
								value={ selection || 'multiple' }
								options={ SELECTION_OPTIONS }
								onChange={ ( value ) => setAttributes( { selection: value } ) }
							/>
						</ToolsPanelItem>
					) }
					{ hasToggles && (
						<ToolsPanelItem
							isShownByDefault
							label={ __( 'Require a selection', 'axismundi-dialogs' ) }
							hasValue={ () => !! selectionRequired }
							onDeselect={ () => setAttributes( { selectionRequired: undefined } ) }
						>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Require a selection', 'axismundi-dialogs' ) }
								help={ __(
									'The last selected toggle cannot be turned off.',
									'axismundi-dialogs'
								) }
								checked={ !! selectionRequired }
								onChange={ ( value ) =>
									setAttributes( { selectionRequired: value || undefined } )
								}
							/>
						</ToolsPanelItem>
					) }
				</ToolsPanel>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

// The between-space is not saved: static markup would carry it forever, and
// the render_block filter adds it on the front end from the same rule.
function save( { attributes } ) {
	const { shape, size } = attributes;
	const blockProps = useBlockProps.save( {
		className: 'wp-block-buttons',
		'data-size': size || undefined,
		'data-shape': shape || undefined,
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}

registerBlockType( metadata, {
	icon,
	edit: Edit,
	save,
} );
