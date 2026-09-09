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
	// children actually became. Printing a count was fine while every child was
	// identical and became a lie the moment one could be edited on its own.
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
			children.map( function ( child ) {
				var link = child.querySelector( ".wp-block-button__link" );
				var label = child.querySelector( "[data-group-label]" );
				var style = Array.prototype.slice.call( child.classList ).filter( function ( name ) {
					return name.indexOf( "is-style-" ) === 0;
				} )[ 0 ];
				var attributes = [];
				if ( style ) {
					attributes.push( 'className:"' + style + '"' );
				}
				if ( child.dataset.size ) {
					attributes.push( 'size:"' + child.dataset.size + '"' );
				}
				if ( child.dataset.shape ) {
					attributes.push( 'shape:"' + child.dataset.shape + '"' );
				}
				if ( child.querySelector( ".wp-block-button__icon" ) ) {
					attributes.push( 'icon:"add"' );
				}
				if ( link && link.disabled ) {
					attributes.push( "disabled:true" );
				}
				return "  <!-- wp:button" +
					( attributes.length ? " {" + attributes.join( "," ) + "}" : "" ) +
					" -->" + ( label ? "  " + label.textContent : "" );
			} ).join( nl ) +
			nl + "</div>";
	}

	function addStandardChild( host, group, iconOnly ) {
		var controls = host.querySelectorAll( "[data-group-control]" );
		var values = {};
		var child = document.createElement( "div" );
		var button = document.createElement( "button" );
		var icon = document.createElement( "span" );
		var label = document.createElement( "span" );
		var number = group.querySelectorAll( "[data-group-child]" ).length + 1;

		controls.forEach( function ( control ) {
			values[ control.dataset.groupControl ] = control.value;
		} );
		child.className = "wp-block-button";
		child.dataset.groupChild = "";
		if ( values.shape === "square" ) {
			child.dataset.shape = "square";
		}
		if ( values.size !== "small" ) {
			child.dataset.size = values.size;
		}
		button.type = "button";
		button.className = "wp-block-button__link wp-element-button";
		icon.className = "wp-block-button__icon material-symbols-outlined notranslate";
		icon.setAttribute( "translate", "no" );
		icon.setAttribute( "aria-hidden", "true" );
		icon.textContent = "add";
		label.dataset.groupLabel = "";
		label.textContent = iconOnly ? "Icon button " + number : "Button " + number;
		if ( iconOnly ) {
			// The label element stays and moves out of sight rather than being
			// hidden: `hidden` would drop it from the accessibility tree and
			// force a second, separate aria-label, which is two sources for one
			// name. The plugin that ships this pattern adds a class for exactly
			// this reason.
			label.className = "screen-reader-text";
			button.append( icon, label );
		} else {
			// Button's anatomy makes the icon optional and the label required,
			// and Figma says the same with `Show icon: boolean`. A label button
			// added here takes no icon, which is what the worked example does:
			// "label text: get started, show icon: false".
			button.append( label );
		}
		child.append( button );
		group.append( child );
	}

	function connectedMarkup( host, group ) {
		var output = host.querySelector( "[data-group-markup]" );
		if ( output ) {
			output.textContent = '<div class="wp-block-axismundi-button-group"\n' +
				'  data-variant="connected"\n' +
				'  data-size="' + group.dataset.size + '"\n' +
				'  data-selection="' + group.dataset.selection + '"' +
				( group.dataset.required === "true" ? '\n  data-required="true"' : "" ) +
				( group.dataset.shape ? '\n  data-shape="' + group.dataset.shape + '"' : "" ) +
				">\n  Segments: " + visibleSegments( group ).length + "\n</div>";
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
				// Nothing to re-render: it governs the next insertion, not the
				// children already placed. A block editor does not retype an
				// inserted block either.
				return;
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
				// Figma's Button type is a property on the group because a
				// static file assembles its children by swapping instances.
				// Here it decides what the next child is, which is the same
				// choice a block editor makes at the moment of insertion.
				var kind = host.querySelector( '[data-group-control="button-type"]' );
				addStandardChild( host, group, kind && kind.value === "icon" );
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
		var icon = child.querySelector( ".wp-block-button__icon" );
		var label = child.querySelector( "[data-group-label]" );
		var style = Array.prototype.slice.call( child.classList ).filter( function ( name ) {
			return name.indexOf( "is-style-" ) === 0;
		} )[ 0 ];
		return {
			icon: !! icon,
			label: label,
			style: style ? style.replace( "is-style-", "" ) : "",
			size: child.dataset.size || "small",
			shape: child.dataset.shape || "round"
		};
	}

	function syncChildPanel( host, child ) {
		var panel = host.querySelector( "[data-group-child-panel]" );
		if ( ! panel ) {
			return;
		}
		panel.hidden = ! child;
		if ( ! child ) {
			return;
		}
		var state = childSummary( child );
		panel.querySelectorAll( "[data-child-control]" ).forEach( function ( control ) {
			var key = control.dataset.childControl;
			if ( key === "show-icon" ) {
				control.checked = state.icon;
			} else if ( key === "label" ) {
				control.value = state.label ? state.label.textContent : "";
				control.disabled = ! state.label;
			} else if ( key === "disabled" ) {
				control.checked = child.querySelector( ".wp-block-button__link" ).disabled;
			} else if ( key === "style" ) {
				control.value = state.style;
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
			if ( ! control || ! child ) {
				return;
			}
			var link = child.querySelector( ".wp-block-button__link" );
			var key = control.dataset.childControl;

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
			if ( key === "style" ) {
				Array.prototype.slice.call( child.classList ).forEach( function ( name ) {
					if ( name.indexOf( "is-style-" ) === 0 ) {
						child.classList.remove( name );
					}
				} );
				if ( control.value ) {
					child.classList.add( "is-style-" + control.value );
				}
			}
			if ( key === "label" ) {
				var label = child.querySelector( "[data-group-label]" );
				if ( label ) {
					label.textContent = control.value;
				}
			}
			if ( key === "show-icon" ) {
				var icon = child.querySelector( ".wp-block-button__icon" );
				if ( control.checked && ! icon ) {
					icon = document.createElement( "span" );
					icon.className = "wp-block-button__icon material-symbols-outlined notranslate";
					icon.setAttribute( "translate", "no" );
					icon.setAttribute( "aria-hidden", "true" );
					icon.textContent = "add";
					link.prepend( icon );
				}
				if ( ! control.checked && icon ) {
					icon.remove();
				}
			}
			if ( key === "disabled" ) {
				link.disabled = control.checked;
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
			connectedMarkup( host, group );
		} );
		connectedMarkup( host, group );
	}

	document.querySelectorAll( ".wp-block-axismundi-button-group[data-selection]:not([data-group-stage])" ).forEach( bindSelection );
	document.querySelectorAll( '[data-group-playground="standard"]' ).forEach( bindStandardDemo );
	document.querySelectorAll( '[data-group-playground="connected"]' ).forEach( bindConnectedDemo );
} )();
