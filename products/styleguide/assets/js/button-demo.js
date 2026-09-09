/* Interactive Button specimen for the static style guide.
 *
 * The result remains actual core/button markup. Controls only update the
 * classes and attributes the page documents; they do not emulate WordPress.
 */
( function () {
	var STYLE_CLASSES = [
		"is-style-tonal",
		"is-style-outline",
		"is-style-elevated",
		"is-style-text",
	];

	function attributes( button, link, icon, showIcon ) {
		var attrs = [];
		var pressed = link.getAttribute( "aria-pressed" );
		if ( button.dataset.size ) {
			attrs.push( 'data-size="' + button.dataset.size + '"' );
		}
		if ( button.dataset.shape ) {
			attrs.push( 'data-shape="' + button.dataset.shape + '"' );
		}

		return '<div class="' + button.className.trim() + '"'
			+ ( attrs.length ? " " + attrs.join( " " ) : "" ) + ">\n"
			+ '  <button type="button" class="wp-block-button__link wp-element-button"'
			+ ( pressed === null ? "" : ' aria-pressed="' + pressed + '"' )
			+ ( link.disabled ? " disabled" : "" )
			+ ( link.getAttribute( "aria-disabled" ) === "true" ? ' aria-disabled="true"' : "" )
			+ ">\n"
			+ ( showIcon
				? '    <span class="wp-block-button__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">'
					+ icon.textContent + "</span>\n"
				: "" )
			+ "    " + link.querySelector( "[data-playground-label]" ).textContent + "\n"
			+ "  </button>\n</div>";
	}

	function setClass( element, value ) {
		STYLE_CLASSES.forEach( function ( name ) {
			element.classList.remove( name );
		} );
		if ( value !== "filled" ) {
			element.classList.add( "is-style-" + ( value === "outlined" ? "outline" : value ) );
		}
	}

	document.querySelectorAll( "[data-button-playground]" ).forEach( function ( host ) {
		var button = host.querySelector( "[data-playground-button]" );
		var link = button.querySelector( ".wp-block-button__link" );
		var icon = button.querySelector( "[data-playground-icon]" );
		var label = button.querySelector( "[data-playground-label]" );
		var markup = host.querySelector( "[data-playground-markup]" );
		var controls = {};

		host.querySelectorAll( "[data-button-control]" ).forEach( function ( control ) {
			controls[ control.dataset.buttonControl ] = control;
		} );

		function render() {
			var showIcon = controls.showIcon.checked;
			var softDisabled = controls.softDisabled.checked;
			setClass( button, controls.style.value );
			button.dataset.size = controls.size.value === "small" ? "" : controls.size.value;
			button.dataset.shape = controls.shape.value === "round" ? "" : controls.shape.value;
			if ( ! button.dataset.size ) {
				delete button.dataset.size;
			}
			if ( ! button.dataset.shape ) {
				delete button.dataset.shape;
			}
			// Toggle is the second M3 variant, and aria-pressed is the whole of
			// it in the DOM: present means unselected, "true" means selected.
			// Text has no toggle - no container, so nothing can carry the
			// selection - and the adapter excludes it, so the control says so
			// here rather than letting a reader tick a box that does nothing.
			if ( controls.style.value === "text" ) {
				controls.togglable.checked = false;
				controls.togglable.disabled = true;
			} else {
				controls.togglable.disabled = false;
			}
			controls.selected.disabled = ! controls.togglable.checked;
			if ( controls.togglable.checked ) {
				link.setAttribute( "aria-pressed", controls.selected.checked ? "true" : "false" );
			} else {
				controls.selected.checked = false;
				link.removeAttribute( "aria-pressed" );
			}
			label.textContent = controls.label.value || "Label";
			icon.textContent = controls.icon.value;
			icon.hidden = ! showIcon;
			link.disabled = controls.disabled.checked;
			if ( softDisabled ) {
				link.setAttribute( "aria-disabled", "true" );
			} else {
				link.removeAttribute( "aria-disabled" );
			}
			markup.textContent = attributes( button, link, icon, showIcon );
		}

		// A toggle that cannot be pressed is not a toggle. The click drives the
		// control rather than the attribute, so one path writes aria-pressed
		// and the markup panel cannot drift from the button it describes.
		link.addEventListener( "click", function () {
			if ( ! controls.togglable.checked ) {
				return;
			}
			controls.selected.checked = ! controls.selected.checked;
			render();
		} );

		host.addEventListener( "input", render );
		host.addEventListener( "change", function ( event ) {
			var control = event.target.closest( "[data-button-control]" );
			if ( control === controls.disabled && control.checked ) {
				controls.softDisabled.checked = false;
			}
			if ( control === controls.softDisabled && control.checked ) {
				controls.disabled.checked = false;
			}
			render();
		} );
		render();
	} );
} )();
