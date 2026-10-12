import {
	Children,
	cloneElement,
	createContext,
	isValidElement,
	useCallback,
	useContext,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import warning from '@wordpress/warning';
import { Elevation } from '../material/elevation';
import { Icon } from '../material/icon';

const EDGE = 8;
const SUBMENU_BLOCK_OFFSET = 2;
const ITEMS = '[role="menuitem"], [role="menuitemradio"], [role="menuitemcheckbox"]';
const MenuContext = createContext( null );
const MenuGroupContext = createContext( {
	isSelected: () => false,
	selectValue: () => {},
	selectionMode: 'none',
} );

function menuItems( menu ) {
	return [ ...( menu?.querySelectorAll( ITEMS ) ?? [] ) ].filter(
		( item ) => item.closest( '[role="menu"]' ) === menu
	);
}

function place( menu, trigger ) {
	if ( ! menu || ! trigger ) {
		return;
	}

	const target = trigger.getBoundingClientRect();
	const below = Math.max( 0, window.innerHeight - target.bottom - EDGE );
	const above = Math.max( 0, target.top - EDGE );
	const opensBelow = menu.scrollHeight <= below || below >= above;
	const availableBlockSize = opensBelow ? below : above;

	menu.style.maxBlockSize = `${ availableBlockSize }px`;
	const box = menu.getBoundingClientRect();
	const top = opensBelow ? target.bottom : target.top - box.height;
	const left = Math.min( target.left, window.innerWidth - box.width - EDGE );

	menu.style.inset = 'auto';
	menu.style.top = `${ Math.max( EDGE, top ) }px`;
	menu.style.left = `${ Math.max( EDGE, left ) }px`;
}

function placeSubmenu( menu, trigger ) {
	if ( ! menu || ! trigger ) {
		return;
	}

	const target = trigger.getBoundingClientRect();
	const right = Math.max( 0, window.innerWidth - target.right - EDGE );
	const left = Math.max( 0, target.left - EDGE );
	menu.style.maxBlockSize = `${ Math.max( 0, window.innerHeight - EDGE * 2 ) }px`;
	const box = menu.getBoundingClientRect();
	const opensRight = box.width <= right || right >= left;
	const top = Math.min(
		Math.max( EDGE, target.top - SUBMENU_BLOCK_OFFSET ),
		Math.max( EDGE, window.innerHeight - box.height - EDGE )
	);
	const inlineStart = opensRight ? target.right : target.left - box.width;

	menu.style.inset = 'auto';
	menu.style.top = `${ top }px`;
	menu.style.left = `${ Math.max( EDGE, inlineStart ) }px`;
}

/**
 * A named group within a menu. Checkability belongs here: each group is one
 * independent radio or checkbox set, rather than a property of the whole menu.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Menu rows.
 * @param {string} [props.label] Optional visible section label.
 * @param {'none'|'single'|'multi'} [props.selectionMode='none'] Selection semantics.
 * @return {import('@wordpress/element').ReactNode} Group markup.
 */
export function MenuGroup( {
	children,
	defaultValue,
	label,
	labelDivider = false,
	labelIcon,
	onValueChange,
	selectionMode = 'none',
	value,
} ) {
	const labelId = useId();
	const mode = [ 'none', 'single', 'multi' ].includes( selectionMode ) ? selectionMode : 'none';
	const [ uncontrolledValue, setUncontrolledValue ] = useState( () => {
		if ( undefined !== defaultValue ) {
			return defaultValue;
		}
		const selected = Children.toArray( children )
			.filter( ( child ) => isValidElement( child ) && child.props.selected )
			.map( ( child ) => child.props.value ?? child.props.id ?? child.props.label );
		return 'multi' === mode ? selected : selected[ 0 ];
	} );
	const controlled = undefined !== value;
	const selectedValue = controlled ? value : uncontrolledValue;
	const isSelected = ( itemValue ) => 'multi' === mode
		? ( selectedValue ?? [] ).includes( itemValue )
		: selectedValue === itemValue;
	const selectValue = ( itemValue ) => {
		if ( 'none' === mode ) {
			return;
		}
		const next = 'multi' === mode
			? ( isSelected( itemValue )
				? ( selectedValue ?? [] ).filter( ( current ) => current !== itemValue )
				: [ ...( selectedValue ?? [] ), itemValue ] )
			: itemValue;
		if ( ! controlled ) {
			setUncontrolledValue( next );
		}
		onValueChange?.( next );
	};

	return (
		<MenuGroupContext.Provider value={ { isSelected, selectValue, selectionMode: mode } }>
			<div
				aria-labelledby={ label ? labelId : undefined }
				className="ax-menu__group"
				role="group"
			>
				{ label && (
					<span className="ax-menu__group-label" id={ labelId }>
						<span className="ax-menu__group-label-content">
							{ labelIcon && <span aria-hidden="true" className="ax-menu__group-label-icon">{ labelIcon }</span> }
							<span>{ label }</span>
						</span>
						{ labelDivider && <span className="ax-menu__group-label-divider" role="separator" /> }
					</span>
				) }
				{ children }
			</div>
		</MenuGroupContext.Provider>
	);
}

/**
 * One menu action. The row stays a button so disabled menu items remain in the
 * focus order while click handling can still reject them.
 *
 * @param {Object} props Component props.
 * @return {import('@wordpress/element').ReactNode} Menu item.
 */
export function MenuItem( {
	disabled = false,
	id,
	label,
	leading,
	supporting,
	selected = false,
	trailing,
	value,
} ) {
	const menu = useContext( MenuContext );
	const { isSelected, selectValue, selectionMode } = useContext( MenuGroupContext );
	const checkable = 'none' !== selectionMode;
	const role = ! checkable ? 'menuitem' : 'multi' === selectionMode ? 'menuitemcheckbox' : 'menuitemradio';
	const itemValue = value ?? id ?? label;
	const isCurrent = checkable ? isSelected( itemValue ) : selected;

	const select = () => {
		if ( disabled ) {
			return;
		}
		selectValue( itemValue );
		menu?.onSelect?.( { id, label, selected: isCurrent, value: itemValue } );
		if ( 'multi' !== selectionMode ) {
			menu?.close();
		}
	};

	return (
		<button
			aria-checked={ checkable ? String( Boolean( isCurrent ) ) : undefined }
			aria-disabled={ disabled || undefined }
			className="ax-menu__item"
			data-selected={ isCurrent ? '' : undefined }
			onClick={ select }
			role={ role }
			type="button"
		>
			<span className="ax-menu__item-container">
				{ leading && <span aria-hidden="true" className="ax-menu__leading">{ leading }</span> }
				<span className="ax-menu__content">
					<span className="ax-menu__label">{ label }</span>
					{ supporting && <span className="ax-menu__supporting">{ supporting }</span> }
				</span>
				{ trailing && <span aria-hidden="true" className="ax-menu__trailing">{ trailing }</span> }
			</span>
		</button>
	);
}

/**
 * A semantic divider between menu groups.
 *
 * @return {import('@wordpress/element').ReactNode} Separator markup.
 */
export function MenuSeparator() {
	return <span className="ax-menu__separator" role="separator" />;
}

/**
 * A menu row which owns and opens its nested menu. Its arrow is structural,
 * so an ordinary item can never accidentally imply a submenu.
 *
 * @param {Object} props Component props.
 * @return {import('@wordpress/element').ReactNode} Submenu markup.
 */
export function MenuSubmenu( { children, disabled = false, label, leading, supporting } ) {
	const parentMenu = useContext( MenuContext );
	const id = useId();
	const menuRef = useRef();
	const triggerRef = useRef();
	const [ open, setOpen ] = useState( false );

	const close = useCallback( ( restoreFocus = true ) => {
		const host = menuRef.current;
		if ( host?.matches( ':popover-open' ) ) {
			host.hidePopover();
		}
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	}, [] );

	useEffect(
		() => parentMenu?.registerSubmenu?.( menuRef.current, close ),
		[ parentMenu, close ]
	);

	const focusItem = ( index ) => menuItems( menuRef.current )[ index ]?.focus();

	const show = () => {
		const host = menuRef.current;
		if ( disabled || ! host || ! triggerRef.current ) {
			return;
		}
		if ( host.matches( ':popover-open' ) ) {
			host.hidePopover();
		}
		host.showPopover();
		placeSubmenu( host, triggerRef.current );
		setOpen( true );
		focusItem( 0 );
	};

	const onTriggerKeyDown = ( event ) => {
		if ( 'ArrowRight' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			show();
		}
	};

	const onMenuKeyDown = ( event ) => {
		const controls = menuItems( menuRef.current );
		const from = controls.indexOf( document.activeElement );
		if ( 'Escape' === event.key || 'ArrowLeft' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			close();
			return;
		}
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			focusItem( 'ArrowDown' === event.key ? Math.min( from + 1, controls.length - 1 ) : Math.max( from - 1, 0 ) );
		}
	};

	return (
		<>
			<button
				aria-controls={ id }
				aria-disabled={ disabled || undefined }
				aria-expanded={ open }
				aria-haspopup="menu"
				className="ax-menu__item ax-menu__submenu-trigger"
				data-submenu-open={ open || undefined }
				onClick={ () => open ? close( false ) : show() }
				onKeyDown={ onTriggerKeyDown }
				ref={ triggerRef }
				role="menuitem"
				type="button"
			>
				<span className="ax-menu__item-container">
					{ leading && <span aria-hidden="true" className="ax-menu__leading">{ leading }</span> }
					<span className="ax-menu__content">
						<span className="ax-menu__label">{ label }</span>
						{ supporting && <span className="ax-menu__supporting">{ supporting }</span> }
					</span>
					<span aria-hidden="true" className="ax-menu__trailing"><Icon name="arrow_right" /></span>
				</span>
			</button>
			<div
				aria-label={ label }
				className="ax-menu ax-menu--submenu"
				id={ id }
				onKeyDown={ onMenuKeyDown }
				popover="manual"
				ref={ menuRef }
				role="menu"
			>
				<Elevation level={ 2 } />
				<div className="ax-menu__list">{ children }</div>
			</div>
		</>
	);
}

/**
 * A vertical M3 menu. The popover host owns anchoring and light dismissal;
 * groups own selection semantics and items own their single action.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Menu groups and separators.
 * @param {import('@wordpress/element').ReactElement} props.trigger The control that opens the menu.
 * @param {string} props.label Accessible name for the action menu.
 * @param {Function} [props.onSelect] Receives a selected item.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Menu and its trigger.
 */
export function Menu( { children, trigger, label, onSelect, className } ) {
	const menuRef = useRef();
	const submenuHosts = useRef( new Map() );
	const triggerRef = useRef();
	const id = useId();
	const [ open, setOpen ] = useState( false );
	const triggerIsValid = isValidElement( trigger );

	const close = ( restoreFocus = true ) => {
		for ( const [ submenu, closeSubmenu ] of submenuHosts.current ) {
			if ( closeSubmenu ) {
				closeSubmenu( false );
			} else if ( submenu?.matches( ':popover-open' ) ) {
				submenu.hidePopover();
			}
		}
		const host = menuRef.current;
		if ( host?.matches( ':popover-open' ) ) {
			host.hidePopover();
		}
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	};

	const registerSubmenu = useCallback( ( host, closeSubmenu ) => {
		if ( ! host ) {
			return undefined;
		}
		submenuHosts.current.set( host, closeSubmenu );
		return () => submenuHosts.current.delete( host );
	}, [] );

	const focusItem = ( index ) => menuItems( menuRef.current )[ index ]?.focus();

	const show = () => {
		const host = menuRef.current;
		if ( ! host || ! triggerRef.current ) {
			return;
		}
		if ( host.matches( ':popover-open' ) ) {
			host.hidePopover();
		}
		host.showPopover();
		place( host, triggerRef.current );
		setOpen( true );
		focusItem( 0 );
	};

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const dismiss = ( event ) => {
			const inMenuTree = menuRef.current?.contains( event.target )
				|| [ ...submenuHosts.current.keys() ].some( ( host ) => host?.contains( event.target ) );
			if ( ! inMenuTree && ! triggerRef.current?.contains( event.target ) ) {
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

	if ( ! triggerIsValid ) {
		warning( 'Menu: `trigger` must be one interactive React element.' );
		return null;
	}
	if ( ! label ) {
		warning( 'Menu: `label` is required so assistive technology can name the menu.' );
	}

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
			focusItem( menuItems( menuRef.current ).length - 1 );
		}
	};

	const onMenuKeyDown = ( event ) => {
		const controls = menuItems( menuRef.current );
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

	return (
		<MenuContext.Provider value={ { close, onSelect, registerSubmenu } }>
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
				<Elevation level={ 2 } />
				<div className="ax-menu__list">{ children }</div>
			</div>
		</MenuContext.Provider>
	);
}

Menu.Group = MenuGroup;
Menu.Item = MenuItem;
Menu.Separator = MenuSeparator;
Menu.Submenu = MenuSubmenu;
