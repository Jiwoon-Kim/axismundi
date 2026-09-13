/**
 * axismundi/dialog - the <dialog> host at the root of a Dialog Surface part.
 *
 * Edited in the Site Editor, inside a template part in the dialog-surface area
 * (includes/surface.php). It owns how the surface presents and dismisses; the
 * blocks inside own its anatomy, so no template is forced here - the area's
 * starter patterns bring one.
 *
 * The canvas shows the surface as a div: an open <dialog> in the editor would
 * sit in the top layer above the editor itself. The markup it stands for is
 * blocks/dialog/render.php.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { layout as icon } from '@wordpress/icons';
import metadata from '../blocks/dialog/block.json';

const PRESENTATIONS = [
	{ label: __( 'Basic dialog', 'axismundi-dialogs' ), value: 'dialog-basic' },
	{ label: __( 'Full-screen dialog', 'axismundi-dialogs' ), value: 'dialog-full-screen' },
	{ label: __( 'Bottom sheet', 'axismundi-dialogs' ), value: 'sheet-bottom' },
	{ label: __( 'Side sheet', 'axismundi-dialogs' ), value: 'sheet-side' },
];

const DISMISSALS = [
	{ label: __( 'Scrim, Escape or a button', 'axismundi-dialogs' ), value: 'any' },
	{ label: __( 'Escape or a button', 'axismundi-dialogs' ), value: 'closerequest' },
	{ label: __( 'Only a button', 'axismundi-dialogs' ), value: 'none' },
];

/**
 * What render.php derives, so the editor can say it without storing it.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} The effective modality, render mode and dismissal.
 */
function effective( attributes ) {
	const { presentation = 'dialog-basic', modality, dismissal = 'any' } = attributes;
	const isSheet = presentation.startsWith( 'sheet-' );
	const effectiveModality = isSheet && modality === 'standard' ? 'standard' : 'modal';
	const renderMode = effectiveModality === 'standard' ? 'standard-sheet' : 'modal-dialog';
	return {
		isSheet,
		modality: effectiveModality,
		renderMode,
		dismissal: renderMode === 'standard-sheet' && dismissal === 'any' ? 'closerequest' : dismissal,
	};
}

function Edit( { attributes, setAttributes } ) {
	const { presentation = 'dialog-basic', attachment, edge, label } = attributes;
	const derived = effective( attributes );

	const blockProps = useBlockProps( {
		'data-presentation': presentation,
		'data-render-mode': derived.renderMode,
		'data-modality': derived.isSheet ? derived.modality : undefined,
		'data-attachment': presentation === 'sheet-side' ? attachment || 'docked' : undefined,
		'data-edge': presentation === 'sheet-side' ? edge || 'end' : undefined,
	} );
	const innerBlocksProps = useInnerBlocksProps( {
		className: 'wp-block-axismundi-dialog__container',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Surface', 'axismundi-dialogs' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Presentation', 'axismundi-dialogs' ) }
						value={ presentation }
						options={ PRESENTATIONS }
						onChange={ ( value ) => setAttributes( { presentation: value } ) }
					/>
					{ derived.isSheet && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Modality', 'axismundi-dialogs' ) }
							value={ derived.modality }
							options={ [
								{ label: __( 'Modal', 'axismundi-dialogs' ), value: 'modal' },
								{ label: __( 'Standard', 'axismundi-dialogs' ), value: 'standard' },
							] }
							help={
								derived.modality === 'standard'
									? __( 'Shares the page and pushes its content aside. No scrim.', 'axismundi-dialogs' )
									: __( 'Covers the page with a scrim until it is closed.', 'axismundi-dialogs' )
							}
							onChange={ ( value ) => setAttributes( { modality: value } ) }
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
									{ label: __( 'End', 'axismundi-dialogs' ), value: 'end' },
									{ label: __( 'Start', 'axismundi-dialogs' ), value: 'start' },
								] }
								onChange={ ( value ) => setAttributes( { edge: value } ) }
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Attachment', 'axismundi-dialogs' ) }
								value={ attachment || 'docked' }
								options={ [
									{ label: __( 'Docked', 'axismundi-dialogs' ), value: 'docked' },
									{ label: __( 'Detached', 'axismundi-dialogs' ), value: 'detached' },
								] }
								onChange={ ( value ) => setAttributes( { attachment: value } ) }
							/>
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
								? DISMISSALS.filter( ( option ) => option.value !== 'any' )
								: DISMISSALS
						}
						help={
							derived.renderMode === 'standard-sheet'
								? __( 'A standard sheet shares the page, so a click on the page never closes it.', 'axismundi-dialogs' )
								: undefined
						}
						onChange={ ( value ) => setAttributes( { dismissal: value } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Label', 'axismundi-dialogs' ) }
						value={ label || '' }
						help={ __( 'Used only when the surface has no heading with an HTML anchor.', 'axismundi-dialogs' ) }
						onChange={ ( value ) => setAttributes( { label: value || undefined } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}

registerBlockType( metadata, {
	icon,
	edit: Edit,
	// Dynamic: render.php writes the <dialog>. Only the inner blocks are saved.
	save: () => <InnerBlocks.Content />,
} );
