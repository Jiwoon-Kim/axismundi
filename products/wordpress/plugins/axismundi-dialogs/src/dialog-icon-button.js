/**
 * axismundi/dialog-icon-button - an icon button (M3): dialog-button's shell
 * holding an icon instead of a label.
 *
 * Everything the two share is dialog-button's own editor (shared/button.js):
 * the wrapper, link editing, Size, Shape, Togglable, Selected and the advanced
 * controls. This file fills the slots that differ - the contents, the Icon
 * panel above Display, and Width and Standard in it - so the two cannot drift apart. Their attribute
 * names match for the same reason: converting one into the other keeps
 * everything they have in common.
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
	store as blockEditorStore,
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
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { starEmpty } from '@wordpress/icons';
import metadata from '../blocks/dialog-icon-button/block.json';
import { ButtonEdit } from './shared/button';
import { isTogglable } from './shared/selection';
import { IconElement, IconPlaceholder, glyphName, useIconRecord } from './shared/icon';
import { IconLibraryModal, IconReferenceControls, fontFamilyOptions } from './shared/icon-controls';

// M3's Width: the space either side of the icon, never the icon. "Default"
// stores nothing, and equals the container's height - a circle.
const WIDTH_OPTIONS = [
	{ label: __( 'Default', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Narrow', 'axismundi-dialogs' ), value: 'narrow' },
	{ label: __( 'Wide', 'axismundi-dialogs' ), value: 'wide' },
];

function styleOf( className ) {
	return ( className || '' ).match( /(?:^|\s)is-style-([^\s]+)/ )?.[ 1 ];
}

function withoutStyles( className ) {
	return ( className || '' ).replace( /(?:^|\s)is-style-[^\s]+/g, ' ' ).trim().replace( /\s+/g, ' ' ) || undefined;
}

/*
 * Colour is one axis in M3 - Filled, Tonal, Outlined, Standard - and two
 * mechanisms here. Filled, Tonal and Outlined are block styles, shared with
 * Button. Standard is an icon-button setting, `standard`, as the theme
 * switcher's cycle button has it: Button has no Standard, so as a block style
 * it would ride along in className when a Standard icon button became a
 * button, and land as a style that does not exist there. As an attribute it is
 * simply not carried over.
 *
 * The two must not be on at once. A local style and Standard exclude each
 * other: turning Standard on drops the local style, choosing a style turns
 * Standard off. The group's style is different - it is a default, and Standard
 * overrides it without touching it, so turning Standard off brings the group's
 * colour back. The effective colour is:
 *
 *   standard ? Standard : ( local style ?? group style ?? Filled )
 */
function useStandardExclusion( attributes, setAttributes ) {
	const { className, standard } = attributes;

	// A style picked in the Styles panel turns Standard off - but only as an
	// answer to that change, never on load (see useSelectionInvariant in
	// dialog-buttons.js for what a fix made on load does to undo). Folded into
	// the style change, so one undo takes both back: recorded on its own, undo
	// reverted the fix alone and left Standard on beside the style (measured).
	const { __unstableMarkNextChangeAsNotPersistent } = useDispatch( blockEditorStore );
	const lastClassName = useRef( className );
	useEffect( () => {
		const changed = lastClassName.current !== className;
		lastClassName.current = className;
		if ( changed && standard && styleOf( className ) ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { standard: undefined } );
		}
	}, [ className ] );

	return ( value ) =>
		setAttributes(
			value
				? { standard: true, className: withoutStyles( className ) }
				: { standard: undefined }
		);
}

function IconButtonEdit( props ) {
	const { attributes, setAttributes } = props;
	const { icon = '', iconClass, iconSource, selectedIcon = '', standard, text, width } = attributes;
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
	const setStandard = useStandardExclusion( attributes, setAttributes );
	const isEmpty = isRegistry ? ! record.content : ! glyphName( icon );
	// Both icons, as render.php draws them, only for a toggle with a selected
	// icon that resolves; data-pressed shows one (assets/button.css).
	const hasSelectedIcon =
		isToggle && ! isEmpty && ( isRegistry ? !! selectedRecord.content : !! glyphName( selectedIcon ) );

	const renderContent = () => (
		<div className="wp-block-button__link wp-element-button">
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
			<span className="screen-reader-text">{ text }</span>
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
		</>
	);

	// Last, as in the theme switcher's Display panel: it replaces the colour
	// the rest of the panel's setting do not touch.
	const settingsItemsAfter = (
		<ToolsPanelItem
			isShownByDefault
			label={ __( 'Standard icon button', 'axismundi-dialogs' ) }
			hasValue={ () => !! standard }
			onDeselect={ () => setStandard( false ) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Standard icon button', 'axismundi-dialogs' ) }
				checked={ !! standard }
				help={
					standard
						? __( 'No container. The icon alone carries the button.', 'axismundi-dialogs' )
						: __( 'The button takes the block’s colour treatment.', 'axismundi-dialogs' )
				}
				onChange={ setStandard }
			/>
		</ToolsPanelItem>
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
							onChange={ ( changes ) =>
								setAttributes(
									'iconSource' in changes ? { ...changes, selectedIcon: undefined } : changes
								)
							}
							onSourceChange={ () => setLibraryTarget( null ) }
						/>
						{ isToggle && (
							<div>
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
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Label', 'axismundi-dialogs' ) }
							help={
								text
									? __( 'The button’s name: read by screen readers, never shown.', 'axismundi-dialogs' )
									: __( 'An icon button has no visible label, so it needs this name. Without it, screen readers have nothing to announce.', 'axismundi-dialogs' )
							}
							value={ text || '' }
							onChange={ ( value ) => setAttributes( { text: value || undefined } ) }
						/>
					</div>
				</PanelBody>
			</InspectorControls>
			{ libraryTarget && isRegistry && (
				<IconLibraryModal
					value={ libraryTarget === 'selectedIcon' ? selectedIcon : icon }
					onClose={ () => setLibraryTarget( null ) }
					onChange={ ( next ) => {
						setAttributes( { [ libraryTarget ]: next } );
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
			settingsItemsAfter={ settingsItemsAfter }
			inspector={ inspector }
			resetAttributes={ { width: undefined, standard: undefined } }
			extraBlockProps={ {
				'data-width': width || undefined,
				'data-standard': standard ? 'true' : undefined,
			} }
		/>
	);
}

registerBlockType( metadata, {
	icon: starEmpty,
	example: {
		attributes: { iconSource: 'font', icon: 'star', text: __( 'Favourite', 'axismundi-dialogs' ) },
	},
	variations: [
		{
			name: 'default',
			isDefault: true,
			attributes: { iconSource: 'font', icon: 'star' },
		},
	],
	edit: IconButtonEdit,
	save: () => null,
} );
