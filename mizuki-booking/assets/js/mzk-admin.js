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
