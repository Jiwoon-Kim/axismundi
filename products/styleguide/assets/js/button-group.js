/**
 * Button group selection and documentation demos.
 *
 * Standard and Connected deliberately have separate handlers. They have
 * different authoring trees: Standard owns independent Button InnerBlocks,
 * while Connected owns an ordered collection of Segments.
 */
( function () {
	var SEGMENT = ".wp-block-axismundi-button-group__item[aria-pressed]";

	function visibleSegments( group ) {
		return Array.prototype.slice.call( group.querySelectorAll( SEGMENT ) ).filter(
			function ( segment ) {
				return ! segment.hidden;
			}
		);
	}

	// `:last-of-type` follows DOM order, not the Connected model's visible
	// segment list. Keep the visual endpoints explicit whenever an optional
	// third, fourth, or fifth segment changes structural visibility.
	function syncConnectedEdges( group ) {
		var segments = visibleSegments( group );
		group.dataset.connectedEdges = "";
		group.querySelectorAll( SEGMENT ).forEach( function ( segment ) {
			delete segment.dataset.connectedEdge;
		} );
		if ( segments.length ) {
			segments[ 0 ].dataset.connectedEdge = "first";
			segments[ segments.length - 1 ].dataset.connectedEdge = "last";
		}
	}

	function normaliseConnected( group ) {
		var segments = visibleSegments( group );
		var selected;
		if ( ! segments.length || group.dataset.selection === "multiple" ) {
			return;
		}
		selected = segments.filter( function ( segment ) {
			return segment.getAttribute( "aria-pressed" ) === "true";
		} );
		if ( selected.length > 1 ) {
			selected.slice( 1 ).forEach( function ( segment ) {
				segment.setAttribute( "aria-pressed", "false" );
			} );
			selected = selected.slice( 0, 1 );
		}
		if ( group.dataset.required === "true" && ! selected.length ) {
			segments[ 0 ].setAttribute( "aria-pressed", "true" );
		}
	}

	function bindSelection( group ) {
		group.addEventListener( "click", function ( event ) {
			var target = event.target.closest( SEGMENT );
			var pressed;
			if ( ! target || ! group.contains( target ) || target.hidden ) {
				return;
			}
			pressed = target.getAttribute( "aria-pressed" ) === "true";
			if ( group.dataset.selection === "multiple" ) {
				target.setAttribute( "aria-pressed", pressed ? "false" : "true" );
				return;
			}
			if ( pressed && group.dataset.required === "true" ) {
				return;
			}
			visibleSegments( group ).forEach( function ( segment ) {
				segment.setAttribute( "aria-pressed", "false" );
			} );
			if ( ! pressed ) {
				target.setAttribute( "aria-pressed", "true" );
			}
		} );
	}

	function setShape( elements, value ) {
		elements.forEach( function ( element ) {
			if ( value === "round" ) {
				delete element.dataset.shape;
			} else {
				element.dataset.shape = value;
			}
		} );
	}

	// The panel is the block a reader would save, so it has to show what the
	// children actually became - including which block each one is. A standard
	// group holds Buttons and Icon buttons side by side, and those are two
	// block names, not one block with a flag.
	function standardMarkup( host, group ) {
		var output = host.querySelector( "[data-group-markup]" );
		var nl = String.fromCharCode( 10 );
		var children;
		if ( ! output ) {
			return;
		}
		children = Array.prototype.slice.call(
			group.querySelectorAll( "[data-group-child]:not([hidden])" )
		);
		output.textContent = '<div class="wp-block-buttons"' +
			( group.dataset.color ? ' data-color="' + group.dataset.color + '"' : "" ) +
			">" + nl +
			children.map( childMarkup ).join( nl ) +
			nl + "</div>";
	}

	function styleOf( element ) {
		return Array.prototype.slice.call( element.classList ).filter( function ( name ) {
			return name.indexOf( "is-style-" ) === 0;
		} )[ 0 ] || "";
	}

	function childMarkup( child ) {
		var attributes = [];
		var style = styleOf( child );
		var isIcon = childKind( child ) === "icon";
		var name = childName( child );
		if ( style ) {
			attributes.push( 'className:"' + style + '"' );
		}
		if ( child.dataset.size ) {
			attributes.push( 'size:"' + child.dataset.size + '"' );
		}
		// Width is the icon button's own axis and the only component here that
		// has one, so it never appears on a Button.
		if ( isIcon && child.dataset.width ) {
			attributes.push( 'width:"' + child.dataset.width + '"' );
		}
		if ( child.dataset.shape ) {
			attributes.push( 'shape:"' + child.dataset.shape + '"' );
		}
		// Figma keeps Show icon and Icon as two properties, and a block would
		// store them the same way: turning the icon off does not forget which
		// one it was. The DOM does drop the element, because it is aria-hidden
		// decoration and an invisible empty span is worth nothing - unlike the
		// name, which is what a screen reader announces and has to stay in the
		// tree. That asymmetry is the difference between content and ornament.
		// An icon button has no such switch: the icon is its entire anatomy.
		if ( ! isIcon && ! child.querySelector( ".wp-block-button__icon" ) ) {
			attributes.push( "showIcon:false" );
		}
		if ( child.dataset.icon ) {
			attributes.push( 'icon:"' + child.dataset.icon + '"' );
		}
		if ( childControl( child ).hasAttribute( "aria-pressed" ) ) {
			attributes.push( "selected:" +
				( childControl( child ).getAttribute( "aria-pressed" ) === "true" ) );
		}
		if ( childControl( child ).disabled ) {
			attributes.push( "disabled:true" );
		}
		return "  <!-- wp:" + ( isIcon ? "axismundi/icon-button" : "button" ) +
			( attributes.length ? " {" + attributes.join( "," ) + "}" : "" ) +
			" -->" + ( name ? "  " + name.textContent : "" );
	}

	// --- Two kinds of child ---------------------------------------------
	//
	// A standard group is a container for independent buttons, and M3 names
	// both kinds when it rules one of them out: "Avoid using standard icon
	// buttons or text buttons, as they have no container treatment." Only
	// three of the four icon-button styles are excluded from that sentence,
	// which is the guidelines saying icon buttons belong in a group.
	//
	// So icon-only here is a different block, not a Button with its label
	// pushed out of sight. This page states the rule further down: a control
	// with an icon and no label is not a Button, it is an Icon button. The
	// demo used to model it the other way, and it contradicted its own page.

	function childKind( child ) {
		return child.classList.contains( "wp-block-axismundi-icon-button" ) ? "icon" : "button";
	}

	// The accessible name, wherever the kind keeps it. A Button's is a visible
	// label that may retreat; an Icon button's is only ever out of sight.
	function childName( child ) {
		return child.querySelector( "[data-group-label], .screen-reader-text" );
	}

	// The element carrying `disabled`. An icon button is its own control; a
	// Button keeps one inside its wrapper.
	function childControl( child ) {
		return childKind( child ) === "icon"
			? child
			: child.querySelector( ".wp-block-button__link" );
	}

	// The name lives on the child, not on the element that renders it, so
	// removing the icon does not throw the choice away with it. Toggling Show
	// icon off and on used to hand every button the same default back.
	//
	// The class differs by kind, and not decoratively: a Button's icon is a slot
	// in the label flow and carries the block's own class, while an Icon
	// button's icon is the whole content and is sized by the component itself.
	function makeIcon( child, kind ) {
		var icon = document.createElement( "span" );
		icon.className = kind === "icon"
			? "material-symbols-outlined notranslate"
			: "wp-block-button__icon material-symbols-outlined notranslate";
		icon.setAttribute( "translate", "no" );
		icon.setAttribute( "aria-hidden", "true" );
		icon.textContent = child.dataset.icon || "add";
		return icon;
	}

	function rememberIcon( child ) {
		var icon = child.querySelector( ".material-symbols-outlined" );
		if ( icon && ! child.dataset.icon ) {
			child.dataset.icon = icon.textContent.trim();
		}
	}

	function makeChild( kind, state ) {
		var name = document.createElement( "span" );
		var child;
		var link;
		if ( kind === "icon" ) {
			child = document.createElement( "button" );
			child.type = "button";
			child.className = "wp-block-axismundi-icon-button";
			child.dataset.groupChild = "";
			child.dataset.icon = state.icon;
			name.className = "screen-reader-text";
			name.textContent = state.name;
			child.append( makeIcon( child, "icon" ), name );
			return child;
		}
		child = document.createElement( "div" );
		link = document.createElement( "button" );
		child.className = "wp-block-button";
		child.dataset.groupChild = "";
		child.dataset.icon = state.icon;
		link.type = "button";
		link.className = "wp-block-button__link wp-element-button";
		// The name element always exists and is never `hidden`: that would drop
		// it from the accessibility tree and force a second, separate aria-label,
		// which is two sources for one name.
		name.dataset.groupLabel = "";
		name.textContent = state.name;
		link.append( makeIcon( child ), name );
		child.append( link );
		return child;
	}

	// Changing kind is a block transform, so it carries across what both blocks
	// store and drops what only one of them does. Elevated and Text have no
	// icon-button counterpart, Standard has no Button one, and Width belongs to
	// the icon button alone. Losing a property the target cannot express is the
	// transform being honest rather than inventing a value.
	var SHARED_STYLES = { tonal: true, outline: true };

	function convertChild( child, kind ) {
		var was = childKind( child );
		var name = childName( child );
		var style = styleOf( child ).replace( "is-style-", "" );
		var disabled = childControl( child ).disabled;
		var pressed = childControl( child ).getAttribute( "aria-pressed" );
		var replacement;
		var icon;
		if ( was === kind ) {
			return child;
		}
		replacement = makeChild( kind, {
			icon: child.dataset.icon || "add",
			name: name ? name.textContent : ""
		} );
		if ( SHARED_STYLES[ style ] ) {
			replacement.classList.add( "is-style-" + style );
		}
		[ "size", "shape", "ownSize", "ownShape", "ownKind", "wantIcon", "groupSelected" ]
			.forEach( function ( key ) {
				if ( child.dataset[ key ] ) {
					replacement.dataset[ key ] = child.dataset[ key ];
				}
			} );
		// A Button that had asked for no icon keeps that answer on the way back.
		if ( kind === "button" && replacement.dataset.wantIcon === "false" ) {
			icon = replacement.querySelector( ".wp-block-button__icon" );
			if ( icon ) {
				icon.remove();
			}
		}
		// Both blocks have the Toggle variant, so the state survives the
		// transform. Only Text has no toggle, and it has no icon-button
		// counterpart either, so it cannot reach this branch carrying one.
		if ( pressed !== null ) {
			childControl( replacement ).setAttribute( "aria-pressed", pressed );
		}
		childControl( replacement ).disabled = disabled;
		child.replaceWith( replacement );
		return replacement;
	}

	function addStandardChild( host, group, kind ) {
		var values = {};
		var number = group.querySelectorAll( "[data-group-child]" ).length + 1;
		var child;
		host.querySelectorAll( "[data-group-control]" ).forEach( function ( control ) {
			values[ control.dataset.groupControl ] = control.value;
		} );
		child = makeChild( kind, {
			icon: "add",
			name: ( kind === "icon" ? "Icon button " : "Button " ) + number
		} );
		if ( values.shape === "square" ) {
			child.dataset.shape = "square";
		}
		if ( values.size !== "small" ) {
			child.dataset.size = values.size;
		}
		group.append( child );
		return child;
	}

	function connectedMarkup( host, group ) {
		var output = host.querySelector( "[data-group-markup]" );
		var nl = String.fromCharCode( 10 );
		var segments;
		if ( output ) {
			segments = visibleSegments( group );
			output.textContent = '<div class="wp-block-axismundi-button-group"\n' +
				'  data-variant="connected"\n' +
				'  data-size="' + group.dataset.size + '"\n' +
				'  data-selection="' + group.dataset.selection + '"' +
				( group.dataset.required === "true" ? '\n  data-required="true"' : "" ) +
				( group.dataset.shape ? '\n  data-shape="' + group.dataset.shape + '"' : "" ) +
				">" + nl + segments.map( function ( segment ) {
					var attributes = [
						'selected:' + ( segment.getAttribute( "aria-pressed" ) === "true" ),
						'icon:"' + ( segment.dataset.icon || "" ) + '"'
					];
					if ( ! segment.querySelector( ".wp-block-button__icon" ) ) {
						attributes.push( "showIcon:false" );
					}
					if ( segment.dataset.labels === "hidden" ) {
						attributes.push( "showLabelText:false" );
					}
					if ( segment.disabled ) {
						attributes.push( "disabled:true" );
					}
					return "  <!-- axismundi/segment {" + attributes.join( "," ) +
						"} --> " + segment.querySelector( "[data-segment-label]" ).textContent;
				} ).join( nl ) + nl + "</div>";
		}
	}

	function setSegmentIcon( segment, visible ) {
		var icon = segment.querySelector( ".wp-block-button__icon" );
		if ( visible && ! icon ) {
			segment.prepend( makeIcon( segment ) );
		}
		if ( ! visible && icon ) {
			icon.remove();
		}
	}

	function setSegmentLabel( segment, visible ) {
		var label = segment.querySelector( "[data-segment-label]" );
		if ( ! label ) {
			return;
		}
		label.classList.toggle( "screen-reader-text", ! visible );
		if ( visible ) {
			delete segment.dataset.labels;
			if ( segment.dataset.wantIcon === "false" ) {
				setSegmentIcon( segment, false );
			}
		} else {
			segment.dataset.labels = "hidden";
			setSegmentIcon( segment, true );
		}
	}

	function syncSegmentPanel( host, segment ) {
		var panel = host.querySelector( "[data-segment-panel]" );
		var label;
		if ( ! panel ) {
			return;
		}
		panel.hidden = ! segment;
		if ( ! segment ) {
			return;
		}
		label = segment.querySelector( "[data-segment-label]" );
		panel.querySelectorAll( "[data-segment-control]" ).forEach( function ( control ) {
			var key = control.dataset.segmentControl;
			if ( key === "selected" ) {
				control.checked = segment.getAttribute( "aria-pressed" ) === "true";
			} else if ( key === "show-icon" ) {
				control.checked = !! segment.querySelector( ".wp-block-button__icon" );
				control.disabled = label.classList.contains( "screen-reader-text" );
			} else if ( key === "show-label" ) {
				control.checked = ! label.classList.contains( "screen-reader-text" );
			} else if ( key === "label" ) {
				control.value = label.textContent;
			} else if ( key === "icon" ) {
				control.value = segment.dataset.icon || "";
			} else if ( key === "disabled" ) {
				control.checked = segment.disabled;
			}
		} );
	}

	function selectConnectedSegment( host, group, segment ) {
		group.querySelectorAll( SEGMENT ).forEach( function ( node ) {
			delete node.dataset.segmentSelected;
		} );
		if ( segment ) {
			segment.dataset.segmentSelected = "true";
		}
		syncSegmentPanel( host, segment );
	}

	function bindConnectedPanel( host, group ) {
		var panel = host.querySelector( "[data-segment-panel]" );
		if ( ! panel ) {
			return;
		}
		group.addEventListener( "click", function ( event ) {
			var segment = event.target.closest( SEGMENT );
			if ( segment && group.contains( segment ) && ! segment.hidden ) {
				selectConnectedSegment( host, group, segment );
				connectedMarkup( host, group );
			}
		} );
		panel.addEventListener( "input", apply );
		panel.addEventListener( "change", apply );

		function apply( event ) {
			var control = event.target.closest( "[data-segment-control]" );
			var segment = group.querySelector( '[data-segment-selected="true"]' );
			var label;
			if ( ! control || ! segment ) {
				return;
			}
			if ( control.dataset.segmentControl === "selected" ) {
				if ( control.checked && group.dataset.selection !== "multiple" ) {
					visibleSegments( group ).forEach( function ( node ) {
						node.setAttribute( "aria-pressed", "false" );
					} );
				}
				segment.setAttribute( "aria-pressed", String( control.checked ) );
			}
			if ( control.dataset.segmentControl === "show-icon" ) {
				segment.dataset.wantIcon = String( control.checked );
				setSegmentIcon( segment, control.checked );
			}
			if ( control.dataset.segmentControl === "show-label" ) {
				setSegmentLabel( segment, control.checked );
			}
			if ( control.dataset.segmentControl === "icon" ) {
				segment.dataset.icon = control.value;
				var icon = segment.querySelector( ".wp-block-button__icon" );
				if ( icon ) {
					icon.textContent = control.value;
				}
			}
			if ( control.dataset.segmentControl === "label" ) {
				label = segment.querySelector( "[data-segment-label]" );
				if ( label ) {
					label.textContent = control.value;
				}
			}
			if ( control.dataset.segmentControl === "disabled" ) {
				segment.disabled = control.checked;
			}
			normaliseConnected( group );
			syncConnectedEdges( group );
			syncSegmentPanel( host, segment );
			connectedMarkup( host, group );
		}
	}

	function bindStandardDemo( host ) {
		var group = host.querySelector( ".wp-block-buttons[data-group-stage]" );
		if ( ! group ) {
			return;
		}
		host.addEventListener( "change", function ( event ) {
			var control = event.target.closest( "[data-group-control]" );
			var children;
			if ( ! control ) {
				return;
			}
			children = Array.prototype.slice.call( group.querySelectorAll( "[data-group-child]" ) );

			// A group control is a default, so it passes over any child that
			// has set the same property for itself. That is the rule the whole
			// page argues, and it only becomes visible once a child can be
			// edited: without the skip, the group would stamp every child and
			// "the child overrules" would be a claim with nothing behind it.
			function inheriting( property ) {
				return children.filter( function ( child ) {
					return child.dataset[ "own" + property ] !== "true";
				} );
			}

			if ( control.dataset.groupControl === "shape" ) {
				setShape( inheriting( "Shape" ), control.value );
			}
			if ( control.dataset.groupControl === "size" ) {
				inheriting( "Size" ).forEach( function ( child ) {
					if ( control.value === "small" ) {
						delete child.dataset.size;
					} else {
						child.dataset.size = control.value;
					}
				} );
			}
			if ( control.dataset.groupControl === "button-type" ) {
				// Figma puts this on the group, and its plates show whole rows
				// of icon buttons and whole rows of label ones - so it governs
				// the children, not only the next insertion. The mixed example
				// proves a child may still differ, which makes it the same kind
				// of control as Size and Color: a default that passes over
				// anyone who has chosen.
				inheriting( "Kind" ).forEach( function ( child ) {
					var replaced = convertChild( child, control.value );
					if ( replaced.dataset.groupSelected ) {
						syncChildPanel( host, replaced );
					}
				} );
			}
			if ( control.dataset.groupControl === "color" ) {
				if ( control.value === "filled" ) {
					delete group.dataset.color;
				} else {
					group.dataset.color = control.value;
				}
			}
			standardMarkup( host, group );
		} );
		host.addEventListener( "click", function ( event ) {
			var action = event.target.closest( "[data-group-action]" );
			if ( ! action ) {
				return;
			}
			if ( action.dataset.groupAction === "add" ) {
				// The group's Button type is also the insertion default, which is
				// the choice a block editor makes at the moment of insertion.
				var kind = host.querySelector( '[data-group-control="button-type"]' );
				addStandardChild( host, group, kind ? kind.value : "button" );
			}
			if ( action.dataset.groupAction === "remove-last" ) {
				var children = group.querySelectorAll( "[data-group-child]" );
				if ( children.length > 1 ) {
					children[ children.length - 1 ].remove();
				}
			}
			standardMarkup( host, group );
		} );
		bindChildPanel( host, group );
		group.querySelectorAll( "[data-group-child]" ).forEach( rememberIcon );
		selectChild( host, group, group.querySelector( "[data-group-child]" ) );
		standardMarkup( host, group );
	}

	// --- The child panel -----------------------------------------------
	//
	// A block editor shows the settings of whatever is selected: the container
	// when you click the container, the child when you click the child. The
	// second panel is that, and it is the only way to demonstrate the rule the
	// page states - a group hands down a default, a child that has chosen keeps
	// its choice - because both halves have to be reachable to see one lose.

	function childSummary( child ) {
		var kind = childKind( child );
		return {
			kind: kind,
			// An icon button always has its icon: it is the whole anatomy, and
			// there is nothing left to look at without it.
			icon: kind === "icon" || !! child.querySelector( ".wp-block-button__icon" ),
			name: childName( child ),
			style: styleOf( child ).replace( "is-style-", "" ),
			size: child.dataset.size || "small",
			shape: child.dataset.shape || "round",
			width: child.dataset.width || "default",
			togglable: childControl( child ).hasAttribute( "aria-pressed" ),
			selected: childControl( child ).getAttribute( "aria-pressed" ) === "true",
			disabled: !! childControl( child ).disabled
		};
	}

	function syncChildPanel( host, child ) {
		var panel = host.querySelector( "[data-group-child-panel]" );
		var state;
		if ( ! panel ) {
			return;
		}
		panel.hidden = ! child;
		if ( ! child ) {
			return;
		}
		state = childSummary( child );
		// The panel shows the settings of the block that is selected, so rows
		// belonging to the other block are not there at all - the same thing a
		// block editor's inspector does. Width and Selected have no meaning on a
		// Button; Show icon has none on an Icon button.
		panel.querySelectorAll( "[data-child-kind]" ).forEach( function ( row ) {
			row.hidden = row.dataset.childKind !== state.kind;
		} );
		panel.querySelectorAll( "[data-child-control]" ).forEach( function ( control ) {
			var key = control.dataset.childControl;
			if ( key === "show-icon" ) {
				control.checked = state.icon;
			} else if ( key === "name" ) {
				control.value = state.name ? state.name.textContent : "";
			} else if ( key === "icon" ) {
				control.value = child.dataset.icon || "";
			} else if ( key === "togglable" ) {
				control.checked = state.togglable;
			} else if ( key === "selected" ) {
				control.checked = state.selected;
				control.disabled = ! state.togglable;
			} else if ( key === "disabled" ) {
				control.checked = state.disabled;
			} else {
				control.value = state[ key ];
			}
		} );
	}

	function selectChild( host, group, child ) {
		group.querySelectorAll( "[data-group-child]" ).forEach( function ( node ) {
			delete node.dataset.groupSelected;
		} );
		if ( child ) {
			child.dataset.groupSelected = "true";
		}
		syncChildPanel( host, child );
	}

	function bindChildPanel( host, group ) {
		var panel = host.querySelector( "[data-group-child-panel]" );
		if ( ! panel ) {
			return;
		}

		group.addEventListener( "click", function ( event ) {
			var child = event.target.closest( "[data-group-child]" );
			if ( child && group.contains( child ) ) {
				event.preventDefault();
				selectChild( host, group, child );
			}
		} );

		panel.addEventListener( "input", apply );
		panel.addEventListener( "change", apply );

		function apply( event ) {
			var control = event.target.closest( "[data-child-control]" );
			var child = group.querySelector( '[data-group-selected="true"]' );
			var key;
			var name;
			var icon;
			if ( ! control || ! child ) {
				return;
			}
			key = control.dataset.childControl;

			// Each of these marks the child as having chosen, which is what takes
			// it out of the group's default for that property. Without the mark
			// the group would stamp every child and "the child overrules" would
			// be a claim with nothing behind it.
			if ( key === "kind" ) {
				child.dataset.ownKind = "true";
				child = convertChild( child, control.value );
				syncChildPanel( host, child );
			}
			if ( key === "size" ) {
				child.dataset.ownSize = "true";
				if ( control.value === "small" ) {
					delete child.dataset.size;
				} else {
					child.dataset.size = control.value;
				}
			}
			if ( key === "shape" ) {
				child.dataset.ownShape = "true";
				if ( control.value === "square" ) {
					child.dataset.shape = "square";
				} else {
					delete child.dataset.shape;
				}
			}
			// Width has no group default to escape: only the icon button has the
			// axis, and the group publishes no value for it.
			if ( key === "width" ) {
				if ( control.value === "default" ) {
					delete child.dataset.width;
				} else {
					child.dataset.width = control.value;
				}
			}
			if ( key === "style" ) {
				Array.prototype.slice.call( child.classList ).forEach( function ( className ) {
					if ( className.indexOf( "is-style-" ) === 0 ) {
						child.classList.remove( className );
					}
				} );
				if ( control.value ) {
					child.classList.add( "is-style-" + control.value );
				}
			}
			if ( key === "name" ) {
				name = childName( child );
				if ( name ) {
					name.textContent = control.value;
				}
			}
			if ( key === "show-icon" ) {
				child.dataset.wantIcon = String( control.checked );
				icon = child.querySelector( ".wp-block-button__icon" );
				if ( control.checked && ! icon ) {
					childControl( child ).prepend( makeIcon( child ) );
				}
				if ( ! control.checked && icon ) {
					icon.remove();
				}
			}
			if ( key === "icon" ) {
				child.dataset.icon = control.value;
				icon = child.querySelector( ".material-symbols-outlined" );
				if ( icon ) {
					icon.textContent = control.value;
				}
			}
			// Togglable is a different component in Figma, not a state of this
			// one, and aria-pressed is what says so in the DOM: present makes it
			// a toggle and brings M3's separate colour table with it.
			if ( key === "togglable" ) {
				if ( control.checked ) {
					childControl( child ).setAttribute( "aria-pressed", "false" );
				} else {
					childControl( child ).removeAttribute( "aria-pressed" );
				}
				syncChildPanel( host, child );
			}
			if ( key === "selected" && childControl( child ).hasAttribute( "aria-pressed" ) ) {
				childControl( child ).setAttribute( "aria-pressed", String( control.checked ) );
			}
			if ( key === "disabled" ) {
				childControl( child ).disabled = control.checked;
			}
			standardMarkup( host, group );
		}
	}

	function bindConnectedDemo( host ) {
		var group = host.querySelector( ".wp-block-axismundi-button-group[data-group-stage]" );
		if ( ! group ) {
			return;
		}
		bindSelection( group );
		bindConnectedPanel( host, group );
		host.addEventListener( "change", function ( event ) {
			var control = event.target.closest( "[data-group-control]" );
			var number;
			if ( ! control ) {
				return;
			}
			if ( control.dataset.groupControl === "shape" ) {
				setShape( [ group ], control.value );
			}
			if ( control.dataset.groupControl === "size" ) {
				group.dataset.size = control.value;
			}
			if ( control.dataset.groupControl === "selection" ) {
				group.dataset.selection = control.value === "multiple" ? "multiple" : "single";
				if ( control.value === "single-required" ) {
					group.dataset.required = "true";
				} else {
					delete group.dataset.required;
				}
			}
			number = { showThird: 3, showFourth: 4, showFifth: 5 }[ control.dataset.groupControl ];
			if ( number ) {
				group.querySelector( '[data-segment="' + number + '"]' ).hidden = ! control.checked;
			}
			normaliseConnected( group );
			syncConnectedEdges( group );
			connectedMarkup( host, group );
		} );
		syncConnectedEdges( group );
		selectConnectedSegment( host, group, group.querySelector( SEGMENT + ':not([hidden])' ) );
		connectedMarkup( host, group );
	}

	document.querySelectorAll( ".wp-block-axismundi-button-group[data-selection]:not([data-group-stage])" ).forEach( bindSelection );
	document.querySelectorAll( '[data-group-playground="standard"]' ).forEach( bindStandardDemo );
	document.querySelectorAll( '[data-group-playground="connected"]' ).forEach( bindConnectedDemo );
} )();
