/**
 * Mizuki Booking - admin helpers.
 */
( function () {
	'use strict';

	var STRINGS = window.MZK_ADMIN || {};

	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest( '[data-mzk-confirm], [data-mzk-confirm-cancel]' );
		if ( ! target ) {
			return;
		}
		var message = target.hasAttribute( 'data-mzk-confirm-cancel' )
			? STRINGS.confirmCancel
			: STRINGS.confirmDelete;
		if ( ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	// Class photo picker, using the WordPress media library.
	// The handler is bound regardless and checks wp.media when clicked, so load
	// order can never leave the button dead.
	var media = document.querySelector( '[data-mzk-media]' );
	if ( media ) {
		var input = media.querySelector( 'input[type="hidden"]' );
		var preview = media.querySelector( '.mzk-media__preview' );
		var frame = null;

		media.querySelector( '[data-mzk-media-pick]' ).addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				window.alert( 'The WordPress media library did not load on this screen. Please reload the page and try again.' );
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'Choose a photo for this class',
					library: { type: 'image' },
					button: { text: 'Use this photo' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var item = frame.state().get( 'selection' ).first().toJSON();
					input.value = item.id;
					var url = ( item.sizes && item.sizes.thumbnail ) ? item.sizes.thumbnail.url : item.url;
					preview.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = url;
					img.alt = '';
					preview.appendChild( img );
				} );
			}
			frame.open();
		} );

		media.querySelector( '[data-mzk-media-clear]' ).addEventListener( 'click', function () {
			input.value = '0';
			preview.innerHTML = '';
		} );
	}

	// Fill duration and capacity from the chosen class defaults, but only while
	// the fields still hold the previous defaults (never overwrite manual edits).
	var picker = document.querySelector( '[data-mzk-class-defaults]' );
	if ( picker ) {
		var duration = document.getElementById( 'mzk-duration' );
		var capacity = document.getElementById( 'mzk-capacity' );

		picker.addEventListener( 'change', function () {
			var option = picker.options[ picker.selectedIndex ];
			if ( ! option ) {
				return;
			}
			if ( duration ) {
				duration.value = option.getAttribute( 'data-duration' );
			}
			if ( capacity ) {
				capacity.value = option.getAttribute( 'data-capacity' );
			}
		} );
	}
} )();
