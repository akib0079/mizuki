<?php
/**
 * Sessions: the bookable time slots. Several per day, each with its own
 * start time, duration and participant limit.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Sessions {

	/**
	 * Query sessions with seats-taken counts attached.
	 *
	 * @param array $args from, to, class_type_id, status, ids, include_full, only_bookable, orderby, limit.
	 * @return object[]
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'from'          => '',
				'to'            => '',
				'class_type_id' => 0,
				'status'        => '',
				'ids'           => array(),
				'only_bookable' => false,
				'limit'         => 0,
				'offset'        => 0,
				'orderby'       => 'session_date ASC, start_time ASC',
			)
		);

		$sessions = MZK_DB::sessions();
		$types    = MZK_DB::class_types();
		$bookings = MZK_DB::bookings();
		$statuses = MZK_Utils::occupying_statuses();
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$params = $statuses;
		$where  = array( '1=1' );

		if ( $args['from'] ) {
			$where[]  = 's.session_date >= %s';
			$params[] = $args['from'];
		}
		if ( $args['to'] ) {
			$where[]  = 's.session_date <= %s';
			$params[] = $args['to'];
		}
		if ( $args['class_type_id'] ) {
			$where[]  = 's.class_type_id = %d';
			$params[] = (int) $args['class_type_id'];
		}
		if ( $args['status'] ) {
			$where[]  = 's.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['ids'] ) ) {
			$ids     = array_map( 'intval', (array) $args['ids'] );
			$where[] = 's.id IN (' . implode( ',', $ids ) . ')';
		}

		$limit_sql = '';
		if ( $args['limit'] ) {
			$limit_sql = 'LIMIT %d OFFSET %d';
			$params[]  = (int) $args['limit'];
			$params[]  = (int) $args['offset'];
		}

		$orderby = preg_replace( '/[^a-zA-Z0-9_,\. ]/', '', $args['orderby'] );

		$sql = "SELECT s.*,
					ct.name AS class_name, ct.slug AS class_slug, ct.colour AS class_colour,
					ct.payment_mode, ct.product_id, ct.booking_url,
					ct.course_based, ct.requires_enrollment, ct.reschedule_enabled,
					ct.reschedule_cutoff_hours, ct.cancel_enabled, ct.cancel_cutoff_hours,
					COALESCE(bk.seats_taken, 0) AS seats_taken
				FROM {$sessions} s
				LEFT JOIN {$types} ct ON ct.id = s.class_type_id
				LEFT JOIN (
					SELECT session_id, SUM(seats) AS seats_taken
					FROM {$bookings}
					WHERE status IN ({$in})
					GROUP BY session_id
				) bk ON bk.session_id = s.id
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY {$orderby} {$limit_sql}";

		$sql  = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB

		if ( ! $rows ) {
			return array();
		}

		// Decorate with blackout + availability info.
		$from     = $args['from'] ? $args['from'] : $rows[0]->session_date;
		$to       = $args['to'] ? $args['to'] : end( $rows )->session_date;
		$blackout = MZK_Blackouts::map( $from, $to, null );

		$out = array();
		foreach ( $rows as $row ) {
			$row = self::decorate( $row, $blackout );
			if ( $args['only_bookable'] && ! $row->is_bookable ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Attach computed availability fields to a session row.
	 *
	 * @param object     $row      Session row with seats_taken.
	 * @param array|null $blackout Optional date => reason map.
	 * @return object
	 */
	public static function decorate( $row, $blackout = null ) {
		$row->id                  = (int) $row->id;
		$row->class_type_id       = (int) $row->class_type_id;
		$row->capacity            = (int) $row->capacity;
		$row->capacity_adjustment = (int) $row->capacity_adjustment;
		$row->duration_minutes    = (int) $row->duration_minutes;
		$row->seats_taken         = (int) $row->seats_taken;

		// A negative adjustment holds seats back (students who signed up over chat);
		// a positive one opens extra seats for a one-off larger group.
		$row->effective_capacity = max( 0, $row->capacity + $row->capacity_adjustment );
		$row->seats_available    = max( 0, $row->effective_capacity - $row->seats_taken );
		$row->is_full            = $row->seats_available < 1;

		if ( null === $blackout ) {
			$row->blackout_reason = MZK_Blackouts::is_blocked( $row->session_date, $row->class_type_id )
				? __( 'Studio closed', 'mizuki-booking' )
				: '';
		} else {
			$row->blackout_reason = isset( $blackout[ $row->session_date ] ) ? $blackout[ $row->session_date ] : '';
		}
		$row->is_blacked_out = '' !== $row->blackout_reason;

		$start           = MZK_Utils::session_start( $row );
		$row->start_iso  = $start ? $start->format( DATE_ATOM ) : '';
		$row->is_past    = $start ? $start->getTimestamp() < time() : false;
		$row->time_label = MZK_Utils::format_time_range( $row );
		$row->date_label = MZK_Utils::format_date( $row->session_date );
		$row->duration_label = MZK_Utils::format_duration( $row->duration_minutes );

		$row->is_bookable = ( 'open' === $row->status )
			&& ! $row->is_past
			&& ! $row->is_full
			&& ! $row->is_blacked_out;

		// How a student gets this place: book it here, buy it in the shop, or use
		// a course package. The calendar shows the right control for each.
		$type               = (object) array(
			'payment_mode'        => $row->payment_mode ?? '',
			'requires_enrollment' => $row->requires_enrollment ?? 0,
			'product_id'          => $row->product_id ?? 0,
			'booking_url'         => $row->booking_url ?? '',
			'slug'                => $row->class_slug ?? '',
		);
		$row->payment_mode  = MZK_Class_Types::payment_mode( $type );
		$row->enrol_url     = 'free' === $row->payment_mode ? '' : MZK_Class_Types::purchase_url( $type );
		$row->enrol_label   = 'package' === $row->payment_mode
			? __( 'About this course', 'mizuki-booking' )
			: __( 'Book and pay', 'mizuki-booking' );

		/**
		 * Filter a decorated session row before it reaches the calendar.
		 *
		 * @param object $row Session row.
		 */
		return apply_filters( 'mzk_session_decorated', $row );
	}

	/**
	 * Fetch a single session with availability data.
	 *
	 * @param int $id Session id.
	 * @return object|null
	 */
	public static function get( $id ) {
		$rows = self::query( array( 'ids' => array( (int) $id ) ) );
		return $rows ? $rows[0] : null;
	}

	/**
	 * Sessions grouped by date, for calendar rendering.
	 *
	 * @param array $args Same as query().
	 * @return array<string,object[]>
	 */
	public static function grouped_by_date( $args = array() ) {
		$grouped = array();
		foreach ( self::query( $args ) as $row ) {
			$grouped[ $row->session_date ][] = $row;
		}
		return $grouped;
	}

	/**
	 * Create or update a session.
	 *
	 * @param array $data Session fields; include 'id' to update.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = MZK_DB::sessions();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$type = MZK_Class_Types::get( (int) ( $data['class_type_id'] ?? 0 ) );
		if ( ! $type ) {
			return new WP_Error( 'mzk_bad_class', __( 'Please choose a class.', 'mizuki-booking' ) );
		}

		$date = MZK_Utils::sanitize_date( $data['session_date'] ?? '' );
		if ( ! $date ) {
			return new WP_Error( 'mzk_bad_date', __( 'Please enter a valid session date.', 'mizuki-booking' ) );
		}

		$time = MZK_Utils::sanitize_time( $data['start_time'] ?? '' );
		if ( ! $time ) {
			return new WP_Error( 'mzk_bad_time', __( 'Please enter a valid start time.', 'mizuki-booking' ) );
		}

		$duration = (int) ( $data['duration_minutes'] ?? $type->default_duration );
		if ( $duration < 15 ) {
			return new WP_Error( 'mzk_bad_duration', __( 'Duration must be at least 15 minutes.', 'mizuki-booking' ) );
		}

		$capacity = (int) ( $data['capacity'] ?? $type->default_capacity );
		if ( $capacity < 1 ) {
			return new WP_Error( 'mzk_bad_capacity', __( 'Participant limit must be at least 1.', 'mizuki-booking' ) );
		}

		$status   = isset( $data['status'] ) && array_key_exists( $data['status'], MZK_Utils::session_statuses() )
			? $data['status']
			: 'open';
		$adjust   = (int) ( $data['capacity_adjustment'] ?? 0 );

		// Never let an edit shrink capacity below the seats already taken.
		if ( $id ) {
			$taken = self::seats_taken( $id );
			if ( max( 0, $capacity + $adjust ) < $taken ) {
				return new WP_Error(
					'mzk_capacity_below_booked',
					sprintf(
						/* translators: %d: number of confirmed participants. */
						__( 'This session already has %d confirmed participants. Cancel a booking first, or raise the limit.', 'mizuki-booking' ),
						$taken
					)
				);
			}
		}

		$row = array(
			'class_type_id'       => (int) $type->id,
			'title'               => sanitize_text_field( $data['title'] ?? '' ),
			'session_date'        => $date,
			'start_time'          => $time,
			'duration_minutes'    => $duration,
			'capacity'            => $capacity,
			'capacity_adjustment' => $adjust,
			'status'              => $status,
			'notes'               => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_at'          => current_time( 'mysql' ),
		);

		if ( $id ) {
			$before = self::get( $id );
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB

			/**
			 * Fires after an existing session is updated.
			 *
			 * @param int    $id     Session id.
			 * @param array  $row    New values.
			 * @param object $before Session row before the update.
			 */
			do_action( 'mzk_session_updated', $id, $row, $before );
		} else {
			$row['template_id'] = (int) ( $data['template_id'] ?? 0 );
			$row['created_at']  = current_time( 'mysql' );
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;

			/** Fires after a new session is created. */
			do_action( 'mzk_session_created', $id, $row );
		}

		return $id;
	}

	/**
	 * Nudge a session's manual capacity adjustment. Used by the quick +/- controls
	 * so the studio can hold seats for students who booked over chat.
	 *
	 * @param int $id    Session id.
	 * @param int $delta Positive opens seats, negative holds them back.
	 * @return int|WP_Error New effective capacity.
	 */
	public static function adjust_capacity( $id, $delta ) {
		$session = self::get( $id );
		if ( ! $session ) {
			return new WP_Error( 'mzk_no_session', __( 'Session not found.', 'mizuki-booking' ) );
		}
		$new_adjust = (int) $session->capacity_adjustment + (int) $delta;
		$effective  = max( 0, (int) $session->capacity + $new_adjust );

		if ( $effective < (int) $session->seats_taken ) {
			return new WP_Error(
				'mzk_capacity_below_booked',
				sprintf(
					/* translators: %d: number of confirmed participants. */
					__( 'Cannot go below the %d participants already booked.', 'mizuki-booking' ),
					(int) $session->seats_taken
				)
			);
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::sessions(),
			array(
				'capacity_adjustment' => $new_adjust,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);

		return $effective;
	}

	/**
	 * Set a session's status (open / closed / cancelled).
	 *
	 * @param int    $id     Session id.
	 * @param string $status New status.
	 * @return true|WP_Error
	 */
	public static function set_status( $id, $status ) {
		if ( ! array_key_exists( $status, MZK_Utils::session_statuses() ) ) {
			return new WP_Error( 'mzk_bad_status', __( 'Unknown session status.', 'mizuki-booking' ) );
		}
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::sessions(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
		return true;
	}

	/**
	 * Seats occupied on a session.
	 *
	 * @param int $session_id Session id.
	 * @return int
	 */
	public static function seats_taken( $session_id ) {
		global $wpdb;
		$bookings = MZK_DB::bookings();
		$statuses = MZK_Utils::occupying_statuses();
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params   = array_merge( array( (int) $session_id ), $statuses );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT COALESCE(SUM(seats),0) FROM {$bookings} WHERE session_id = %d AND status IN ({$in})", $params ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Delete a session. Refuses while active bookings exist.
	 *
	 * @param int  $id    Session id.
	 * @param bool $force Delete even with bookings (bookings are cancelled).
	 * @return true|WP_Error
	 */
	public static function delete( $id, $force = false ) {
		$id    = (int) $id;
		$taken = self::seats_taken( $id );
		if ( $taken && ! $force ) {
			return new WP_Error(
				'mzk_session_has_bookings',
				sprintf(
					/* translators: %d: number of participants. */
					__( 'This session has %d participants booked. Cancel the session instead, or move those students first.', 'mizuki-booking' ),
					$taken
				)
			);
		}
		global $wpdb;
		if ( $force ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				MZK_DB::bookings(),
				array(
					'status'     => 'cancelled',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'session_id' => $id )
			);
		}
		$wpdb->delete( MZK_DB::sessions(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		return true;
	}

	/**
	 * Move a session to another date and/or time, and tell everyone booked on it.
	 *
	 * This is the "I have to be away that weekend" case: the class still runs, it
	 * just moves. Students keep their place — they are not cancelled and re-booked —
	 * so nobody loses a seat and no course session is spent twice.
	 *
	 * @param int    $id      Session id.
	 * @param string $date    New date, Y-m-d.
	 * @param string $time    New start time, H:i.
	 * @param array  $opts    notify (bool), duration_minutes (int|null), reason (string).
	 * @return array|WP_Error {moved:bool, notified:int}
	 */
	public static function move( $id, $date, $time, $opts = array() ) {
		global $wpdb;

		$session = self::get( $id );
		if ( ! $session ) {
			return new WP_Error( 'mzk_no_session', __( 'Session not found.', 'mizuki-booking' ) );
		}

		$date = MZK_Utils::sanitize_date( $date );
		$time = MZK_Utils::sanitize_time( $time );
		if ( ! $date || ! $time ) {
			return new WP_Error( 'mzk_bad_when', __( 'Please give a valid new date and time.', 'mizuki-booking' ) );
		}

		$duration = isset( $opts['duration_minutes'] ) && $opts['duration_minutes']
			? max( 15, (int) $opts['duration_minutes'] )
			: (int) $session->duration_minutes;

		if ( $date === $session->session_date
			&& $time === $session->start_time
			&& $duration === (int) $session->duration_minutes ) {
			return new WP_Error( 'mzk_no_change', __( 'That is already when the session runs.', 'mizuki-booking' ) );
		}

		// Refuse to move a class on top of another one for the same students.
		$clash = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT id FROM " . MZK_DB::sessions() . " WHERE class_type_id = %d AND session_date = %s AND start_time = %s AND id <> %d", // phpcs:ignore WordPress.DB
				(int) $session->class_type_id,
				$date,
				$time,
				(int) $id
			)
		);
		if ( $clash ) {
			return new WP_Error(
				'mzk_clash',
				__( 'There is already a session of this class at that date and time.', 'mizuki-booking' )
			);
		}

		$old = (object) array(
			'session_date'     => $session->session_date,
			'start_time'       => $session->start_time,
			'duration_minutes' => $session->duration_minutes,
		);

		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::sessions(),
			array(
				'session_date'     => $date,
				'start_time'       => $time,
				'duration_minutes' => $duration,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);

		// A moved class needs its reminder sending again for the new date.
		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE " . MZK_DB::bookings() . " SET reminder_sent_at = NULL WHERE session_id = %d", // phpcs:ignore WordPress.DB
				(int) $id
			)
		);

		$notified = 0;
		if ( empty( $opts['skip_emails'] ) ) {
			$booked = MZK_Bookings::query(
				array(
					'session_id' => (int) $id,
					'statuses'   => array( 'confirmed', 'awaiting_approval' ),
				)
			);
			foreach ( $booked as $booking ) {
				if ( MZK_Mailer::send_session_moved( (int) $booking->id, $old, $opts['reason'] ?? '' ) ) {
					++$notified;
				}
			}
		}

		/**
		 * Fires after a session is moved to a new date or time.
		 *
		 * @param int    $id  Session id.
		 * @param object $old Previous date/time.
		 */
		do_action( 'mzk_session_moved', (int) $id, $old );

		return array(
			'moved'    => true,
			'notified' => $notified,
		);
	}

	/**
	 * Create many sessions at once: a set of dates, each with the same time slots.
	 *
	 * Studios plan a term as "these weekends, morning and afternoon", which is
	 * tedious one session at a time. Existing sessions are never duplicated.
	 *
	 * @param int    $class_type_id Class type id.
	 * @param array  $dates         Y-m-d strings.
	 * @param array  $slots         Each: array( 'time' => 'H:i', 'duration' => minutes, 'capacity' => int ).
	 * @param string $title         Optional session name.
	 * @return array|WP_Error {created:int, skipped:int, blocked:int}
	 */
	public static function bulk_create( $class_type_id, $dates, $slots, $title = '' ) {
		$type = MZK_Class_Types::get( $class_type_id );
		if ( ! $type ) {
			return new WP_Error( 'mzk_bad_class', __( 'Please choose a class.', 'mizuki-booking' ) );
		}

		$clean_dates = array();
		foreach ( (array) $dates as $date ) {
			$date = MZK_Utils::sanitize_date( trim( $date ) );
			if ( $date ) {
				$clean_dates[ $date ] = true;
			}
		}
		$clean_dates = array_keys( $clean_dates );

		$clean_slots = array();
		foreach ( (array) $slots as $slot ) {
			$time = MZK_Utils::sanitize_time( $slot['time'] ?? '' );
			if ( ! $time ) {
				continue;
			}
			$clean_slots[] = array(
				'time'     => $time,
				'duration' => max( 15, (int) ( $slot['duration'] ?? $type->default_duration ) ),
				'capacity' => max( 1, (int) ( $slot['capacity'] ?? $type->default_capacity ) ),
			);
		}

		if ( ! $clean_dates ) {
			return new WP_Error( 'mzk_no_dates', __( 'Please choose at least one date.', 'mizuki-booking' ) );
		}
		if ( ! $clean_slots ) {
			return new WP_Error( 'mzk_no_slots', __( 'Please add at least one time slot.', 'mizuki-booking' ) );
		}

		$stats = array(
			'created' => 0,
			'skipped' => 0,
			'blocked' => 0,
		);

		sort( $clean_dates );
		$blackouts = MZK_Blackouts::map( $clean_dates[0], end( $clean_dates ), (int) $type->id );

		foreach ( $clean_dates as $date ) {
			if ( isset( $blackouts[ $date ] ) ) {
				++$stats['blocked'];
				continue;
			}

			foreach ( $clean_slots as $slot ) {
				global $wpdb;
				$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"SELECT id FROM " . MZK_DB::sessions() . " WHERE class_type_id = %d AND session_date = %s AND start_time = %s", // phpcs:ignore WordPress.DB
						(int) $type->id,
						$date,
						$slot['time']
					)
				);
				if ( $exists ) {
					++$stats['skipped'];
					continue;
				}

				$result = self::save(
					array(
						'class_type_id'    => (int) $type->id,
						'title'            => $title,
						'session_date'     => $date,
						'start_time'       => $slot['time'],
						'duration_minutes' => $slot['duration'],
						'capacity'         => $slot['capacity'],
						'status'           => 'open',
					)
				);

				if ( is_wp_error( $result ) ) {
					continue;
				}
				++$stats['created'];
			}
		}

		return $stats;
	}

	/* ---------------------------------------------------------------------
	 * Recurring templates + schedule generation
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch weekly templates.
	 *
	 * @param bool $active_only Only active templates.
	 * @return object[]
	 */
	public static function templates( $active_only = false ) {
		global $wpdb;
		$table = MZK_DB::templates();
		$types = MZK_DB::class_types();
		$where = $active_only ? 'WHERE t.active = 1' : '';
		return $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT t.*, ct.name AS class_name, ct.colour AS class_colour
			 FROM {$table} t
			 LEFT JOIN {$types} ct ON ct.id = t.class_type_id
			 {$where}
			 ORDER BY t.weekday ASC, t.start_time ASC"
		);
	}

	/**
	 * Get one template.
	 *
	 * @param int $id Template id.
	 * @return object|null
	 */
	public static function get_template( $id ) {
		global $wpdb;
		$table = MZK_DB::templates();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Create or update a weekly template.
	 *
	 * @param array $data Template fields.
	 * @return int|WP_Error
	 */
	public static function save_template( $data ) {
		global $wpdb;
		$table = MZK_DB::templates();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$type = MZK_Class_Types::get( (int) ( $data['class_type_id'] ?? 0 ) );
		if ( ! $type ) {
			return new WP_Error( 'mzk_bad_class', __( 'Please choose a class.', 'mizuki-booking' ) );
		}
		$time = MZK_Utils::sanitize_time( $data['start_time'] ?? '' );
		if ( ! $time ) {
			return new WP_Error( 'mzk_bad_time', __( 'Please enter a valid start time.', 'mizuki-booking' ) );
		}

		$row = array(
			'class_type_id'    => (int) $type->id,
			'label'            => sanitize_text_field( $data['label'] ?? '' ),
			'weekday'          => max( 0, min( 6, (int) ( $data['weekday'] ?? 0 ) ) ),
			'start_time'       => $time,
			'duration_minutes' => max( 15, (int) ( $data['duration_minutes'] ?? $type->default_duration ) ),
			'capacity'         => max( 1, (int) ( $data['capacity'] ?? $type->default_capacity ) ),
			'valid_from'       => MZK_Utils::sanitize_date( $data['valid_from'] ?? '' ),
			'valid_until'      => MZK_Utils::sanitize_date( $data['valid_until'] ?? '' ),
			'active'           => empty( $data['active'] ) ? 0 : 1,
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}
		return $id;
	}

	/**
	 * Delete a template. Sessions already generated from it are kept.
	 *
	 * @param int $id Template id.
	 */
	public static function delete_template( $id ) {
		global $wpdb;
		$wpdb->delete( MZK_DB::templates(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Generate sessions from weekly templates across a date range.
	 * Existing sessions at the same class/date/time are never duplicated,
	 * and blacked-out dates are skipped.
	 *
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @param int    $template_id Limit to one template (0 = all active).
	 * @return array{created:int,skipped:int,blocked:int}
	 */
	public static function generate( $from, $to, $template_id = 0 ) {
		$from = MZK_Utils::sanitize_date( $from );
		$to   = MZK_Utils::sanitize_date( $to );
		$stats = array(
			'created' => 0,
			'skipped' => 0,
			'blocked' => 0,
			'error'   => '',
		);
		if ( ! $from || ! $to || $to < $from ) {
			return $stats;
		}

		$templates = array_filter(
			self::templates( true ),
			static function ( $t ) use ( $template_id ) {
				return ! $template_id || (int) $t->id === (int) $template_id;
			}
		);
		if ( ! $templates ) {
			return $stats;
		}

		$blackouts = MZK_Blackouts::map( $from, $to, null );

		global $wpdb;
		$table = MZK_DB::sessions();
		$now   = current_time( 'mysql' );

		$cursor = $from;
		while ( $cursor <= $to ) {
			$weekday = (int) gmdate( 'w', strtotime( $cursor . ' 12:00:00' ) );

			foreach ( $templates as $tpl ) {
				if ( (int) $tpl->weekday !== $weekday ) {
					continue;
				}
				if ( $tpl->valid_from && $cursor < $tpl->valid_from ) {
					continue;
				}
				if ( $tpl->valid_until && $cursor > $tpl->valid_until ) {
					continue;
				}
				if ( isset( $blackouts[ $cursor ] ) ) {
					++$stats['blocked'];
					continue;
				}

				$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE class_type_id = %d AND session_date = %s AND start_time = %s", // phpcs:ignore WordPress.DB
						(int) $tpl->class_type_id,
						$cursor,
						$tpl->start_time
					)
				);
				if ( $exists ) {
					++$stats['skipped'];
					continue;
				}

				$wpdb->insert( // phpcs:ignore WordPress.DB
					$table,
					array(
						'class_type_id'       => (int) $tpl->class_type_id,
						'template_id'         => (int) $tpl->id,
						'title'               => $tpl->label,
						'session_date'        => $cursor,
						'start_time'          => $tpl->start_time,
						'duration_minutes'    => (int) $tpl->duration_minutes,
						'capacity'            => (int) $tpl->capacity,
						'capacity_adjustment' => 0,
						'status'              => 'open',
						'created_at'          => $now,
						'updated_at'          => $now,
					)
				);
				if ( $wpdb->last_error ) {
					// Record why, instead of reporting a silent zero. This is what
					// turns "nothing shows up" into an answer.
					$stats['error'] = $wpdb->last_error;
					update_option(
						'mzk_last_generate',
						array(
							'when'  => current_time( 'mysql' ),
							'error' => $wpdb->last_error,
						),
						false
					);
					return $stats;
				}

				++$stats['created'];
			}

			$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}

		update_option(
			'mzk_last_generate',
			array(
				'when'    => current_time( 'mysql' ),
				'created' => $stats['created'],
				'skipped' => $stats['skipped'],
				'blocked' => $stats['blocked'],
				'range'   => $from . ' → ' . $to,
			),
			false
		);

		return $stats;
	}

	/**
	 * Keep the published schedule at least N months deep, so families can plan ahead.
	 * Runs daily from cron and on demand from the admin.
	 *
	 * @param int|null $months Months of horizon; defaults to the saved setting.
	 * @return array{created:int,skipped:int,blocked:int}
	 */
	public static function ensure_horizon( $months = null ) {
		$months = null === $months ? (int) MZK_Install::get_setting( 'months_ahead', 3 ) : (int) $months;
		$months = max( 2, $months );

		$today = MZK_Utils::today();
		$to    = gmdate( 'Y-m-d', strtotime( $today . " +{$months} months" ) );

		return self::generate( $today, $to, 0 );
	}

	/**
	 * Close every session inside a blackout range so students stop seeing them.
	 *
	 * @param string $start_date    Y-m-d.
	 * @param string $end_date      Y-m-d.
	 * @param int    $class_type_id 0 = all classes.
	 * @return int Number of sessions closed.
	 */
	public static function close_range( $start_date, $end_date, $class_type_id = 0 ) {
		global $wpdb;
		$table  = MZK_DB::sessions();
		$params = array( current_time( 'mysql' ), $start_date, $end_date );
		$extra  = '';
		if ( $class_type_id ) {
			$extra    = ' AND class_type_id = %d';
			$params[] = (int) $class_type_id;
		}
		return (int) $wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'closed', updated_at = %s WHERE session_date BETWEEN %s AND %s AND status = 'open'{$extra}", // phpcs:ignore WordPress.DB
				$params
			)
		);
	}
}
