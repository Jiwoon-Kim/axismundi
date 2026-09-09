/**
 * Icon button interactive demos.
 *
 * Two of them, and separately, because the Figma components are two: the
 * togglable one adds Selected and Icon(selected) and the plain one has neither.
 * Collapsing them into one panel would invent a control that does not exist on
 * the default icon button.
 *
 * Every control writes an attribute the adapter already reads, and the panel
 * under each stage prints what a block would save. Nothing here simulates a
 * rendering engine.
 */
( function () {
	var BUTTON = ".wp-block-axismundi-icon-button";

	function apply( host, button ) {
		var values = {};
		host.querySelectorAll( "[data-icon-control]" ).forEach( function ( control ) {
			values[ control.dataset.iconControl ] = control.type === "checkbox"
				? control.checked
				: control.value;
		} );

		// Round, small and default width are the defaults, so they leave no
		// attribute behind - the same shape the block would save.
		if ( values.shape === "square" ) {
			button.dataset.shape = "square";
		} else {
			delete button.dataset.shape;
		}
		if ( values.size && values.size !== "small" ) {
			button.dataset.size = values.size;
		} else {
			delete button.dataset.size;
		}
		if ( values.width && values.width !== "default" ) {
			button.dataset.width = values.width;
		} else {
			delete button.dataset.width;
		}

		Array.prototype.slice.call( button.classList ).forEach( function ( name ) {
			if ( name.indexOf( "is-style-" ) === 0 ) {
				button.classList.remove( name );
			}
		} );
		if ( values.color ) {
			button.classList.add( "is-style-" + values.color );
		}

		var glyph = button.querySelector( ".material-symbols-outlined" );
		if ( glyph && typeof values.icon === "string" && values.icon ) {
			glyph.textContent = values.icon;
		}

		if ( "selected" in values ) {
			button.setAttribute( "aria-pressed", values.selected ? "true" : "false" );
		}

		button.disabled = !! values.disabled;

		// The browser owns the real focus ring, and :focus-visible only fires
		// for keyboard focus - which a reader inspecting the page is not doing.
		// This forces the same ring so it can be looked at, and it is the one
		// control here that renders something the attribute set never stores.
		button.classList.toggle( "is-forced-focus", !! values.focusRing );

		markup( host, button, values );
	}

	function markup( host, button, values ) {
		var output = host.querySelector( "[data-icon-markup]" );
		var nl = String.fromCharCode( 10 );
		var attributes = [];
		if ( ! output ) {
			return;
		}
		if ( values.color ) {
			attributes.push( 'className:"is-style-' + values.color + '"' );
		}
		if ( button.dataset.size ) {
			attributes.push( 'size:"' + button.dataset.size + '"' );
		}
		if ( button.dataset.width ) {
			attributes.push( 'width:"' + button.dataset.width + '"' );
		}
		if ( button.dataset.shape ) {
			attributes.push( 'shape:"' + button.dataset.shape + '"' );
		}
		attributes.push( 'icon:"' + values.icon + '"' );
		if ( "selected" in values ) {
			attributes.push( "selected:" + ( values.selected ? "true" : "false" ) );
		}
		if ( values.disabled ) {
			attributes.push( "disabled:true" );
		}

		var label = button.querySelector( ".screen-reader-text" );
		output.textContent =
			"<!-- wp:axismundi/icon-button {" + attributes.join( "," ) + "} -->" + nl +
			'<button class="wp-block-axismundi-icon-button' +
			( values.color ? " is-style-" + values.color : "" ) + '"' +
			( button.dataset.size ? ' data-size="' + button.dataset.size + '"' : "" ) +
			( button.dataset.width ? ' data-width="' + button.dataset.width + '"' : "" ) +
			( button.dataset.shape ? ' data-shape="' + button.dataset.shape + '"' : "" ) +
			( "selected" in values ? ' aria-pressed="' + ( values.selected ? "true" : "false" ) + '"' : "" ) +
			( values.disabled ? " disabled" : "" ) + ">" + nl +
			'  <span class="material-symbols-outlined" aria-hidden="true">' + values.icon + "</span>" + nl +
			'  <span class="screen-reader-text">' + ( label ? label.textContent : "" ) + "</span>" + nl +
			"</button>";
	}

	document.querySelectorAll( "[data-icon-playground]" ).forEach( function ( host ) {
		var button = host.querySelector( BUTTON );
		if ( ! button ) {
			return;
		}
		// The specimen is a real toggle, so pressing it should toggle. The click
		// drives the control rather than the attribute: one path sets
		// aria-pressed, and the panel cannot drift from the thing it describes.
		var selected = host.querySelector( '[data-icon-control="selected"]' );
		if ( selected ) {
			button.addEventListener( "click", function () {
				selected.checked = ! selected.checked;
				selected.dispatchEvent( new Event( "change", { bubbles: true } ) );
			} );
		}

		host.addEventListener( "input", function () {
			apply( host, button );
		} );
		host.addEventListener( "change", function () {
			apply( host, button );
		} );
		apply( host, button );
	} );
} )();
