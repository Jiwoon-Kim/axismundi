import { Pane } from '../panes/pane';
import warning from '@wordpress/warning';

const APP_BAR_SCOPES = [ 'pane', 'window' ];

/**
 * Places application chrome around a content pane. The rail is a sibling of
 * that pane; the app bar, primary pane, and optional supporting pane live
 * inside it. The scaffold owns window-level geometry only.
 *
 * The `sheet` slot is where a standard side sheet lives. It is a column of the
 * body rather than padding injected on the page root, which is what the block
 * runtime has to do for want of a scaffold: the body already sits below the app
 * bar and above the navigation bar, so a sheet placed here needs no offsets of
 * its own and nothing has to measure its width. Section 4 of
 * `docs/DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`.
 *
 * It is a node, like `appBar` and `supporting`, so the sheet's open state stays
 * with whatever renders it. A sheet that is closed, or open as a modal, takes
 * no space: the column is `auto`, a closed dialog is `display: none`, and a
 * modal one is in the top layer.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Primary content.
 * @param {import('@wordpress/element').ReactNode} [props.appBar] Top application bar.
 * @param {'pane'|'window'} [props.appBarScope='pane'] Whether the app bar spans
 * the content pane or the full Scaffold window.
 * @param {import('@wordpress/element').ReactNode} [props.navigationBar] Compact navigation.
 * @param {import('@wordpress/element').ReactNode} [props.navigationRail] Medium-and-up navigation.
 * @param {import('@wordpress/element').ReactNode} [props.companion] Trailing rail content.
 * @param {string} [props.companionLabel] Accessible name for the companion rail landmark.
 * @param {import('@wordpress/element').ReactNode} [props.supporting] Expanded supporting content.
 * @param {import('@wordpress/element').ReactNode} [props.sheet] Standard side sheet column.
 * @param {string} [props.className] Product-level wrapper class.
 * @return {import('@wordpress/element').ReactNode} Application scaffold.
 */
export function Scaffold( {
	children,
	appBar,
	navigationBar,
	navigationRail,
	companion,
	companionLabel,
	supporting,
	sheet,
	appBarScope = 'pane',
	className,
} ) {
	const barScope = APP_BAR_SCOPES.includes( appBarScope ) ? appBarScope : 'pane';
	if ( barScope !== appBarScope ) {
		warning( `Scaffold: unknown appBarScope "${ appBarScope }"; using pane.` );
	}
	if ( companion && ! companionLabel ) {
		warning( 'Scaffold: companionLabel is required when companion is present.' );
	}

	return (
		<div
			className={ [ 'ax-scaffold', className ].filter( Boolean ).join( ' ' ) }
			data-has-app-bar={ appBar ? '' : undefined }
			data-app-bar-scope={ appBar ? barScope : undefined }
			data-has-bar={ navigationBar ? '' : undefined }
			data-has-rail={ navigationRail ? '' : undefined }
			data-has-companion={ companion ? '' : undefined }
			data-has-supporting={ supporting ? '' : undefined }
			data-has-sheet={ sheet ? '' : undefined }
		>
			{ navigationBar ? (
				<div className="ax-scaffold__bar">{ navigationBar }</div>
			) : null }
			{ navigationRail ? (
				<div className="ax-scaffold__rail">{ navigationRail }</div>
			) : null }
			{ 'window' === barScope && appBar ? (
				<div className="ax-scaffold__app-bar">{ appBar }</div>
			) : null }
			{ companion ? (
				<aside className="ax-scaffold__companion" aria-label={ companionLabel || undefined }>{ companion }</aside>
			) : null }
			<Pane className="ax-scaffold__content">
				{ 'pane' === barScope && appBar ? (
					<div className="ax-scaffold__app-bar">{ appBar }</div>
				) : null }
				<div className="ax-scaffold__body">
					<Pane as="main" className="ax-scaffold__main">
						{ children }
					</Pane>
					{ supporting ? (
						<Pane as="aside" className="ax-scaffold__supporting">
							{ supporting }
						</Pane>
					) : null }
					{ sheet }
				</div>
			</Pane>
		</div>
	);
}
