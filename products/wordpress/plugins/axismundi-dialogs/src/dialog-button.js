/**
 * axismundi/dialog-button - core/button copy. See shared/button.js.
 *
 * Two things are no longer a copy, both because of the icon:
 *
 *   It renders on the server. A registry icon resolves through wp_get_icon(),
 *   which a save() cannot call - see blocks/dialog-button/render.php for the
 *   whole argument, and why core cannot make the same move.
 *
 *   The label is stored in the block comment. Nothing is parsed back out of
 *   saved markup any more, so `url`, `text`, `linkTarget` and `rel` lost their
 *   sources. The deprecation below keeps buttons saved the old way readable,
 *   and lifts those four out of their markup on the way in.
 *
 * The icon itself is the shared primitive (shared/icon.js, includes/icon.php) -
 * the same one dialog-icon draws standalone and dialog-icon-button draws alone
 * in a button. M3 puts it before the label; `icon_gap` is the space between
 * them, and both come from the button's size (assets/button.css).
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { BlockControls, InspectorControls, useSettings } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { button as icon } from '@wordpress/icons';
import metadata from '../blocks/dialog-button/block.json';
import { ButtonEdit, buttonSave, mergeButtons } from './shared/button';
import { isTogglable } from './shared/selection';
import { ICON_BUTTON, toIconButton } from './shared/button-transforms';
import { IconElement, glyphName, useIconRecord } from './shared/icon';
import { ACTION_OPTIONS, actionAttributes } from './shared/action-controls';
import { ActionTargetControls } from './shared/action-targets';
import {
	DEFAULT_ICONS,
	IconLibraryModal,
	IconReferenceControls,
	fontFamilyOptions,
} from './shared/icon-controls';

function DialogButtonEdit( props ) {
	const { attributes, setAttributes } = props;
	const {
		action,
		actionTarget,
		fillOnSelect,
		icon: iconName = '',
		iconClass,
		iconSource,
		selectedIcon = '',
		showIcon,
	} = attributes;
	const source = iconSource === 'registry' ? 'registry' : 'font';
	const isRegistry = source === 'registry';
	const iconRef = { source, name: iconName, class: iconClass };
	const record = useIconRecord( iconRef );
	const selectedRef = { source, name: selectedIcon, class: iconClass };
	const selectedRecord = useIconRecord( selectedRef );
	// Which reference the Icon library is choosing for, or null when closed.
	const [ libraryTarget, setLibraryTarget ] = useState( null );
	const isToggle = isTogglable( attributes, props.context[ 'axismundi/togglable' ] );
	const fontOptions = fontFamilyOptions( useSettings( 'typography.fontFamilies' )[ 0 ] );

	/*
	 * The icon is a setting of its own - M3: "Can contain an optional leading
	 * icon" - not a side effect of a name being stored. Off is the resting
	 * state and nothing is drawn for it; no placeholder either, since an empty
	 * box beside a label would read as a fault rather than an invitation.
	 *
	 * Turning it off keeps the icon the button had, the way `selected` survives
	 * on a button that has stopped being a toggle: turning it back on restores
	 * the choice instead of starting again at the default.
	 */
	const hasIcon =
		!! showIcon && ( isRegistry ? !! record.content : !! glyphName( iconName ) );
	// On with nothing chosen yet would draw nothing, so the first turn brings
	// the source's default with it (shared/icon-controls.js).
	const setShowIcon = ( value ) =>
		setAttributes(
			value
				? { showIcon: true, icon: iconName || DEFAULT_ICONS[ source ] }
				: { showIcon: undefined }
		);
	// Both icons, as render.php draws them, only for a toggle whose selected
	// icon resolves; data-pressed shows one (assets/button.css).
	const hasSelectedIcon =
		isToggle && hasIcon && ( isRegistry ? !! selectedRecord.content : !! glyphName( selectedIcon ) );

	/*
	 * Keyed on the SETTING, not on whether the icon resolves.
	 *
	 * The slot decides the label's element - a span inside the control with an
	 * icon, the control itself without one - so anything that flips it
	 * remounts the RichText, and a remounted RichText in a selected block
	 * takes the caret. Keyed on resolution, emptying the Icon field did that
	 * mid-keystroke: focus jumped to the label and the rest of the typing -
	 * backspaces included - landed there, deleting it. Reported, and the
	 * reason this is `showIcon`, which only a deliberate switch changes.
	 *
	 * So an unresolvable name leaves the slot in place and empty, which is
	 * also what render.php does with it.
	 */
	const iconSlot = showIcon ? (
		<>
			{ hasIcon && (
				<IconElement
					icon={ iconRef }
					record={ record }
					className={ hasSelectedIcon ? 'ax-icon--unselected' : undefined }
				/>
			) }
			{ hasSelectedIcon && (
				<IconElement icon={ selectedRef } record={ selectedRecord } className="ax-icon--selected" />
			) }
		</>
	) : null;

	// A new source resets both references: the two value spaces do not overlap
	// (shared/icon-controls.js).
	const setIconAttributes = ( changes ) =>
		setAttributes(
			'iconSource' in changes ? { ...changes, selectedIcon: undefined } : changes
		);

	const inspector = (
		<>
			<BlockControls group="other">
				{ isRegistry && hasIcon && (
					<ToolbarButton onClick={ () => setLibraryTarget( 'icon' ) }>
						{ __( 'Replace', 'axismundi-dialogs' ) }
					</ToolbarButton>
				) }
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Icon', 'axismundi-dialogs' ) } initialOpen={ !! showIcon }>
					<div style={ { display: 'grid', gap: '16px' } }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show icon', 'axismundi-dialogs' ) }
							checked={ !! showIcon }
							help={ __( 'A leading icon, before the label.', 'axismundi-dialogs' ) }
							onChange={ setShowIcon }
						/>
						{ !! showIcon && (
							<>
								<IconReferenceControls
									source={ source }
									icon={ iconName }
									iconClass={ iconClass }
									fontOptions={ fontOptions }
									record={ record }
									onChange={ setIconAttributes }
									onSourceChange={ () => setLibraryTarget( null ) }
								/>
						{ isToggle && hasIcon && (
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
							</>
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
					value={ libraryTarget === 'selectedIcon' ? selectedIcon : iconName }
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
			iconSlot={ iconSlot }
			inspector={ inspector }
			extraBlockProps={ {
				'data-fill-on-select': fillOnSelect === false ? 'false' : undefined,
			} }
		/>
	);
}

/*
 * Buttons saved before this block rendered on the server. Their markup is still
 * the source of `url`, `text`, `linkTarget` and `rel`, so the old attribute
 * definitions are kept here to parse them out; the block's own definitions have
 * no source, and the parsed values land in them unchanged - all but the label,
 * whose type changes on the way (see `migrate` below).
 */
const deprecated = [
	{
		attributes: {
			...metadata.attributes,
			url: {
				type: 'string',
				source: 'attribute',
				selector: 'a',
				attribute: 'href',
				role: 'content',
			},
			text: {
				type: 'rich-text',
				source: 'rich-text',
				selector: 'a,button',
				role: 'content',
			},
			linkTarget: {
				type: 'string',
				source: 'attribute',
				selector: 'a',
				attribute: 'target',
				role: 'content',
			},
			rel: {
				type: 'string',
				source: 'attribute',
				selector: 'a',
				attribute: 'rel',
				role: 'content',
			},
		},
		supports: metadata.supports,
		save: buttonSave,
		/*
		 * `source: 'rich-text'` hands back a RichTextData object, not a string.
		 * Carried into an attribute typed `string` it survives in the editor -
		 * RichText reads it - and then serialises into the block comment as
		 * `{}`, losing the label. Measured: a migrated button showed its text
		 * and stored `"text":{}`. The only change is the value's type.
		 */
		migrate: ( attributes ) => ( {
			...attributes,
			text: attributes.text ? attributes.text.toString() : undefined,
		} ),
	},
];

registerBlockType( metadata, {
	icon,
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ ICON_BUTTON ],
				transform: toIconButton,
			},
		],
	},
	edit: DialogButtonEdit,
	save: () => null,
	merge: mergeButtons,
	deprecated,
} );
