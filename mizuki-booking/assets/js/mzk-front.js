/**
 * Mizuki Booking - front-end calendar and booking manager.
 * Vanilla JS, no build step, no dependencies.
 */
( function () {
	'use strict';

	var CFG = window.MZK_CFG || {};
	var I18N = CFG.i18n || {};

	/* ---------------------------------------------------------------- utils */

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}
		return node;
	}

	function clear( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function sprintf1( template, value ) {
		return String( template ).replace( /%d|%s/, value );
	}

	function ymd( date ) {
		var m = String( date.getMonth() + 1 ).padStart( 2, '0' );
		var d = String( date.getDate() ).padStart( 2, '0' );
		return date.getFullYear() + '-' + m + '-' + d;
	}

	function parseYmd( value ) {
		var parts = String( value ).split( '-' );
		return new Date( Number( parts[ 0 ] ), Number( parts[ 1 ] ) - 1, Number( parts[ 2 ] ) );
	}

	function api( path, options ) {
		options = options || {};
		var opts = {
			method: options.method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce
			},
			credentials: 'same-origin'
		};
		if ( options.body ) {
			opts.body = JSON.stringify( options.body );
		}
		return fetch( CFG.root + path, opts ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var err = new Error( ( data && data.message ) || I18N.error );
					err.data = data;
					throw err;
				}
				return data;
			} );
		} );
	}

	function notice( type, message ) {
		var box = el( 'div', 'mzk-notice mzk-notice--' + type );
		box.textContent = message;
		return box;
	}

	/* ------------------------------------------------------------- calendar */

	function Calendar( root ) {
		this.root = root;
		this.classSlug = root.getAttribute( 'data-class' ) || '';
		this.view = root.getAttribute( 'data-view' ) || 'calendar';
		this.showFilter = 'no' !== root.getAttribute( 'data-showfilter' );
		this.months = parseInt( root.getAttribute( 'data-months' ), 10 ) || CFG.months || 3;
		this.data = null;
		this.selectedDate = null;
		this.load();
	}

	Calendar.prototype.load = function () {
		var self = this;
		clear( this.root );
		this.root.appendChild( el( 'div', 'mzk-loading', I18N.loading ) );

		var query = '/calendar';
		if ( this.classSlug ) {
			query += '?class_type=' + encodeURIComponent( this.classSlug );
		}

		api( query )
			.then( function ( data ) {
				self.data = data;
				self.render();
			} )
			.catch( function ( error ) {
				clear( self.root );
				self.root.appendChild( notice( 'error', error.message || I18N.error ) );
			} );
	};

	Calendar.prototype.render = function () {
		var self = this;
		clear( this.root );

		if ( this.showFilter && this.data.classes.length > 1 ) {
			this.root.appendChild( this.renderFilter() );
		}

		var layout = el( 'div', 'mzk-layout' );
		var left = el( 'div', 'mzk-months' );
		var right = el( 'div', 'mzk-panel' );

		var dayKeys = Object.keys( this.data.days );
		if ( ! dayKeys.length ) {
			layout.appendChild( notice( 'info', I18N.noneInRange ) );
			this.root.appendChild( layout );
			return;
		}

		if ( 'list' === this.view ) {
			this.root.appendChild( this.renderList() );
			return;
		}

		dayKeys.sort();
		if ( ! this.selectedDate || ! this.data.days[ this.selectedDate ] ) {
			this.selectedDate = dayKeys[ 0 ];
		}

		var start = parseYmd( this.data.from );
		var end = parseYmd( this.data.to );
		var cursor = new Date( start.getFullYear(), start.getMonth(), 1 );
		var guard = 0;

		while ( cursor <= end && guard < 24 ) {
			left.appendChild( this.renderMonth( cursor.getFullYear(), cursor.getMonth() ) );
			cursor = new Date( cursor.getFullYear(), cursor.getMonth() + 1, 1 );
			guard++;
		}

		layout.appendChild( left );
		layout.appendChild( right );
		this.root.appendChild( layout );

		this.panel = right;
		this.renderPanel();

		// Keyboard support on the grid.
		left.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				var target = event.target;
				if ( target && target.hasAttribute( 'data-date' ) ) {
					event.preventDefault();
					self.select( target.getAttribute( 'data-date' ) );
				}
			}
		} );
	};

	Calendar.prototype.renderFilter = function () {
		var self = this;
		var bar = el( 'div', 'mzk-filter' );
		var label = el( 'label', 'mzk-filter__label', I18N.allClasses );
		var select = el( 'select', 'mzk-filter__select' );

		var all = el( 'option', null, I18N.allClasses );
		all.value = '';
		select.appendChild( all );

		this.data.classes.forEach( function ( item ) {
			var option = el( 'option', null, item.name );
			option.value = item.slug;
			if ( item.slug === self.classSlug ) {
				option.selected = true;
			}
			select.appendChild( option );
		} );

		select.addEventListener( 'change', function () {
			self.classSlug = select.value;
			self.selectedDate = null;
			self.load();
		} );

		label.setAttribute( 'for', 'mzk-filter-select' );
		select.id = 'mzk-filter-select';
		bar.appendChild( label );
		bar.appendChild( select );
		return bar;
	};

	Calendar.prototype.renderMonth = function ( year, month ) {
		var self = this;
		var wrap = el( 'div', 'mzk-month' );
		var head = el( 'div', 'mzk-month__head' );
		head.appendChild( el( 'h3', 'mzk-month__title', ( CFG.months_names[ month ] || '' ) + ' ' + year ) );
		wrap.appendChild( head );

		var grid = el( 'div', 'mzk-grid' );
		var startOfWeek = parseInt( CFG.startOfWeek, 10 ) || 0;

		for ( var i = 0; i < 7; i++ ) {
			var index = ( startOfWeek + i ) % 7;
			grid.appendChild( el( 'div', 'mzk-grid__dow', CFG.weekdays[ index ] ) );
		}

		var first = new Date( year, month, 1 );
		var lead = ( first.getDay() - startOfWeek + 7 ) % 7;
		for ( var l = 0; l < lead; l++ ) {
			grid.appendChild( el( 'div', 'mzk-day mzk-day--empty' ) );
		}

		var daysInMonth = new Date( year, month + 1, 0 ).getDate();
		for ( var d = 1; d <= daysInMonth; d++ ) {
			grid.appendChild( this.renderDay( new Date( year, month, d ) ) );
		}

		// Pad the final week so the grid's cell background never shows through
		// as a grey block after the last day of the month.
		var trailing = ( 7 - ( ( lead + daysInMonth ) % 7 ) ) % 7;
		for ( var t = 0; t < trailing; t++ ) {
			grid.appendChild( el( 'div', 'mzk-day mzk-day--empty' ) );
		}

		wrap.appendChild( grid );

		grid.addEventListener( 'click', function ( event ) {
			var cell = event.target.closest( '[data-date]' );
			if ( cell && ! cell.classList.contains( 'mzk-day--disabled' ) ) {
				self.select( cell.getAttribute( 'data-date' ) );
			}
		} );

		return wrap;
	};

	Calendar.prototype.renderDay = function ( date ) {
		var key = ymd( date );
		var sessions = this.data.days[ key ] || [];
		var blackout = this.data.blackouts[ key ];
		var isPast = key < this.data.today;

		var cell = el( 'div', 'mzk-day' );
		cell.setAttribute( 'data-date', key );
		cell.appendChild( el( 'span', 'mzk-day__num', String( date.getDate() ) ) );

		if ( blackout ) {
			cell.classList.add( 'mzk-day--blackout', 'mzk-day--disabled' );
			cell.title = blackout || I18N.closed;
		} else if ( isPast || ! sessions.length ) {
			cell.classList.add( 'mzk-day--disabled' );
		} else {
			var open = sessions.filter( function ( s ) {
				return s.bookable;
			} );
			cell.classList.add( open.length ? 'mzk-day--open' : 'mzk-day--full' );

			var dots = el( 'span', 'mzk-day__dots' );
			var seen = {};
			sessions.forEach( function ( session ) {
				if ( seen[ session.classSlug ] ) {
					return;
				}
				seen[ session.classSlug ] = true;
				var dot = el( 'i', 'mzk-dot' );
				dot.style.background = session.colour;
				dots.appendChild( dot );
			} );
			cell.appendChild( dots );
			cell.setAttribute( 'tabindex', '0' );
			cell.setAttribute( 'role', 'button' );
			cell.setAttribute( 'aria-label', key + ' – ' + sessions.length );
		}

		if ( key === this.selectedDate ) {
			cell.classList.add( 'is-selected' );
		}

		return cell;
	};

	Calendar.prototype.select = function ( key ) {
		this.selectedDate = key;
		var cells = this.root.querySelectorAll( '[data-date]' );
		Array.prototype.forEach.call( cells, function ( cell ) {
			cell.classList.toggle( 'is-selected', cell.getAttribute( 'data-date' ) === key );
		} );
		this.renderPanel();
		if ( this.panel && window.innerWidth < 900 ) {
			this.panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	Calendar.prototype.renderPanel = function () {
		var self = this;
		clear( this.panel );

		var sessions = this.data.days[ this.selectedDate ] || [];
		if ( ! sessions.length ) {
			this.panel.appendChild( notice( 'info', I18N.selectDate ) );
			return;
		}

		this.panel.appendChild( el( 'h3', 'mzk-panel__title', sessions[ 0 ].dateLabel ) );

		var list = el( 'ul', 'mzk-sessions' );
		sessions.forEach( function ( session ) {
			list.appendChild( self.renderSession( session ) );
		} );
		this.panel.appendChild( list );
	};

	Calendar.prototype.renderSession = function ( session ) {
		var self = this;
		var item = el( 'li', 'mzk-session' + ( session.bookable ? '' : ' mzk-session--full' ) );
		// Class colour marks the card edge and dots only; the call-to-action
		// stays the brand teal so buttons look identical across classes.
		item.style.setProperty( '--mzk-class', session.colour );

		var head = el( 'div', 'mzk-session__head' );
		head.appendChild( el( 'span', 'mzk-session__class', session.className ) );
		head.appendChild( el( 'span', 'mzk-session__time', session.timeLabel ) );
		item.appendChild( head );

		var meta = el( 'div', 'mzk-session__meta' );
		meta.appendChild( el( 'span', 'mzk-session__duration', session.durationLabel ) );
		meta.appendChild(
			el(
				'span',
				'mzk-session__seats' + ( session.isFull ? ' is-full' : '' ),
				session.isFull ? I18N.full : sprintf1( I18N.seatsLeft, session.seatsLeft )
			)
		);
		item.appendChild( meta );

		if ( session.bookable ) {
			var button = el( 'button', 'mzk-btn mzk-btn--primary', I18N.book );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				self.openForm( item, session, button );
			} );
			item.appendChild( button );
		}

		return item;
	};

	Calendar.prototype.openForm = function ( item, session, button ) {
		var self = this;
		if ( item.querySelector( '.mzk-form' ) ) {
			return;
		}
		button.style.display = 'none';

		var form = el( 'form', 'mzk-form' );

		function field( name, labelText, type, required ) {
			var wrap = el( 'label', 'mzk-field' );
			wrap.appendChild( el( 'span', 'mzk-field__label', labelText + ( required ? ' *' : '' ) ) );
			var input = 'textarea' === type ? el( 'textarea' ) : el( 'input' );
			if ( 'textarea' !== type ) {
				input.type = type;
			}
			input.name = name;
			input.className = 'mzk-input';
			if ( required ) {
				input.required = true;
			}
			wrap.appendChild( input );
			form.appendChild( wrap );
			return input;
		}

		var name = field( 'student_name', I18N.name, 'text', true );
		var email = field( 'email', I18N.email, 'email', true );
		var phone = field( 'phone', I18N.phone, 'tel', !! CFG.requirePhone );
		field( 'notes', I18N.notes, 'textarea', false );

		// Honeypot.
		var honey = el( 'input', 'mzk-honey' );
		honey.type = 'text';
		honey.name = 'website';
		honey.tabIndex = -1;
		honey.autocomplete = 'off';
		form.appendChild( honey );

		var actions = el( 'div', 'mzk-form__actions' );
		var submit = el( 'button', 'mzk-btn mzk-btn--primary', I18N.confirm );
		submit.type = 'submit';
		var cancel = el( 'button', 'mzk-btn mzk-btn--ghost', I18N.cancel );
		cancel.type = 'button';
		actions.appendChild( submit );
		actions.appendChild( cancel );
		form.appendChild( actions );

		cancel.addEventListener( 'click', function () {
			form.remove();
			button.style.display = '';
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var existing = item.querySelector( '.mzk-notice' );
			if ( existing ) {
				existing.remove();
			}

			if ( ! name.value.trim() || ! email.value.trim() || ( CFG.requirePhone && ! phone.value.trim() ) ) {
				form.appendChild( notice( 'error', I18N.required ) );
				return;
			}

			submit.disabled = true;
			submit.textContent = I18N.booking;

			var payload = {
				session_id: session.id,
				student_name: name.value,
				email: email.value,
				phone: phone.value,
				notes: form.elements.notes.value,
				website: honey.value
			};

			api( '/bookings', { method: 'POST', body: payload } )
				.then( function ( response ) {
					var done = el( 'div', 'mzk-success' );
					done.appendChild( el( 'h4', null, response.message ) );
					done.appendChild( el( 'p', null, session.className + ' — ' + session.dateLabel + ', ' + session.timeLabel ) );
					var link = el( 'a', 'mzk-btn mzk-btn--ghost', I18N.reschedule );
					link.href = response.manageUrl;
					done.appendChild( link );
					clear( item );
					item.classList.add( 'mzk-session--booked' );
					item.appendChild( done );
					self.load();
				} )
				.catch( function ( error ) {
					submit.disabled = false;
					submit.textContent = I18N.confirm;
					form.appendChild( notice( 'error', error.message || I18N.error ) );
				} );
		} );

		item.appendChild( form );
		name.focus();
	};

	Calendar.prototype.renderList = function () {
		var self = this;
		var wrap = el( 'div', 'mzk-list' );
		var keys = Object.keys( this.data.days ).sort();

		keys.forEach( function ( key ) {
			var group = el( 'div', 'mzk-list__day' );
			group.appendChild( el( 'h3', 'mzk-list__date', self.data.days[ key ][ 0 ].dateLabel ) );
			var list = el( 'ul', 'mzk-sessions' );
			self.data.days[ key ].forEach( function ( session ) {
				list.appendChild( self.renderSession( session ) );
			} );
			group.appendChild( list );
			wrap.appendChild( group );
		} );

		return wrap;
	};

	/* ------------------------------------------------------ booking manager */

	function Manager( root ) {
		this.root = root;
		this.bookingId = parseInt( root.getAttribute( 'data-booking' ), 10 ) || 0;
		this.token = root.getAttribute( 'data-token' ) || '';
		this.loggedIn = '1' === root.getAttribute( 'data-logged-in' );
		this.load();
	}

	Manager.prototype.load = function () {
		var self = this;
		clear( this.root );
		this.root.appendChild( el( 'div', 'mzk-loading', I18N.loading ) );

		if ( this.bookingId ) {
			api( '/bookings/' + this.bookingId + '?token=' + encodeURIComponent( this.token ) )
				.then( function ( data ) {
					clear( self.root );
					self.root.appendChild( self.renderBooking( data.booking, data.alternates ) );
				} )
				.catch( function ( error ) {
					clear( self.root );
					self.root.appendChild( notice( 'error', error.message || I18N.error ) );
				} );
			return;
		}

		if ( ! this.loggedIn ) {
			clear( this.root );
			return;
		}

		api( '/my-bookings' )
			.then( function ( data ) {
				clear( self.root );
				if ( ! data.bookings.length ) {
					self.root.appendChild( notice( 'info', I18N.noBookings ) );
					return;
				}
				data.bookings.forEach( function ( booking ) {
					self.root.appendChild( self.renderBooking( booking, null ) );
				} );
			} )
			.catch( function ( error ) {
				clear( self.root );
				self.root.appendChild( notice( 'error', error.message || I18N.error ) );
			} );
	};

	Manager.prototype.renderBooking = function ( booking, alternates ) {
		var self = this;
		var card = el( 'div', 'mzk-booking mzk-booking--' + booking.status );

		card.appendChild( el( 'h3', 'mzk-booking__title', booking.title ) );
		var meta = el( 'div', 'mzk-booking__meta' );
		meta.appendChild( el( 'span', null, booking.dateLabel ) );
		meta.appendChild( el( 'span', null, booking.timeLabel ) );
		meta.appendChild( el( 'span', 'mzk-badge', booking.statusLabel ) );
		card.appendChild( meta );

		if ( 'confirmed' !== booking.status || booking.isPast ) {
			return card;
		}

		var actions = el( 'div', 'mzk-booking__actions' );

		if ( booking.canReschedule ) {
			var move = el( 'button', 'mzk-btn mzk-btn--primary', I18N.reschedule );
			move.type = 'button';
			move.addEventListener( 'click', function () {
				self.openReschedule( card, booking, alternates );
			} );
			actions.appendChild( move );
		} else if ( booking.rescheduleNote ) {
			card.appendChild( notice( 'info', booking.rescheduleNote ) );
		}

		if ( booking.canCancel ) {
			var kill = el( 'button', 'mzk-btn mzk-btn--ghost', I18N.cancelBooking );
			kill.type = 'button';
			kill.addEventListener( 'click', function () {
				if ( ! window.confirm( I18N.confirmCancel ) ) {
					return;
				}
				kill.disabled = true;
				api( '/bookings/' + booking.id + '/cancel', {
					method: 'POST',
					body: { token: self.token }
				} )
					.then( function () {
						self.load();
					} )
					.catch( function ( error ) {
						kill.disabled = false;
						card.appendChild( notice( 'error', error.message || I18N.error ) );
					} );
			} );
			actions.appendChild( kill );
		}

		card.appendChild( actions );
		return card;
	};

	Manager.prototype.openReschedule = function ( card, booking, alternates ) {
		var self = this;
		if ( card.querySelector( '.mzk-alternates' ) ) {
			return;
		}

		var box = el( 'div', 'mzk-alternates' );
		box.appendChild( el( 'h4', null, I18N.chooseNew ) );

		var render = function ( options ) {
			if ( ! options || ! options.length ) {
				box.appendChild( notice( 'info', I18N.noAlternates ) );
				return;
			}
			var list = el( 'ul', 'mzk-sessions' );
			options.forEach( function ( session ) {
				var item = el( 'li', 'mzk-session' );
				// Class colour marks the card edge and dots only; the call-to-action
		// stays the brand teal so buttons look identical across classes.
		item.style.setProperty( '--mzk-class', session.colour );
				var head = el( 'div', 'mzk-session__head' );
				head.appendChild( el( 'span', 'mzk-session__class', session.dateLabel ) );
				head.appendChild( el( 'span', 'mzk-session__time', session.timeLabel ) );
				item.appendChild( head );
				item.appendChild(
					el( 'div', 'mzk-session__meta', sprintf1( I18N.seatsLeft, session.seatsLeft ) )
				);

				var pick = el( 'button', 'mzk-btn mzk-btn--primary', I18N.moveHere );
				pick.type = 'button';
				pick.addEventListener( 'click', function () {
					pick.disabled = true;
					api( '/bookings/' + booking.id + '/reschedule', {
						method: 'POST',
						body: { session_id: session.id, token: self.token }
					} )
						.then( function () {
							self.load();
						} )
						.catch( function ( error ) {
							pick.disabled = false;
							box.appendChild( notice( 'error', error.message || I18N.error ) );
						} );
				} );
				item.appendChild( pick );
				list.appendChild( item );
			} );
			box.appendChild( list );
		};

		if ( alternates ) {
			render( alternates );
		} else {
			api( '/bookings/' + booking.id + '?token=' + encodeURIComponent( this.token ) )
				.then( function ( data ) {
					render( data.alternates );
				} )
				.catch( function ( error ) {
					box.appendChild( notice( 'error', error.message || I18N.error ) );
				} );
		}

		card.appendChild( box );
	};

	/* ------------------------------------------------------------- bootstrap */

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-mzk-calendar]' ), function ( node ) {
			new Calendar( node );
		} );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-mzk-manage]' ), function ( node ) {
			new Manager( node );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
