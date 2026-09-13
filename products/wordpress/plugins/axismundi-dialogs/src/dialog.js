/**
 * axismundi/dialog - the <dialog> host at the root of a Dialog Surface part.
 *
 * A copy of core/group (Gutenberg trunk 68e2ecefe2,
 * packages/block-library/src/group: edit.jsx, placeholder.jsx), edited in the
 * Site Editor inside a dialog-surface template part (includes/surface.php).
 * What changed, and why:
 *
 *   element     Fixed to <dialog>, so no tagName attribute and no element
 *               control. The canvas shows the surface as a div: an open
 *               <dialog> in the editor would sit in the top layer above the
 *               editor itself. The markup it stands for is
 *               blocks/dialog/render.php.
 *   layout      A vertical flex column that cannot be switched (block.json):
 *               Header and Actions keep their size and Content scrolls
 *               (blocks/dialog/style.css). Content width belongs to the Content
 *               group inside, not the dialog. There is no inner-container div:
 *               the inner blocks are the dialog's own children, on the front
 *               end and here.
 *   variations  The group's Group / Row / Stack / Grid become the four M3
 *               spec pages: Dialog - Basic, Dialog - Full screen, Sheets -
 *               Bottom, Sheets - Side. Everything finer is a setting or design.
 *   name        core's ariaLabel support registers the attribute and nothing
 *               to edit it with, so the Label field below is the control.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType, store as blocksStore } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	Path,
	Placeholder,
	SVG,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { layout as icon } from '@wordpress/icons';
import metadata from '../blocks/dialog/block.json';

/*
 * One variation per M3 component spec page - Dialogs (basic, full-screen),
 * Bottom sheets, Side sheets - and nothing finer. Modality, edge, attachment,
 * the drag handle and dismissal are settings in the Surface panel, and the
 * content is design (the starter patterns), so switching variation keeps them.
 * Matched on presentation alone for the same reason.
 */
const VARIATIONS = [
	{
		name: 'dialog-basic',
		title: __( 'Dialog - Basic', 'axismundi-dialogs' ),
		description: __(
			'A modal dialog that asks for a decision.',
			'axismundi-dialogs'
		),
		attributes: { presentation: 'dialog-basic' },
		isDefault: true,
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: [ 'presentation' ],
	},
	{
		name: 'dialog-full-screen',
		title: __( 'Dialog - Full screen', 'axismundi-dialogs' ),
		description: __(
			'A dialog that fills a compact window for a task with several steps.',
			'axismundi-dialogs'
		),
		attributes: { presentation: 'dialog-full-screen' },
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: [ 'presentation' ],
	},
	{
		name: 'sheet-bottom',
		title: __( 'Sheets - Bottom', 'axismundi-dialogs' ),
		description: __(
			'A sheet anchored to the bottom of the window, for supplementary content.',
			'axismundi-dialogs'
		),
		attributes: { presentation: 'sheet-bottom' },
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: [ 'presentation' ],
	},
	{
		name: 'sheet-side',
		title: __( 'Sheets - Side', 'axismundi-dialogs' ),
		description: __(
			'A sheet on the side of the window, for supplementary content.',
			'axismundi-dialogs'
		),
		attributes: { presentation: 'sheet-side' },
		scope: [ 'block', 'inserter', 'transform' ],
		isActive: [ 'presentation' ],
	},
];

const DISMISSALS = [
	{
		label: __( 'Scrim, Escape or a button', 'axismundi-dialogs' ),
		value: 'any',
	},
	{
		label: __( 'Escape or a button', 'axismundi-dialogs' ),
		value: 'closerequest',
	},
	{ label: __( 'Only a button', 'axismundi-dialogs' ), value: 'none' },
];

/**
 * A 48px picture of each presentation, drawn the way core's group placeholder
 * draws its layouts: the window, and the surface in it.
 *
 * @param {string} name The variation name.
 * @return {Element|undefined} The icon.
 */
function getPlaceholderIcon( name ) {
	const surfaces = {
		'dialog-basic':
			'M12 16a2 2 0 0 1 2-2h20a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2V16Z',
		'dialog-full-screen':
			'M0 10a2 2 0 0 1 2-2h44a2 2 0 0 1 2 2v28a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10Z',
		'sheet-bottom': 'M0 26a4 4 0 0 1 4-4h40a4 4 0 0 1 4 4v14H0V26Z',
		'sheet-side': 'M32 12a4 4 0 0 1 4-4h12v32H36a4 4 0 0 1-4-4V12Z',
	};
	if ( ! surfaces[ name ] ) {
		return undefined;
	}
	return (
		<SVG
			xmlns="http://www.w3.org/2000/svg"
			width="48"
			height="48"
			viewBox="0 0 48 48"
		>
			<Path d={ surfaces[ name ] } />
		</SVG>
	);
}

/**
 * Whether to show the presentation placeholder. core/group's hook, without the
 * layout type it also reads - this block has none.
 *
 * @param {Object}  props                  Hook arguments.
 * @param {Object}  [props.attributes]     The block's attributes.
 * @param {boolean} [props.hasInnerBlocks] Whether the block has inner blocks.
 * @return {[boolean, Function]} A state value and its setter.
 */
function useShouldShowPlaceHolder( {
	attributes = {},
	hasInnerBlocks = false,
} ) {
	const { style, backgroundColor, textColor, fontSize } = attributes;
	const [ showPlaceholder, setShowPlaceholder ] = useState(
		! hasInnerBlocks &&
			! backgroundColor &&
			! fontSize &&
			! textColor &&
			! style
	);

	useEffect( () => {
		if (
			!! hasInnerBlocks ||
			!! backgroundColor ||
			!! fontSize ||
			!! textColor ||
			!! style
		) {
			setShowPlaceholder( false );
		}
	}, [ backgroundColor, fontSize, textColor, style, hasInnerBlocks ] );

	return [ showPlaceholder, setShowPlaceholder ];
}

/**
 * Presentation choices for an empty dialog. core/group's placeholder.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.name     The block's name.
 * @param {Function} props.onSelect Sets the chosen variation's attributes.
 * @return {Element} The placeholder.
 */
function DialogPlaceholder( { name, onSelect } ) {
	const variations = useSelect(
		( select ) => select( blocksStore ).getBlockVariations( name, 'block' ),
		[ name ]
	);
	const blockProps = useBlockProps( {
		className: 'wp-block-axismundi-dialog__placeholder',
	} );

	useEffect( () => {
		if ( variations && variations.length === 1 ) {
			onSelect( variations[ 0 ] );
		}
	}, [ onSelect, variations ] );

	return (
		<div { ...blockProps }>
			<Placeholder
				instructions={ __(
					'Choose how this surface presents:',
					'axismundi-dialogs'
				) }
			>
				{ /* The `list` role is redundant, but Safari and VoiceOver do not announce the list without it - as in core/group. */ }
				{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles */ }
				<ul
					role="list"
					className="wp-block-group-placeholder__variations"
					aria-label={ __( 'Block variations', 'axismundi-dialogs' ) }
				>
					{ ( variations || [] ).map( ( variation ) => (
						<li key={ variation.name }>
							<Button
								__next40pxDefaultSize
								variant="tertiary"
								icon={ getPlaceholderIcon( variation.name ) }
								iconSize={ 48 }
								onClick={ () => onSelect( variation ) }
								className="wp-block-group-placeholder__variation-button"
								label={ `${ variation.title }: ${ variation.description }` }
							/>
						</li>
					) ) }
				</ul>
			</Placeholder>
		</div>
	);
}

/**
 * What render.php derives, so the editor can say it without storing it.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} The effective modality, render mode and dismissal.
 */
function effective( attributes ) {
	const {
		presentation = 'dialog-basic',
		modality,
		dismissal = 'any',
	} = attributes;
	const isSheet = presentation.startsWith( 'sheet-' );
	const effectiveModality =
		isSheet && modality === 'standard' ? 'standard' : 'modal';
	const renderMode =
		effectiveModality === 'standard' ? 'standard-sheet' : 'modal-dialog';
	return {
		isSheet,
		modality: effectiveModality,
		renderMode,
		dismissal:
			renderMode === 'standard-sheet' && dismissal === 'any'
				? 'closerequest'
				: dismissal,
	};
}

/**
 * The Block spacing value as an inline style. Core's layout support skips
 * writing the gap for this block (block.json __experimentalSkipSerialization),
 * so the block writes it itself, as blocks/dialog/render.php does on the page.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object|undefined} The style, or nothing when no gap is set.
 */
function blockGapStyle( attributes ) {
	const gap = attributes?.style?.spacing?.blockGap;
	const value = typeof gap === 'string' ? gap : gap?.top;
	if ( ! value ) {
		return undefined;
	}
	const preset = value.match( /^var:preset\|spacing\|(.+)$/ );
	return {
		gap: preset ? `var(--wp--preset--spacing--${ preset[ 1 ] })` : value,
	};
}

function Edit( { attributes, name, setAttributes, clientId } ) {
	const { hasInnerBlocks } = useSelect(
		( select ) => {
			const block = select( blockEditorStore ).getBlock( clientId );
			return { hasInnerBlocks: !! ( block && block.innerBlocks.length ) };
		},
		[ clientId ]
	);

	const {
		templateLock,
		allowedBlocks,
		presentation = 'dialog-basic',
		attachment,
		edge,
		ariaLabel,
		showDragHandle = true,
		pageShare,
	} = attributes;
	const derived = effective( attributes );

	const ref = useRef();
	const blockProps = useBlockProps( {
		ref,
		style: blockGapStyle( attributes ),
		'data-presentation': presentation,
		'data-render-mode': derived.renderMode,
		'data-modality': derived.isSheet ? derived.modality : undefined,
		'data-attachment':
			presentation === 'sheet-side' ? attachment || 'docked' : undefined,
		'data-edge': presentation === 'sheet-side' ? edge || 'end' : undefined,
	} );

	const [ showPlaceholder, setShowPlaceholder ] = useShouldShowPlaceHolder( {
		attributes,
		hasInnerBlocks,
	} );

	let renderAppender;
	if ( showPlaceholder ) {
		// The placeholder keeps the inner blocks area as a drop zone, so the
		// appender is not rendered in it - as in core/group.
		renderAppender = false;
	} else if ( ! hasInnerBlocks ) {
		renderAppender = InnerBlocks.ButtonBlockAppender;
	}

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		dropZoneElement: ref.current,
		templateLock,
		allowedBlocks,
		renderAppender,
	} );

	const { selectBlock } = useDispatch( blockEditorStore );
	const selectVariation = ( nextVariation ) => {
		setAttributes( nextVariation.attributes );
		selectBlock( clientId, -1 );
		setShowPlaceholder( false );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Surface', 'axismundi-dialogs' ) }>
					{ /* A setting, not a variation: M3 specifies modal and standard
					   sheets on one spec page, and they differ only in the scrim and
					   in how the sheet opens. A dialog is always modal. */ }
					{ derived.isSheet && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Modality', 'axismundi-dialogs' ) }
							value={ derived.modality }
							options={ [
								{
									label: __( 'Modal', 'axismundi-dialogs' ),
									value: 'modal',
								},
								{
									label: __(
										'Standard',
										'axismundi-dialogs'
									),
									value: 'standard',
								},
							] }
							help={
								derived.modality === 'standard'
									? __(
											'No scrim; the page behind it stays usable.',
											'axismundi-dialogs'
									  )
									: __(
											'Above a scrim; the page behind it cannot be used until it closes.',
											'axismundi-dialogs'
									  )
							}
							onChange={ ( value ) =>
								setAttributes( { modality: value } )
							}
						/>
					) }
					{ presentation === 'sheet-bottom' && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Drag handle', 'axismundi-dialogs' ) }
							checked={ showDragHandle }
							help={
								showDragHandle
									? __(
											'A button at the top. The sheet opens at no more than half the window, and the handle raises it to full height and back.',
											'axismundi-dialogs'
									  )
									: __(
											'No handle. The sheet opens at its full height.',
											'axismundi-dialogs'
									  )
							}
							onChange={ ( value ) =>
								setAttributes( { showDragHandle: value } )
							}
						/>
					) }
					{ presentation === 'sheet-side' && (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Edge', 'axismundi-dialogs' ) }
								value={ edge || 'end' }
								options={ [
									{
										label: __( 'End', 'axismundi-dialogs' ),
										value: 'end',
									},
									{
										label: __(
											'Start',
											'axismundi-dialogs'
										),
										value: 'start',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { edge: value } )
								}
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Attachment',
									'axismundi-dialogs'
								) }
								value={ attachment || 'docked' }
								options={ [
									{
										label: __(
											'Docked',
											'axismundi-dialogs'
										),
										value: 'docked',
									},
									{
										label: __(
											'Detached',
											'axismundi-dialogs'
										),
										value: 'detached',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { attachment: value } )
								}
							/>
							{ /* How a docked standard sheet shares the page
							   (blocks/dialog/view.js). A detached or modal
							   sheet floats over it, so the choice is not shown. */ }
							{ derived.modality === 'standard' &&
								( attachment || 'docked' ) === 'docked' && (
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Page',
											'axismundi-dialogs'
										) }
										value={ pageShare || 'resize' }
										options={ [
											{
												label: __(
													'Resize content',
													'axismundi-dialogs'
												),
												value: 'resize',
											},
											{
												label: __(
													'Move page',
													'axismundi-dialogs'
												),
												value: 'move',
											},
										] }
										help={
											pageShare === 'move'
												? __(
														'The whole page slides aside and keeps its width, as a mobile drawer does. Part of it leaves the window while the sheet is open.',
														'axismundi-dialogs'
												  )
												: __(
														'The content gets narrower to make room for the sheet. On a small window the sheet opens as a modal instead.',
														'axismundi-dialogs'
												  )
										}
										onChange={ ( value ) =>
											setAttributes( {
												pageShare:
													value === 'resize'
														? undefined
														: value,
											} )
										}
									/>
								) }
						</>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Closes with', 'axismundi-dialogs' ) }
						value={ derived.dismissal }
						// A standard sheet never light-dismisses (render.php), so the
						// scrim option is not offered there rather than offered and
						// quietly ignored.
						options={
							derived.renderMode === 'standard-sheet'
								? DISMISSALS.filter(
										( option ) => option.value !== 'any'
								  )
								: DISMISSALS
						}
						help={
							derived.renderMode === 'standard-sheet'
								? __(
										'A standard sheet leaves the page usable, so a click on the page never closes it.',
										'axismundi-dialogs'
								  )
								: undefined
						}
						onChange={ ( value ) =>
							setAttributes( { dismissal: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Label', 'axismundi-dialogs' ) }
						value={ ariaLabel || '' }
						help={ __(
							'The name assistive technology announces. Leave empty to use the first heading.',
							'axismundi-dialogs'
						) }
						onChange={ ( value ) =>
							setAttributes( { ariaLabel: value || undefined } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ showPlaceholder && (
				<div>
					{ innerBlocksProps.children }
					<DialogPlaceholder
						name={ name }
						onSelect={ selectVariation }
					/>
				</div>
			) }
			{ ! showPlaceholder && (
				<div { ...innerBlocksProps }>
					{ /* A picture of the handle: the button itself is render.php's, and it
					   would do nothing in the canvas. */ }
					{ presentation === 'sheet-bottom' && showDragHandle && (
						<header
							className="wp-block-axismundi-dialog__header"
							aria-hidden="true"
						>
							<span className="wp-block-axismundi-dialog__drag-handle" />
						</header>
					) }
					{ innerBlocksProps.children }
				</div>
			) }
		</>
	);
}

registerBlockType( metadata, {
	icon,
	variations: VARIATIONS,
	edit: Edit,
	// Dynamic: render.php writes the <dialog>. Only the inner blocks are saved.
	save: () => <InnerBlocks.Content />,
} );
