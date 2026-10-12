import {
	cloneElement,
	isValidElement,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import warning from '@wordpress/warning';
import { Elevation } from '../material/elevation';

const EDGE = 8;
/*
 * A selectable row's role is not `menuitem`, so every lookup that walks the
 * rows matches the family rather than the plain role. Querying `menuitem`
 * alone silently drops checked rows out of keyboard movement.
 */
const ITEMS = '[role="menuitem"], [role="menuitemradio"], [role="menuitemcheckbox"]';

function place( menu, trigger ) {
	if ( ! menu || ! trigger ) {
		return;
	}

	const target = trigger.getBoundingClientRect();
	const box = menu.getBoundingClientRect();
	let top = target.bottom;
	let left = target.left;

	if ( top + box.height + EDGE > window.innerHeight ) {
		top = target.top - box.height;
	}
	if ( left + box.width + EDGE > window.innerWidth ) {
		left = window.innerWidth - box.width - EDGE;
	}

	menu.style.inset = 'auto';
	menu.style.top = `${ Math.max( EDGE, top ) }px`;
	menu.style.left = `${ Math.max( EDGE, left ) }px`;
}

/**
 * A vertical M3 menu. The popover host owns anchoring and light dismissal;
 * this component owns menu-item semantics and keyboard movement.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactElement} props.trigger The control that opens the menu.
 * @param {Array} props.items Menu items, or `{ divider: true }` separators.
 * @param {string} props.label Accessible name for the action menu.
 * @param {Function} [props.onSelect] Receives a selected item.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Menu and its trigger.
 */
export function Menu( { trigger, items = [], label, onSelect, className, selectionMode = 'single' } ) {
	const menuRef = useRef();
	const triggerRef = useRef();
	const id = useId();
	const [ open, setOpen ] = useState( false );

	if ( ! isValidElement( trigger ) ) {
		warning( 'Menu: `trigger` must be one interactive React element.' );
		return null;
	}
	if ( ! label ) {
		warning( 'Menu: `label` is required so assistive technology can name the menu.' );
	}

	const close = ( restoreFocus = true ) => {
		const menu = menuRef.current;
		if ( menu?.matches( ':popover-open' ) ) {
			menu.hidePopover();
		}
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	};

	const focusItem = ( index ) => {
		const controls = [ ...( menuRef.current?.querySelectorAll( ITEMS ) ?? [] ) ];
		controls[ index ]?.focus();
	};

	const show = () => {
		const menu = menuRef.current;
		if ( ! menu || ! triggerRef.current ) {
			return;
		}
		if ( menu.matches( ':popover-open' ) ) {
			menu.hidePopover();
		}
		menu.showPopover();
		place( menu, triggerRef.current );
		setOpen( true );
		focusItem( 0 );
	};

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const dismiss = ( event ) => {
			if ( ! menuRef.current?.contains( event.target ) && ! triggerRef.current?.contains( event.target ) ) {
				close( false );
			}
		};
		const reposition = () => place( menuRef.current, triggerRef.current );
		document.addEventListener( 'pointerdown', dismiss, true );
		window.addEventListener( 'resize', reposition );
		window.addEventListener( 'scroll', reposition, true );
		return () => {
			document.removeEventListener( 'pointerdown', dismiss, true );
			window.removeEventListener( 'resize', reposition );
			window.removeEventListener( 'scroll', reposition, true );
		};
	}, [ open ] );

	const onTriggerClick = ( event ) => {
		trigger.props.onClick?.( event );
		if ( event.defaultPrevented ) {
			return;
		}
		open ? close( false ) : show();
	};

	const onTriggerKeyDown = ( event ) => {
		trigger.props.onKeyDown?.( event );
		if ( event.defaultPrevented || ! [ 'ArrowDown', 'ArrowUp' ].includes( event.key ) ) {
			return;
		}
		event.preventDefault();
		show();
		if ( 'ArrowUp' === event.key ) {
			const controls = [ ...( menuRef.current?.querySelectorAll( ITEMS ) ?? [] ) ];
			focusItem( controls.length - 1 );
		}
	};

	const onMenuKeyDown = ( event ) => {
		const controls = [ ...( menuRef.current?.querySelectorAll( ITEMS ) ?? [] ) ];
		const from = controls.indexOf( document.activeElement );
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			close();
			return;
		}
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			const next = 'ArrowDown' === event.key ? Math.min( from + 1, controls.length - 1 ) : Math.max( from - 1, 0 );
			focusItem( next );
			return;
		}
		if ( 1 === event.key.length ) {
			const start = ( from + 1 ) % controls.length;
			const next = [ ...controls.slice( start ), ...controls.slice( 0, start ) ].find(
				( item ) => item.textContent.trim().toLocaleLowerCase().startsWith( event.key.toLocaleLowerCase() )
			);
			next?.focus();
		}
	};

	/*
	 * Checkability is a property of the menu, not of the row that happens to be
	 * checked. A radio group whose unselected rows are plain menu items is not
	 * a group, and the kit's own selectable sample declares `selected` on one
	 * row only.
	 */
	const checkable = items.some( ( item ) => undefined !== item.selected );

	const select = ( item ) => {
		if ( item.disabled ) {
			return;
		}
		onSelect?.( item );
		close();
	};

	return (
		<>
			{ cloneElement( trigger, {
				'aria-controls': id,
				'aria-expanded': open,
				'className': [ trigger.props.className, 'ax-menu__trigger' ].filter( Boolean ).join( ' ' ),
				'data-menu-open': open || undefined,
				'onClick': onTriggerClick,
				'onKeyDown': onTriggerKeyDown,
				ref: triggerRef,
			} ) }
			<div
				aria-label={ label || undefined }
				className={ [ 'ax-menu', className ].filter( Boolean ).join( ' ' ) }
				id={ id }
				onKeyDown={ onMenuKeyDown }
				popover="manual"
				ref={ menuRef }
				role="menu"
			>
				{ /*
				  * Elevation is an absolutely positioned layer, so it has to sit
				  * outside the box that scrolls or it scrolls away with the rows.
				  * The list is that box, and it is the kit's own level: its tree
				  * is Menu > List content > Menu-item.
				  */ }
				<Elevation level={ 2 } />
				<div className="ax-menu__list">
				{ items.map( ( item, index ) => {
					if ( item.divider ) {
						return <span aria-hidden="true" className="ax-menu__divider" key={ `divider-${ index }` } />;
					}
					/*
					 * A row that can carry a selection is a checkable row, and the
					 * state goes on `aria-checked` (`menu.yml` `selection`). Which
					 * checkable role depends on how many rows may be checked at
					 * once, which is what the two published selection modes mean.
					 */
					const role = ! checkable
						? 'menuitem'
						: 'multi' === selectionMode ? 'menuitemcheckbox' : 'menuitemradio';
					return (
						<button
							aria-checked={ checkable ? ( item.selected ? 'true' : 'false' ) : undefined }
							aria-disabled={ item.disabled || undefined }
							className="ax-menu__item"
							data-selected={ item.selected ? '' : undefined }
							key={ item.id ?? item.label }
							onClick={ () => select( item ) }
							role={ role }
							type="button"
						>
							<span className="ax-menu__item-container">
								{ item.leading && <span aria-hidden="true" className="ax-menu__leading">{ item.leading }</span> }
								<span className="ax-menu__content">
									<span className="ax-menu__label">{ item.label }</span>
									{ item.supporting && <span className="ax-menu__supporting">{ item.supporting }</span> }
								</span>
								{ item.trailing && <span aria-hidden="true" className="ax-menu__trailing">{ item.trailing }</span> }
							</span>
						</button>
					);
				} ) }
				</div>
			</div>
		</>
	);
}
