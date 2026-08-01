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
				++$stats['created'];
			}

			$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}

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
