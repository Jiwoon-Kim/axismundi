/**
 * Shared edit/save for axismundi/dialog-button and axismundi/dialog-icon-button.
 *
 * A copy of core/button (packages/block-library/src/button), kept as close to
 * it as a plugin can: same attributes, same saved markup, same link editing,
 * same Enter-to-split behaviour. These blocks are an experiment toward an
 * improved core/button - one that is also a dialog trigger, and later takes
 * M3 sizes, icons and selection - so staying diffable against core is the
 * point.
 *
 * Three pieces of core/button cannot be copied, because they are not public to
 * plugins in WordPress 7.1:
 *
 *   HTMLElementControl          private block-editor API. Unlocking it from a
 *                               plugin throws at load and took the whole block
 *                               down. Replaced by SelectControl, which is how
 *                               core offered the same choice before the
 *                               control existed.
 *   subscribeDelegatedListener  private compose API. Replaced by a capture-
 *                               phase listener on the same element.
 *   Link (@wordpress/ui)        no `wp-ui` script is registered in 7.1, so the
 *                               import would add a missing dependency and the
 *                               script would never enqueue. Replaced by the
 *                               public ExternalLink.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useEffect,
	useState,
	useRef,
	useMemo,
	createInterpolateElement,
} from '@wordpress/element';
import {
	BaseControl,
	ExternalLink,
	Popover,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	// Public in WordPress 7.1 only under these names; core/social-links
	// imports them the same way for its Icon size control.
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import {
	BlockControls,
	InspectorControls,
	LinkControl,
	RichText,
	store as blockEditorStore,
	useBlockEditingMode,
	useBlockProps,
} from '@wordpress/block-editor';
import { displayShortcut, isKeyboardEvent, ENTER } from '@wordpress/keycodes';
import { link, linkOff } from '@wordpress/icons';
import {
	cloneBlock,
	createBlock,
	getBlockBindingsSource,
	getDefaultBlockName,
} from '@wordpress/blocks';
import { useMergeRefs, useRefEffect } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { prependHTTPS } from '@wordpress/url';
import { isTogglable } from './selection';
import { actionMarkup } from './action-controls';

// M3 button sizes. "Default" stores nothing: the button inherits the size of
// its Dialog Button Group, and falls back to Small. An explicit size - Small
// included - holds that size against the group. The two must stay separate
// options, since inheriting Medium and holding Small are different choices.
const SIZE_OPTIONS = [
	{ label: __( 'Default', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Extra small', 'axismundi-dialogs' ), value: 'xsmall' },
	{ label: __( 'Small', 'axismundi-dialogs' ), value: 'small' },
	{ label: __( 'Medium', 'axismundi-dialogs' ), value: 'medium' },
	{ label: __( 'Large', 'axismundi-dialogs' ), value: 'large' },
	{ label: __( 'Extra large', 'axismundi-dialogs' ), value: 'xlarge' },
];

// M3 calls this axis "Type" in Figma; the editor says "Shape", since a button
// "type" is about to mean something else here (button, toggle, selection).
// "Default" stores nothing and inherits the group's shape, falling back to
// round; an explicit Round holds round against a Square group.
const SHAPE_OPTIONS = [
	{ label: __( 'Default', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Round', 'axismundi-dialogs' ), value: 'round' },
	{ label: __( 'Square', 'axismundi-dialogs' ), value: 'square' },
];

// M3's "Togglable" (Figma's name for the toggle variant). "Default" stores
// nothing and follows the group; On and Off hold against it, so a group of
// actions can hold one toggle and a togglable group one plain action.
const TOGGLABLE_OPTIONS = [
	{ label: __( 'Default', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'On', 'axismundi-dialogs' ), value: 'on' },
	{ label: __( 'Off', 'axismundi-dialogs' ), value: 'off' },
];

const NEW_TAB_REL = 'noopener';
const NEW_TAB_TARGET = '_blank';
const NOFOLLOW_REL = 'nofollow';

const LINK_SETTINGS = [
	...LinkControl.DEFAULT_LINK_SETTINGS,
	{
		id: 'nofollow',
		title: __( 'Mark as nofollow', 'axismundi-dialogs' ),
	},
];

// core/button's element class, `__experimentalGetElementClassName( 'button' )`,
// written out so the saved markup does not depend on an experimental export.
const ELEMENT_BUTTON_CLASS = 'wp-element-button';

// core: utils/remove-anchor-tag.js.
function removeAnchorTag( value ) {
	return value.toString().replace( /<\/?a[^>]*>/g, '' );
}

// core: button/get-updated-link-attributes.js, unchanged.
function getUpdatedLinkAttributes( { rel = '', url = '', opensInNewTab, nofollow } ) {
	let newLinkTarget;
	let updatedRel = rel;

	if ( opensInNewTab ) {
		newLinkTarget = NEW_TAB_TARGET;
		updatedRel = updatedRel?.includes( NEW_TAB_REL )
			? updatedRel
			: updatedRel + ` ${ NEW_TAB_REL }`;
	} else {
		const relRegex = new RegExp( `\\b${ NEW_TAB_REL }\\s*`, 'g' );
		updatedRel = updatedRel?.replace( relRegex, '' ).trim();
	}

	if ( nofollow ) {
		updatedRel = updatedRel?.includes( NOFOLLOW_REL )
			? updatedRel
			: ( updatedRel + ` ${ NOFOLLOW_REL }` ).trim();
	} else {
		const relRegex = new RegExp( `\\b${ NOFOLLOW_REL }\\s*`, 'g' );
		updatedRel = updatedRel?.replace( relRegex, '' ).trim();
	}

	return {
		url: prependHTTPS( url ),
		linkTarget: newLinkTarget,
		rel: updatedRel || undefined,
	};
}

// core: button/edit.jsx useEnter. Enter in an empty button splits the group
// around a default block, as core/button does inside core/buttons.
function useEnter( clientId ) {
	const { replaceBlocks, selectionChange } = useDispatch( blockEditorStore );
	const { getBlock, getBlockAttributes, getBlockRootClientId, getBlockIndex } =
		useSelect( blockEditorStore );

	return useRefEffect( ( element ) => {
		function onKeyDown( event ) {
			if ( event.defaultPrevented || event.keyCode !== ENTER ) {
				return;
			}
			const { text } = getBlockAttributes( clientId ) ?? {};
			if ( text?.length ) {
				return;
			}
			event.preventDefault();
			const topParentListBlock = getBlock( getBlockRootClientId( clientId ) );
			const blockIndex = getBlockIndex( clientId );
			const head = cloneBlock( {
				...topParentListBlock,
				innerBlocks: topParentListBlock.innerBlocks.slice( 0, blockIndex ),
			} );
			const middle = createBlock( getDefaultBlockName() );
			const after = topParentListBlock.innerBlocks.slice( blockIndex + 1 );
			const tail = after.length
				? [ cloneBlock( { ...topParentListBlock, innerBlocks: after } ) ]
				: [];
			replaceBlocks( topParentListBlock.clientId, [ head, middle, ...tail ], 1 );
			selectionChange( middle.clientId );
		}

		// Capture phase, as core, so this runs before writing-flow's handlers.
		element.addEventListener( 'keydown', onKeyDown, true );
		return () => element.removeEventListener( 'keydown', onKeyDown, true );
	}, [] );
}

// The button's side of the group's selection rule: turning one toggle on in
// single mode turns the other toggles off, and the last selected toggle cannot
// be turned off while a selection is required. Buttons that are not toggles
// are not counted and not touched. The group repairs anything else (see
// useSelectionInvariant in dialog-button-group.js).
function useSelectedToggle( clientId, selected, group ) {
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const { getBlockAttributes, getBlockOrder, getBlockRootClientId } =
		useSelect( blockEditorStore );
	const toggleIds = () =>
		getBlockOrder( getBlockRootClientId( clientId ) ).filter( ( id ) =>
			isTogglable( getBlockAttributes( id ) ?? {}, group.togglable )
		);
	const selectedCount = useSelect(
		( select ) => {
			const store = select( blockEditorStore );
			return store
				.getBlockOrder( store.getBlockRootClientId( clientId ) )
				.map( ( id ) => store.getBlockAttributes( id ) ?? {} )
				.filter( ( button ) => isTogglable( button, group.togglable ) && button.selected )
				.length;
		},
		[ clientId, group.togglable ]
	);

	const isLocked = !! group.selectionRequired && !! selected && selectedCount === 1;

	function setSelected( value ) {
		if ( ! value ) {
			if ( ! isLocked ) {
				updateBlockAttributes( clientId, { selected: undefined } );
			}
			return;
		}
		const changes = { [ clientId ]: { selected: true } };
		if ( group.selection === 'single' ) {
			toggleIds()
				.filter( ( id ) => id !== clientId )
				.forEach( ( id ) => {
					changes[ id ] = { selected: undefined };
				} );
		}
		// One dispatch, so the whole change is one undo step.
		updateBlockAttributes( Object.keys( changes ), changes, { uniqueByBlock: true } );
	}

	return { isLocked, setSelected };
}

/**
 * The button editor. Used as is by dialog-button; dialog-icon-button passes
 * the slots below to change what the shell holds, and keeps everything else -
 * the wrapper, link editing, the Settings panel, the advanced controls - the
 * same, so the two cannot drift apart.
 *
 * @param {Object}   props                 Block edit props, plus:
 * @param {Function} props.renderContent   Draws the control's contents instead
 *                                         of the editable label.
 * @param {Element}  props.iconSlot        Drawn inside the control, before the
 *                                         editable label, which is then wrapped
 *                                         in a span of its own.
 * @param {Element}  props.settingsItems   Extra Settings items, after Shape.
 * @param {Element}  props.settingsItemsAfter Extra Settings items, last.
 * @param {Element}  props.inspector       Extra inspector panels.
 * @param {Object}   props.extraBlockProps Extra wrapper attributes.
 * @param {Object}   props.resetAttributes What Reset all also clears, for the
 *                                         extra Settings items.
 * @param {string}   props.settingsLabel   The Settings panel's title.
 */
export function ButtonEdit( props ) {
	const {
		attributes,
		setAttributes,
		isSelected,
		onReplace,
		mergeBlocks,
		clientId,
		context,
		renderContent,
		iconSlot,
		settingsItems,
		inspector,
		extraBlockProps,
		resetAttributes,
		settingsLabel,
		settingsItemsAfter,
	} = props;
	const {
		tagName,
		disabled,
		linkTarget,
		placeholder,
		rel,
		selected,
		shape,
		size,
		text,
		togglable,
		url,
		metadata,
	} = attributes;
	const group = {
		togglable: context[ 'axismundi/togglable' ],
		selection: context[ 'axismundi/selection' ],
		selectionRequired: context[ 'axismundi/selectionRequired' ],
	};
	const isToggle = isTogglable( attributes, group.togglable );
	const { isLocked, setSelected } = useSelectedToggle( clientId, selected, group );

	// An action is a <button>'s whatever was stored (src/shared/action-controls.js).
	const TagName = attributes.action ? 'button' : tagName || 'button';

	function onKeyDown( event ) {
		if ( isKeyboardEvent.primary( event, 'k' ) ) {
			startEditing( event );
		} else if ( isKeyboardEvent.primaryShift( event, 'k' ) ) {
			unlink();
			richTextRef.current?.focus();
		}
	}

	const [ popoverAnchor, setPopoverAnchor ] = useState( null );
	const ref = useRef();
	const richTextRef = useRef();
	// The wrapper keeps core's class so the theme's button styles apply to it.
	const blockProps = useBlockProps( {
		className: 'wp-block-button',
		// Absent when unset, so the editor draws exactly what save() writes.
		'data-size': size || undefined,
		'data-shape': shape || undefined,
		// The editor's stand-in: the control is a div there, so it is neither
		// :disabled nor a link without an href.
		'data-disabled': disabled ? 'true' : undefined,
		// Editor-only stand-in for the front end's aria-pressed, which the
		// contenteditable label cannot carry. Never saved; see assets/button.css.
		'data-pressed': isToggle ? String( !! selected ) : undefined,
		...extraBlockProps,
		ref: useMergeRefs( [ setPopoverAnchor, ref ] ),
		onKeyDown,
	} );
	const blockEditingMode = useBlockEditingMode();

	const [ isEditingURL, setIsEditingURL ] = useState( false );
	const isURLSet = !! url;
	const opensInNewTab = linkTarget === NEW_TAB_TARGET;
	const nofollow = !! rel?.includes( NOFOLLOW_REL );
	const isLinkTag = 'a' === TagName;

	const { createPageEntity, userCanCreatePages, lockUrlControls = false } = useSelect(
		( select ) => {
			if ( ! isSelected ) {
				return {};
			}
			const settings = select( blockEditorStore ).getSettings();
			const blockBindingsSource = getBlockBindingsSource(
				metadata?.bindings?.url?.source
			);
			return {
				createPageEntity: settings.__experimentalCreatePageEntity,
				userCanCreatePages: settings.__experimentalUserCanCreatePages,
				lockUrlControls:
					!! metadata?.bindings?.url &&
					! blockBindingsSource?.canUserEditValue?.( {
						select,
						context,
						args: metadata?.bindings?.url?.args,
					} ),
			};
		},
		[ context, isSelected, metadata?.bindings?.url ]
	);

	async function handleCreate( pageTitle ) {
		const page = await createPageEntity( { title: pageTitle, status: 'draft' } );
		return {
			id: page.id,
			type: page.type,
			title: page.title.rendered,
			url: page.link,
			kind: 'post-type',
		};
	}

	function createButtonText( searchTerm ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %s: search term. */
				__( 'Create page: <mark>%s</mark>', 'axismundi-dialogs' ),
				searchTerm
			),
			{ mark: <mark /> }
		);
	}

	function startEditing( event ) {
		event.preventDefault();
		setIsEditingURL( true );
	}

	function unlink() {
		setAttributes( { url: undefined, linkTarget: undefined, rel: undefined } );
		setIsEditingURL( false );
	}

	useEffect( () => {
		if ( ! isSelected ) {
			setIsEditingURL( false );
		}
	}, [ isSelected ] );

	// core: memoised so LinkControl's internal state is not overridden.
	const linkValue = useMemo(
		() => ( { url, opensInNewTab, nofollow } ),
		[ url, opensInNewTab, nofollow ]
	);

	const useEnterRef = useEnter( clientId );
	const mergedRef = useMergeRefs( [ useEnterRef, richTextRef ] );

	/*
	 * The editable label. With an icon beside it, it becomes a span inside the
	 * control rather than being the control itself - the control has to hold two
	 * things, and an icon inside editable text would be editable text. Without an
	 * icon it is the control, which is the markup core/button saves and what
	 * render.php writes back (blocks/dialog-button/render.php).
	 */
	const label = (
		<RichText
			ref={ mergedRef }
			/* A div by default, as core edits in; as the label beside an icon
			   it has to be the span render.php writes, or editor and page
			   differ in structure. */
			tagName={ iconSlot ? 'span' : undefined }
			aria-label={ __( 'Button text', 'axismundi-dialogs' ) }
			placeholder={ placeholder || __( 'Add text…', 'axismundi-dialogs' ) }
			value={ text }
			onChange={ ( value ) => setAttributes( { text: removeAnchorTag( value ) } ) }
			withoutInteractiveFormatting
			className={
				iconSlot
					? 'wp-block-button__label'
					: `wp-block-button__link ${ ELEMENT_BUTTON_CLASS }`
			}
			onReplace={ onReplace }
			onMerge={ mergeBlocks }
			identifier="text"
		/>
	);
	const content = iconSlot ? (
		<div className={ `wp-block-button__link ${ ELEMENT_BUTTON_CLASS }` }>
			{ iconSlot }
			{ label }
		</div>
	) : (
		label
	);

	const hasNonContentControls = blockEditingMode === 'default';
	const hasBlockControls = hasNonContentControls || ( isLinkTag && ! lockUrlControls );

	return (
		<>
			<div { ...blockProps }>
				{ /* Edits in a div, as core does: a contenteditable <button>
				   swallows typing, and tagName only decides the saved element. */ }
				{ renderContent ? renderContent() : content }
			</div>
			{ hasBlockControls && (
				<BlockControls group="block">
					{ isLinkTag && ! lockUrlControls && (
						<ToolbarButton
							name="link"
							icon={ ! isURLSet ? link : linkOff }
							title={
								! isURLSet
									? __( 'Link', 'axismundi-dialogs' )
									: __( 'Unlink', 'axismundi-dialogs' )
							}
							shortcut={
								! isURLSet
									? displayShortcut.primary( 'k' )
									: displayShortcut.primaryShift( 'k' )
							}
							onClick={ ! isURLSet ? startEditing : unlink }
							isActive={ isURLSet }
						/>
					) }
				</BlockControls>
			) }
			{ isLinkTag && isSelected && ( isEditingURL || isURLSet ) && ! lockUrlControls && (
				<Popover
					placement="bottom"
					onClose={ () => {
						setIsEditingURL( false );
						richTextRef.current?.focus();
					} }
					anchor={ popoverAnchor }
					focusOnMount={ isEditingURL ? 'firstElement' : false }
					__unstableSlotName="__unstable-block-tools-after"
					shift
				>
					<LinkControl
						value={ linkValue }
						onChange={ ( {
							url: newURL,
							opensInNewTab: newOpensInNewTab,
							nofollow: newNofollow,
						} ) =>
							setAttributes(
								getUpdatedLinkAttributes( {
									rel,
									url: newURL,
									opensInNewTab: newOpensInNewTab,
									nofollow: newNofollow,
								} )
							)
						}
						onRemove={ () => {
							unlink();
							richTextRef.current?.focus();
						} }
						forceIsEditingLink={ isEditingURL }
						settings={ LINK_SETTINGS }
						createSuggestion={ createPageEntity && handleCreate }
						withCreateSuggestion={ userCanCreatePages }
						createSuggestionButtonText={ createButtonText }
					/>
				</Popover>
			) }
			{ /* The slot's panels come first: an icon button's Icon panel sits
			   above its Display panel, as dialog-icon's does. */ }
			{ inspector }
			{ /* Settings tab, as core/social-links places its Icon size. */ }
			<InspectorControls>
				<ToolsPanel
					label={ settingsLabel || __( 'Settings', 'axismundi-dialogs' ) }
					resetAll={ () =>
						setAttributes( {
							size: undefined,
							shape: undefined,
							togglable: undefined,
					disabled: undefined,
							...resetAttributes,
						} )
					}
				>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Size', 'axismundi-dialogs' ) }
						hasValue={ () => !! size }
						onDeselect={ () => setAttributes( { size: undefined } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Size', 'axismundi-dialogs' ) }
							value={ size ?? '' }
							options={ SIZE_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { size: value === '' ? undefined : value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Shape', 'axismundi-dialogs' ) }
						hasValue={ () => !! shape }
						onDeselect={ () => setAttributes( { shape: undefined } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Shape', 'axismundi-dialogs' ) }
							value={ shape ?? '' }
							options={ SHAPE_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { shape: value === '' ? undefined : value } )
							}
						/>
					</ToolsPanelItem>
					{ settingsItems }
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Togglable', 'axismundi-dialogs' ) }
						hasValue={ () => togglable !== undefined }
						onDeselect={ () => setAttributes( { togglable: undefined } ) }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Togglable', 'axismundi-dialogs' ) }
							value={ togglable === undefined ? '' : ( togglable ? 'on' : 'off' ) }
							options={ TOGGLABLE_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( {
									togglable: value === '' ? undefined : value === 'on',
								} )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						isShownByDefault
						label={ __( 'Disabled', 'axismundi-dialogs' ) }
						hasValue={ () => !! disabled }
						onDeselect={ () => setAttributes( { disabled: undefined } ) }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Disabled', 'axismundi-dialogs' ) }
							checked={ !! disabled }
							help={
								disabled
									? __( 'Shown, but cannot be pressed or followed.', 'axismundi-dialogs' )
									: __( 'The button works normally.', 'axismundi-dialogs' )
							}
							onChange={ ( value ) => setAttributes( { disabled: value || undefined } ) }
						/>
					</ToolsPanelItem>
					{ /* Selected belongs to a toggle, so it is shown only on one.
					   Not part of the panel's reset: Reset all would otherwise
					   break a required selection, and a toggle's initial state is
					   content, not a setting to fall back from. Full width, as a
					   ToolsPanelItem would be: the panel is a two-column grid. */ }
					{ isToggle && (
						<div style={ { gridColumn: '1 / -1' } }>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Selected', 'axismundi-dialogs' ) }
								help={
									isLocked
										? __(
											'The group requires a selection, and this is the only selected toggle.',
											'axismundi-dialogs'
										)
										: __( 'Selected when the page loads.', 'axismundi-dialogs' )
								}
								checked={ !! selected }
								disabled={ isLocked }
								onChange={ setSelected }
							/>
						</div>
					) }
					{ settingsItemsAfter }
				</ToolsPanel>
			</InspectorControls>
			<InspectorControls group="advanced">
				<BaseControl
					__nextHasNoMarginBottom
					label={ __( 'Rendered element', 'axismundi-dialogs' ) }
					help={ __( 'What the settings above produce. Set by the Action, not edited here.', 'axismundi-dialogs' ) }
				>
					<code className="axismundi-button__markup">
						{ actionMarkup( { ...attributes, togglable: isToggle }, TagName ) }
					</code>
				</BaseControl>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'HTML element', 'axismundi-dialogs' ) }
					value={ TagName }
					// A link cannot be a toggle, and cannot carry an Action, so <a>
					// is offered on neither; the group turns an existing toggle
					// link into a <button>, and choosing an Action does the same.
					options={ [
						{ label: __( 'Default (<button>)', 'axismundi-dialogs' ), value: 'button' },
						...( isToggle || attributes.action ? [] : [ { label: '<a>', value: 'a' } ] ),
					] }
					onChange={ ( value ) => setAttributes( { tagName: value } ) }
				/>
				{ isLinkTag && (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Link relation', 'axismundi-dialogs' ) }
						help={ createInterpolateElement(
							__(
								'The <a>Link Relation</a> attribute defines the relationship between a linked resource and the current document.',
								'axismundi-dialogs'
							),
							{
								a: (
									<ExternalLink href="https://developer.mozilla.org/docs/Web/HTML/Attributes/rel" />
								),
							}
						) }
						value={ rel || '' }
						onChange={ ( newRel ) => setAttributes( { rel: newRel } ) }
					/>
				) }
			</InspectorControls>
		</>
	);
}

export function buttonSave( { attributes } ) {
	const { tagName, type, linkTarget, rel, shape, size, text, url } = attributes;
	const TagName = tagName || 'button';
	const isButtonTag = 'button' === TagName;
	const buttonType = type || 'button';

	// data-size and data-shape are written only when stored, so blocks saved
	// before the attributes existed keep their exact markup and stay valid.
	return (
		<div
			{ ...useBlockProps.save( {
				className: 'wp-block-button',
				'data-size': size || undefined,
				'data-shape': shape || undefined,
			} ) }
		>
			<RichText.Content
				tagName={ TagName }
				type={ isButtonTag ? buttonType : null }
				className={ `wp-block-button__link ${ ELEMENT_BUTTON_CLASS }` }
				href={ isButtonTag ? null : url }
				value={ text }
				target={ isButtonTag ? null : linkTarget }
				rel={ isButtonTag ? null : rel }
			/>
		</div>
	);
}

// core: button/index.js `merge`. Without it, onMerge has nothing to call.
export function mergeButtons( a, { text = '' } ) {
	return { ...a, text: ( a.text || '' ) + text };
}
