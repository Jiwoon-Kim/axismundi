/**
 * axismundi/dialog-button-group - core/buttons copy, as WordPress 7.1 ships it.
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
import metadata from '../blocks/dialog-button-group/block.json';
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

const ICON_DEFAULT_BLOCK = {
	name: 'axismundi/dialog-icon-button',
	attributesToCopy: [ 'className' ],
};

/*
 * M3's "Button type: Icon | Label" on the Standard button group, adapted.
 *
 * In Figma it is a variant of the whole component, so every button in the group
 * changes at once - there is no way to express a group holding one of each.
 * Here the buttons are real blocks, and a mixed group is both expressible and
 * useful: an icon button beside two labelled ones is a normal toolbar. So this
 * sets WHICH BLOCK IS ADDED NEXT and leaves the buttons already there alone.
 * Switching it is then never destructive, which matters because the two blocks
 * do not hold the same things - `width`, `standard` and `showTooltips` exist
 * only on the icon button and would be dropped by a conversion.
 *
 * To convert the buttons that are already there, the block transforms do it one
 * at a time and say what they drop (shared/button-transforms.js).
 */
/*
 * M3's adjacent interaction, which only exists where there is room for it: a
 * neighbour can compress only if the group is wider than its content. Measured
 * both ways - see the note in assets/button.css - so this is a choice rather
 * than the default, because a Dialog Button Group is also the plain action row
 * that should stay at content width.
 */
const DISTRIBUTION_OPTIONS = [
	{ label: __( 'Content width', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Fill the width', 'axismundi-dialogs' ), value: 'fill' },
];

const BUTTON_TYPE_OPTIONS = [
	{ label: __( 'Label', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Icon', 'axismundi-dialogs' ), value: 'icon' },
];

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
// Mirrored in PHP (axismundi_dialogs_button_group_size_gap) for the front end.
function sizeGapStyle( attributes ) {
	return attributes.size && ! hasBlockGap( attributes.style )
		? { gap: 'var(--ax-button-group-between)' }
		: {};
}

function Edit( { attributes, setAttributes, clientId } ) {
	const {
		buttonType,
		distribution,
		layout,
		selection,
		selectionRequired,
		shape,
		size,
		togglable,
	} = attributes;
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
	const isIconType = 'icon' === buttonType;
	const blockProps = useBlockProps( {
		className: 'wp-block-buttons',
		'data-size': size || undefined,
		'data-shape': shape || undefined,
		'data-distribution': distribution || undefined,
		style: sizeGapStyle( attributes ),
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		defaultBlock: isIconType ? ICON_DEFAULT_BLOCK : DEFAULT_BLOCK,
		template: [ [ isIconType ? 'axismundi/dialog-icon-button' : 'axismundi/dialog-button' ] ],
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
							buttonType: undefined,
				distribution: undefined,
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
				label={ __( 'Button type', 'axismundi-dialogs' ) }
				hasValue={ () => !! buttonType }
				onDeselect={ () => setAttributes( { buttonType: undefined } ) }
			>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Button type', 'axismundi-dialogs' ) }
					value={ buttonType ?? '' }
					options={ BUTTON_TYPE_OPTIONS }
					help={ __( 'The button a new group starts with, and the one the editor adds where it adds a default. The + button asks instead. Buttons already here do not change — convert one with Transform to.', 'axismundi-dialogs' ) }
					onChange={ ( value ) => setAttributes( { buttonType: value || undefined } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				isShownByDefault
				label={ __( 'Distribution', 'axismundi-dialogs' ) }
				hasValue={ () => !! distribution }
				onDeselect={ () => setAttributes( { distribution: undefined } ) }
			>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Distribution', 'axismundi-dialogs' ) }
					value={ distribution ?? '' }
					options={ DISTRIBUTION_OPTIONS }
					help={
						distribution === 'fill'
							? __( 'The group fills the width and its buttons share it equally. Where the buttons are toggles, the selected one widens and its neighbours give way. Too narrow to share, and they stack at full width.', 'axismundi-dialogs' )
							: __( 'Each button is as wide as its own label.', 'axismundi-dialogs' )
					}
					onChange={ ( value ) => setAttributes( { distribution: value || undefined } ) }
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
	const { distribution, shape, size } = attributes;
	const blockProps = useBlockProps.save( {
		className: 'wp-block-buttons',
		'data-size': size || undefined,
		'data-shape': shape || undefined,
		'data-distribution': distribution || undefined,
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}

registerBlockType( metadata, {
	icon,
	edit: Edit,
	save,
} );
