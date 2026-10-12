import {
	Children,
	cloneElement,
	createContext,
	isValidElement,
	useContext,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import warning from '@wordpress/warning';
import { Elevation } from '../material/elevation';

const EDGE = 8;
const ITEMS = '[role="menuitem"], [role="menuitemradio"], [role="menuitemcheckbox"]';
const MenuContext = createContext( null );
const MenuGroupContext = createContext( {
	isSelected: () => false,
	selectValue: () => {},
	selectionMode: 'none',
} );

function menuItems( menu ) {
	return [ ...( menu?.querySelectorAll( ITEMS ) ?? [] ) ];
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
	const triggerRef = useRef();
	const id = useId();
	const [ open, setOpen ] = useState( false );
	const triggerIsValid = isValidElement( trigger );

	const close = ( restoreFocus = true ) => {
		const host = menuRef.current;
		if ( host?.matches( ':popover-open' ) ) {
			host.hidePopover();
		}
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	};

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
		<MenuContext.Provider value={ { close, onSelect } }>
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
