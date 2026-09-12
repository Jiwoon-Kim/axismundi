/**
 * axismundi/dialog-icon-button - an icon button (M3): dialog-button's shell
 * holding an icon instead of a label.
 *
 * Everything the two share is dialog-button's own editor (shared/button.js):
 * the wrapper, link editing, Size, Shape, Togglable, Selected and the advanced
 * controls. This file fills the slots that differ - the contents, the Icon
 * panel above Display and Width in it - so the two cannot drift apart. Their
 * attribute names match for the same reason: converting one into the other
 * keeps everything they have in common.
 *
 * The icon is the shared primitive (shared/icon.js, includes/icon.php), always
 * decorative here: an icon button's name is the button's, from `text`, as text
 * nobody sees. The block renders on the server (render.php), as core/icon does.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	BlockControls,
	InspectorControls,
	useSettings,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { starEmpty } from '@wordpress/icons';
import metadata from '../blocks/dialog-icon-button/block.json';
import { ButtonEdit } from './shared/button';
import { isTogglable } from './shared/selection';
import { BUTTON, toButton } from './shared/button-transforms';
import { IconElement, IconPlaceholder, glyphName, useIconRecord } from './shared/icon';
import { IconLibraryModal, IconReferenceControls, fontFamilyOptions } from './shared/icon-controls';
import { ACTION_OPTIONS, actionAttributes } from './shared/action-controls';
import { ActionTargetControls } from './shared/action-targets';

// M3's Width: the space either side of the icon, never the icon. "Default"
// stores nothing, and equals the container's height - a circle.
const WIDTH_OPTIONS = [
	{ label: __( 'Default', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Narrow', 'axismundi-dialogs' ), value: 'narrow' },
	{ label: __( 'Wide', 'axismundi-dialogs' ), value: 'wide' },
];

/*
 * The name, from the icon's name, when there is nothing else.
 *
 * An icon button has no visible label, so `text` is the only name it has, and
 * an empty one leaves a control screen readers cannot announce. M3 says as
 * much. `arrow_back` -> "Arrow back": the icon's name is what the icon means,
 * which is what the button does often enough to be a good start.
 *
 * A starting value, not a fallback: written into `text`, so it shows in the
 * Label field where the author can see it, change it, and have it translated
 * with the rest of the page. A label the author never sees would be worse than
 * none - it would look named while announcing a glyph's identifier. So this
 * only ever fills an empty Label, and only when an icon is chosen.
 */
function labelFromIcon( name ) {
	const text = String( name || '' ).replace( /[_-]+/g, ' ' ).trim();
	return text ? text.charAt( 0 ).toUpperCase() + text.slice( 1 ) : undefined;
}

/*
 * Colour is one axis in M3 - Filled, Tonal, Outlined, Standard - and all four
 * are block styles. Standard is registered on this block alone (block.json),
 * which is what the published spec says its WordPress form is:
 * `wp_style: is-style-standard` in _data/icon_button.yml.
 *
 * It was an attribute first, to stop `is-style-standard` riding along in
 * className when a Standard icon button became a Button, which has no such
 * style. The conversion now filters `is-style-*` against what the target block
 * registers (shared/button-transforms.js), so that reason is gone - and being a
 * style again buys the mutual exclusion for free: the Styles panel already
 * allows one at a time, where an attribute needed a hook to keep the two from
 * both being on.
 */

function IconButtonEdit( props ) {
	const { attributes, setAttributes } = props;
	const {
		fillOnSelect,
		icon = '',
		iconClass,
		iconSource,
		selectedIcon = '',
		showTooltips,
		text,
		action,
		actionTarget,
		width,
	} = attributes;
	const source = iconSource === 'registry' ? 'registry' : 'font';
	const isRegistry = source === 'registry';
	const iconRef = { source, name: icon, class: iconClass };
	const record = useIconRecord( iconRef );
	const selectedRef = { source, name: selectedIcon, class: iconClass };
	const selectedRecord = useIconRecord( selectedRef );
	// Which reference the Icon library is choosing for, or null when closed.
	const [ libraryTarget, setLibraryTarget ] = useState( null );
	const isToggle = isTogglable( attributes, props.context[ 'axismundi/togglable' ] );
	const fontOptions = fontFamilyOptions( useSettings( 'typography.fontFamilies' )[ 0 ] );
	// Every way an icon is chosen goes through here, so the name follows it.
	// One change, so one undo takes the icon and the label it brought.
	const setIconAttributes = ( changes ) => {
		const next =
			'iconSource' in changes ? { ...changes, selectedIcon: undefined } : { ...changes };
		if ( changes.icon && ! text ) {
			next.text = labelFromIcon( changes.icon );
		}
		setAttributes( next );
	};
	const isEmpty = isRegistry ? ! record.content : ! glyphName( icon );
	// Both icons, as render.php draws them, only for a toggle with a selected
	// icon that resolves; data-pressed shows one (assets/button.css).
	const hasSelectedIcon =
		isToggle && ! isEmpty && ( isRegistry ? !! selectedRecord.content : !! glyphName( selectedIcon ) );

	const renderContent = () => (
		<div
			className="wp-block-button__link wp-element-button"
			// The tooltip runtime reads the name out of .screen-reader-text
			// below, the same as on the page; the editor bridge attaches it to
			// the canvas (assets/editor-tooltip.js).
			data-ax-tooltip={ showTooltips !== false && text ? 'true' : undefined }
		>
			{ isEmpty && <IconPlaceholder style={ { height: 'auto' } } /> }
			{ ! isEmpty && (
				<IconElement
					icon={ iconRef }
					record={ record }
					className={ hasSelectedIcon ? 'ax-icon--unselected' : undefined }
				/>
			) }
			{ hasSelectedIcon && (
				<IconElement icon={ selectedRef } record={ selectedRecord } className="ax-icon--selected" />
			) }
			{ !! text && <span className="screen-reader-text">{ text }</span> }
		</div>
	);

	const settingsItems = (
		<>
			<ToolsPanelItem
				isShownByDefault
				label={ __( 'Width', 'axismundi-dialogs' ) }
				hasValue={ () => !! width }
				onDeselect={ () => setAttributes( { width: undefined } ) }
			>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Width', 'axismundi-dialogs' ) }
					value={ width ?? '' }
					options={ WIDTH_OPTIONS }
					onChange={ ( value ) => setAttributes( { width: value || undefined } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				isShownByDefault
				label={ __( 'Label', 'axismundi-dialogs' ) }
				hasValue={ () => !! text }
				onDeselect={ () => setAttributes( { text: undefined } ) }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Label', 'axismundi-dialogs' ) }
					help={
						text
							? __( 'The button’s name: read by screen readers, and shown as the tooltip.', 'axismundi-dialogs' )
							: __( 'An icon button has no visible label, so it needs this name. Without it, screen readers have nothing to announce and there is no tooltip to show.', 'axismundi-dialogs' )
					}
					value={ text || '' }
					onChange={ ( value ) => setAttributes( { text: value || undefined } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				isShownByDefault
				label={ __( 'Show tooltips', 'axismundi-dialogs' ) }
				hasValue={ () => showTooltips === false }
				onDeselect={ () => setAttributes( { showTooltips: undefined } ) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show tooltips', 'axismundi-dialogs' ) }
					checked={ showTooltips !== false }
					help={
						showTooltips !== false
							? __( 'The label appears on hover and on keyboard focus, including in the editor.', 'axismundi-dialogs' )
							: __( 'No tooltip. Screen readers still read the label.', 'axismundi-dialogs' )
					}
					onChange={ ( value ) => setAttributes( { showTooltips: value ? undefined : false } ) }
				/>
			</ToolsPanelItem>
		</>
	);

	const inspector = (
		<>
			<BlockControls group="other">
				{ isRegistry && (
					<ToolbarButton onClick={ () => setLibraryTarget( 'icon' ) }>
						{ icon ? __( 'Replace', 'axismundi-dialogs' ) : __( 'Choose icon', 'axismundi-dialogs' ) }
					</ToolbarButton>
				) }
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Icon', 'axismundi-dialogs' ) }>
					<div style={ { display: 'grid', gap: '16px' } }>
						<IconReferenceControls
							source={ source }
							icon={ icon }
							iconClass={ iconClass }
							fontOptions={ fontOptions }
							record={ record }
							// A new source resets both references: the two value
							// spaces do not overlap (shared/icon-controls.js).
							onChange={ setIconAttributes }
							onSourceChange={ () => setLibraryTarget( null ) }
						/>
					{ isToggle && (
							<div>
								{ /* A font axis: the registry has no fill to move, and a filled
								     icon there is a different icon - Icon(selected) below. */ }
								{ ! isRegistry && (
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __( 'Fill icon when selected', 'axismundi-dialogs' ) }
										checked={ fillOnSelect !== false }
										help={
											fillOnSelect !== false
												? __( 'M3’s selection cue: a variable icon font fills the icon. A font with no fill axis is unaffected - use Selected icon there.', 'axismundi-dialogs' )
												: __( 'The icon stays as it is; colour and shape carry the selection.', 'axismundi-dialogs' )
										}
										onChange={ ( value ) => setAttributes( { fillOnSelect: value ? undefined : false } ) }
									/>
								) }
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Selected icon', 'axismundi-dialogs' ) }
									help={
										isRegistry
											? __( 'Shown while the toggle is selected. In the icon library a filled icon is a separate icon. Leave empty to keep the same icon.', 'axismundi-dialogs' )
											: __( 'Shown while the toggle is selected. A variable icon font fills the same icon on its own, so this is for a font without a fill axis. Leave empty to keep the same icon.', 'axismundi-dialogs' )
									}
									value={ selectedIcon }
									onChange={ ( value ) => setAttributes( { selectedIcon: value || undefined } ) }
								/>
								{ isRegistry && (
									<Button
										__next40pxDefaultSize
										variant="secondary"
										onClick={ () => setLibraryTarget( 'selectedIcon' ) }
									>
										{ selectedIcon
											? __( 'Replace selected icon', 'axismundi-dialogs' )
											: __( 'Choose selected icon', 'axismundi-dialogs' ) }
									</Button>
								) }
							</div>
						) }
					</div>
				</PanelBody>
				<PanelBody title={ __( 'Action', 'axismundi-dialogs' ) } initialOpen={ !! action }>
					<div style={ { display: 'grid', gap: '16px' } }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Does', 'axismundi-dialogs' ) }
							value={ action ?? '' }
							options={ ACTION_OPTIONS }
							help={ __( 'What the button is for. Its type and ARIA follow from this — see Advanced.', 'axismundi-dialogs' ) }
							onChange={ ( value ) => setAttributes( actionAttributes( value ) ) }
						/>
						<ActionTargetControls
							action={ action }
							value={ actionTarget }
							onChange={ ( value ) => setAttributes( { actionTarget: value } ) }
						/>
					</div>
				</PanelBody>
			</InspectorControls>
			{ libraryTarget && isRegistry && (
				<IconLibraryModal
					value={ libraryTarget === 'selectedIcon' ? selectedIcon : icon }
					onClose={ () => setLibraryTarget( null ) }
					onChange={ ( next ) => {
						setIconAttributes( { [ libraryTarget ]: next } );
						setLibraryTarget( null );
					} }
				/>
			) }
		</>
	);

	return (
		<ButtonEdit
			{ ...props }
			renderContent={ renderContent }
			settingsLabel={ __( 'Display', 'axismundi-dialogs' ) }
			settingsItems={ settingsItems }
			inspector={ inspector }
			resetAttributes={ { width: undefined, showTooltips: undefined } }
			extraBlockProps={ {
				'data-width': width || undefined,
				'data-fill-on-select': fillOnSelect === false ? 'false' : undefined,
			} }
		/>
	);
}

registerBlockType( metadata, {
	icon: starEmpty,
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ BUTTON ],
				transform: toButton,
			},
		],
	},
	example: {
		attributes: { iconSource: 'font', icon: 'star', text: __( 'Favourite', 'axismundi-dialogs' ) },
	},
	variations: [
		{
			name: 'default',
			isDefault: true,
			// With the name the Label field would have been given: an icon
			// button inserted and left alone is still announced.
			attributes: { iconSource: 'font', icon: 'star', text: __( 'Star', 'axismundi-dialogs' ) },
		},
	],
	edit: IconButtonEdit,
	save: () => null,
} );
