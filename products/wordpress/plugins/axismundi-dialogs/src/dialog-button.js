/**
 * axismundi/dialog-button - core/button copy. See shared/button.js.
 */
import { button as icon } from '@wordpress/icons';
import { registerBlockType } from '@wordpress/blocks';
import metadata from '../blocks/dialog-button/block.json';
import { ButtonEdit, buttonSave, mergeButtons } from './shared/button';

registerBlockType( metadata, {
	icon,
	edit: ButtonEdit,
	save: buttonSave,
	merge: mergeButtons,
} );
