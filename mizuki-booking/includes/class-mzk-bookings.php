<?php
/**
 * Bookings: create, reschedule, cancel, and the rules around them.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Bookings {

	/**
	 * Query bookings joined to their session and class type.
	 *
	 * @param array $args id, session_id, email, user_id, status, statuses, class_type_id,
	 *                    enrollment_id, from, to, upcoming, search, limit, offset, orderby.
	 * @return object[]
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'id'            => 0,
				'session_id'    => 0,
				'order_id'      => 0,
				'email'         => '',
				'user_id'       => 0,
				'status'        => '',
				'statuses'      => array(),
				'class_type_id' => 0,
				'enrollment_id' => 0,
				'from'          => '',
				'to'            => '',
				'upcoming'      => false,
				'search'        => '',
				'limit'         => 0,
				'offset'        => 0,
				'orderby'       => 's.session_date ASC, s.start_time ASC',
			)
		);

		$bookings = MZK_DB::bookings();
		$sessions = MZK_DB::sessions();
		$types    = MZK_DB::class_types();

		$where  = array( '1=1' );
		$params = array();

		if ( $args['id'] ) {
			$where[]  = 'b.id = %d';
			$params[] = (int) $args['id'];
		}
		if ( $args['session_id'] ) {
			$where[]  = 'b.session_id = %d';
			$params[] = (int) $args['session_id'];
		}
		if ( $args['order_id'] ) {
			$where[]  = 'b.order_id = %d';
			$params[] = (int) $args['order_id'];
		}
		if ( $args['email'] ) {
			$where[]  = 'b.email = %s';
			$params[] = sanitize_email( $args['email'] );
		}
		if ( $args['user_id'] ) {
			$where[]  = 'b.user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( $args['status'] ) {
			$where[]  = 'b.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['statuses'] ) ) {
			$list    = (array) $args['statuses'];
			$in      = implode( ',', array_fill( 0, count( $list ), '%s' ) );
			$where[] = "b.status IN ({$in})";
			$params  = array_merge( $params, $list );
		}
		if ( $args['class_type_id'] ) {
			$where[]  = 'b.class_type_id = %d';
			$params[] = (int) $args['class_type_id'];
		}
		if ( $args['enrollment_id'] ) {
			$where[]  = 'b.enrollment_id = %d';
			$params[] = (int) $args['enrollment_id'];
		}
		if ( $args['from'] ) {
			$where[]  = 's.session_date >= %s';
			$params[] = $args['from'];
		}
		if ( $args['to'] ) {
			$where[]  = 's.session_date <= %s';
			$params[] = $args['to'];
		}
		if ( $args['upcoming'] ) {
			$where[]  = 's.session_date >= %s';
			$params[] = MZK_Utils::today();
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(b.student_name LIKE %s OR b.email LIKE %s OR b.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$limit_sql = '';
		if ( $args['limit'] ) {
			$limit_sql = 'LIMIT %d OFFSET %d';
			$params[]  = (int) $args['limit'];
			$params[]  = (int) $args['offset'];
		}

		$orderby = preg_replace( '/[^a-zA-Z0-9_,\. ]/', '', $args['orderby'] );

		$sql = "SELECT b.*, s.session_date, s.start_time, s.duration_minutes, s.status AS session_status,
					s.title AS session_title, ct.name AS class_name, ct.slug AS class_slug,
					ct.colour AS class_colour, ct.course_based, ct.reschedule_enabled,
					ct.reschedule_cutoff_hours, ct.cancel_enabled, ct.cancel_cutoff_hours,
					ct.max_reschedules
				FROM {$bookings} b
				LEFT JOIN {$sessions} s ON s.id = b.session_id
				LEFT JOIN {$types} ct ON ct.id = b.class_type_id
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY {$orderby} {$limit_sql}";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
		return array_map( array( __CLASS__, 'decorate' ), $rows ? $rows : array() );
	}

	/**
	 * Attach display + permission fields to a booking row.
	 *
	 * @param object $row Booking row joined to its session.
	 * @return object
	 */
	public static function decorate( $row ) {
		$row->id            = (int) $row->id;
		$row->session_id    = (int) $row->session_id;
		$row->enrollment_id = (int) $row->enrollment_id;
		$row->seats         = (int) $row->seats;

		$row->date_label     = $row->session_date ? MZK_Utils::format_date( $row->session_date ) : '';
		$row->time_label     = $row->session_date ? MZK_Utils::format_time_range( $row ) : '';
		$row->duration_label = MZK_Utils::format_duration( $row->duration_minutes );
		$row->status_label   = MZK_Utils::booking_statuses()[ $row->status ] ?? $row->status;

		$start        = $row->session_date ? MZK_Utils::session_start( $row ) : null;
		$row->is_past = $start ? $start->getTimestamp() < time() : false;

		$type    = (object) array(
			'name'                    => $row->class_name,
			'reschedule_enabled'      => $row->reschedule_enabled,
			'reschedule_cutoff_hours' => $row->reschedule_cutoff_hours,
			'cancel_enabled'          => $row->cancel_enabled,
			'cancel_cutoff_hours'     => $row->cancel_cutoff_hours,
			'max_reschedules'         => $row->max_reschedules,
		);
		$session = (object) array(
			'session_date'     => $row->session_date,
			'start_time'       => $row->start_time,
			'duration_minutes' => $row->duration_minutes,
		);

		$row->can_reschedule        = false;
		$row->can_cancel            = false;
		$row->reschedule_block_note = '';
		$row->cancel_block_note     = '';

		if ( 'confirmed' === $row->status && ! $row->is_past ) {
			$resched = MZK_Class_Types::can_reschedule( $type, $session, $row );
			if ( is_wp_error( $resched ) ) {
				$row->reschedule_block_note = $resched->get_error_message();
			} else {
				$row->can_reschedule = true;
			}

			$cancel = MZK_Class_Types::can_cancel( $type, $session );
			if ( is_wp_error( $cancel ) ) {
				$row->cancel_block_note = $cancel->get_error_message();
			} else {
				$row->can_cancel = true;
			}
		}

		$row->manage_url = MZK_Utils::manage_url( $row );

		return $row;
	}

	/**
	 * Fetch a single booking.
	 *
	 * @param int $id Booking id.
	 * @return object|null
	 */
	public static function get( $id ) {
		$rows = self::query( array( 'id' => (int) $id ) );
		return $rows ? $rows[0] : null;
	}

	/**
	 * Fetch a booking by its manage token (from the confirmation e-mail).
	 *
	 * @param int    $id    Booking id.
	 * @param string $token Manage token.
	 * @return object|null
	 */
	public static function get_by_token( $id, $token ) {
		$booking = self::get( $id );
		if ( ! $booking || '' === $booking->manage_token ) {
			return null;
		}
		return hash_equals( $booking->manage_token, (string) $token ) ? $booking : null;
	}

	/**
	 * Create a booking, holding a row lock on the session so two students
	 * cannot take the same last seat.
	 *
	 * @param array $data session_id, student_name, email, phone, seats, source,
	 *                    notes, user_id, skip_emails, allow_overbook.
	 * @return int|WP_Error Booking id.
	 */
	public static function create( $data ) {
		global $wpdb;

		$session_id = (int) ( $data['session_id'] ?? 0 );
		$session    = MZK_Sessions::get( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'mzk_no_session', __( 'That session no longer exists.', 'mizuki-booking' ) );
		}

		$name  = sanitize_text_field( $data['student_name'] ?? '' );
		$email = sanitize_email( $data['email'] ?? '' );
		$phone = sanitize_text_field( $data['phone'] ?? '' );
		$seats = max( 1, (int) ( $data['seats'] ?? 1 ) );
		$admin = ! empty( $data['allow_overbook'] );

		if ( '' === $name ) {
			return new WP_Error( 'mzk_bad_name', __( 'Please enter your name.', 'mizuki-booking' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'mzk_bad_email', __( 'Please enter a valid e-mail address.', 'mizuki-booking' ) );
		}
		if ( '' === $phone && MZK_Install::get_setting( 'require_phone' ) && ! $admin ) {
			return new WP_Error( 'mzk_bad_phone', __( 'Please enter a contact number.', 'mizuki-booking' ) );
		}

		if ( ! $admin ) {
			$gate = self::check_bookable( $session );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
		}

		$type = MZK_Class_Types::get( $session->class_type_id );

		// Paid classes are bought in the shop, not booked directly here, so the
		// seat and the payment can never disagree.
		if ( $type && ! $admin && 'paid' === MZK_Class_Types::payment_mode( $type ) ) {
			$where = MZK_Class_Types::purchase_url( $type );
			return new WP_Error(
				'mzk_purchase_required',
				sprintf(
					/* translators: %s: class name. */
					__( '%s is booked through our shop so you can pay for your place. Your seat is held while you check out.', 'mizuki-booking' ),
					$type->name
				),
				array(
					'enrolUrl'   => $where,
					'enrolLabel' => __( 'Book and pay', 'mizuki-booking' ),
				)
			);
		}

		// Duplicate guard: same e-mail, same session, still active.
		$existing = self::query(
			array(
				'session_id' => $session_id,
				'email'      => $email,
				'statuses'   => MZK_Utils::occupying_statuses(),
			)
		);
		if ( $existing && empty( $data['allow_duplicate'] ) ) {
			return new WP_Error( 'mzk_duplicate', __( 'You already have a booking for this session.', 'mizuki-booking' ) );
		}

		$status = isset( $data['status'] ) && array_key_exists( $data['status'], MZK_Utils::booking_statuses() )
			? $data['status']
			: 'confirmed';

		// Classes set to "approve registrations" park the booking until the studio
		// says yes. The seat is still held, so it cannot be taken meanwhile.
		if ( 'confirmed' === $status && ! $admin && class_exists( 'MZK_Students' )
			&& MZK_Students::needs_approval( (int) $session->class_type_id ) ) {
			$status = 'awaiting_approval';
		}

		// A seat held for an unpaid order does not spend a package session yet;
		// MZK_Bookings::attach_enrollment() runs once payment confirms it.
		$is_hold = 'pending' === $status;

		// Course students spend a session from their package.
		$enrollment    = null;
		$enrollment_id = isset( $data['enrollment_id'] ) ? (int) $data['enrollment_id'] : 0;
		if ( $type && ! empty( $type->course_based ) && ! $is_hold ) {
			$enrollment = $enrollment_id
				? MZK_Enrollments::get( $enrollment_id )
				: MZK_Enrollments::find_usable( $email, $session->class_type_id, (int) ( $data['user_id'] ?? 0 ) );

			if ( ! $enrollment && ! empty( $type->requires_enrollment ) && ! $admin ) {
				// Never leave the student at a dead end: point them at the course.
				$where = MZK_Class_Types::purchase_url( $type );

				return new WP_Error(
					'mzk_no_enrollment',
					$where
						? sprintf(
							/* translators: %s: class name. */
							__( '%s is a course — you enrol once, then book your sessions on the dates that suit you. We could not find a course under this e-mail address yet.', 'mizuki-booking' ),
							$type->name
						)
						: sprintf(
							/* translators: %s: class name. */
							__( 'We could not find an active %s course under this e-mail address. Please contact the studio so we can set it up for you.', 'mizuki-booking' ),
							$type->name
						),
					array(
						'enrolUrl'   => $where,
						'enrolLabel' => __( 'See the course', 'mizuki-booking' ),
					)
				);
			}
			if ( $enrollment && ! $enrollment->is_usable && ! $admin ) {
				$reason = $enrollment->is_expired
					? __( 'your course package has expired', 'mizuki-booking' )
					: __( 'you have used all the sessions in your package', 'mizuki-booking' );
				return new WP_Error(
					'mzk_enrollment_exhausted',
					sprintf(
						/* translators: %s: reason the package cannot be used. */
						__( 'This booking could not be completed because %s. Please contact the studio — we can extend it for you.', 'mizuki-booking' ),
						$reason
					)
				);
			}
			if ( $enrollment && $enrollment->sessions_left < $seats && ! $admin ) {
				return new WP_Error(
					'mzk_enrollment_balance',
					sprintf(
						/* translators: %d: sessions remaining. */
						__( 'Your package has %d session(s) left, which is not enough for this booking.', 'mizuki-booking' ),
						$enrollment->sessions_left
					)
				);
			}
			$enrollment_id = $enrollment ? (int) $enrollment->id : 0;
		} else {
			$enrollment_id = 0;
		}

		// Give every student an account so their classes, packages and details all
		// live in one place. Falls back to an e-mail-only booking if that fails.
		//
		// Never for a seat merely held against an unpaid order: an abandoned
		// checkout would leave an orphan account behind and, worse, send a
		// "welcome, set your password" e-mail to someone who never paid. Woo
		// creates the account when the payment confirms instead.
		$user_id = (int) ( $data['user_id'] ?? 0 );
		if ( ! $user_id && ! $is_hold && class_exists( 'MZK_Students' ) && empty( $data['no_account'] ) ) {
			$user_id = MZK_Students::ensure_account( $email, $name, $phone );
		}
		if ( ! $user_id ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = (int) $user->ID;
			}
		}

		$table = MZK_DB::bookings();
		$now   = current_time( 'mysql' );
		$row   = array(
			'session_id'       => $session_id,
			'class_type_id'    => (int) $session->class_type_id,
			'enrollment_id'    => $enrollment_id,
			'user_id'          => $user_id,
			'student_name'     => $name,
			'email'            => $email,
			'phone'            => $phone,
			'seats'            => $seats,
			'status'           => $status,
			'source'           => in_array( $data['source'] ?? 'web', array( 'web', 'admin', 'chat', 'phone' ), true ) ? $data['source'] : 'web',
			'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
			'manage_token'     => MZK_Utils::generate_token(),
			'reschedule_count' => 0,
			'rescheduled_from' => (int) ( $data['rescheduled_from'] ?? 0 ),
			'order_id'         => (int) ( $data['order_id'] ?? 0 ),
			'order_item_id'    => (int) ( $data['order_item_id'] ?? 0 ),
			'product_id'       => (int) ( $data['product_id'] ?? 0 ),
			'hold_expires_at'  => ! empty( $data['hold_expires_at'] ) ? $data['hold_expires_at'] : null,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		// Serialise both scarce resources against concurrent bookings: the seats on
		// the session, and the sessions left in the student's package. Locks are
		// always taken package-first, then session, so two requests can never grab
		// them in opposite orders and deadlock.
		$enrollment_lock = $enrollment_id ? self::lock_enrollment( $enrollment_id ) : null;
		$lock            = self::lock_session( $session_id );

		$taken     = MZK_Sessions::seats_taken( $session_id );
		$effective = max( 0, (int) $session->capacity + (int) $session->capacity_adjustment );
		if ( ! $admin && ( $taken + $seats ) > $effective ) {
			self::release_lock( $lock );
			self::release_lock( $enrollment_lock );
			return new WP_Error( 'mzk_full', __( 'Sorry — this session just filled up. Please choose another date.', 'mizuki-booking' ) );
		}

		// Re-read the balance under the lock. Without this, two bookings made at
		// the same moment could each see the last session and both spend it.
		if ( $enrollment_id && ! $admin ) {
			$fresh = MZK_Enrollments::get( $enrollment_id );
			if ( ! $fresh || $fresh->sessions_left < $seats ) {
				self::release_lock( $lock );
				self::release_lock( $enrollment_lock );
				return new WP_Error(
					'mzk_enrollment_balance',
					__( 'Your course package does not have enough sessions left for this booking. Please contact the studio — we can extend it for you.', 'mizuki-booking' )
				);
			}
		}

		$inserted = $wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
		$id       = $inserted ? (int) $wpdb->insert_id : 0;

		self::release_lock( $lock );
		self::release_lock( $enrollment_lock );

		if ( ! $id ) {
			return new WP_Error( 'mzk_insert_failed', __( 'The booking could not be saved. Please try again.', 'mizuki-booking' ) );
		}

		/**
		 * Fires after a booking is created.
		 *
		 * @param int   $id  Booking id.
		 * @param array $row Stored values.
		 */
		do_action( 'mzk_booking_created', $id, $row );

		if ( empty( $data['skip_emails'] ) ) {
			if ( 'awaiting_approval' === $status ) {
				// "We have your request" now; the confirmation follows on approval.
				MZK_Students::send_decision( $id, 'pending' );
				MZK_Students::notify_admin_pending( $id );
			} else {
				MZK_Mailer::send_confirmation( $id );
				if ( MZK_Install::get_setting( 'notify_admin' ) ) {
					MZK_Mailer::notify_admin_new_booking( $id );
				}
			}
		}

		return $id;
	}

	/**
	 * Link a confirmed booking to the student's course package, if the class is
	 * course-based and they have one. Used after an order is paid, when the seat
	 * moves from a hold to a real booking.
	 *
	 * @param int $booking_id Booking id.
	 * @return int Enrollment id, or 0 when none applies.
	 */
	public static function attach_enrollment( $booking_id ) {
		$booking = self::get( $booking_id );
		if ( ! $booking || $booking->enrollment_id ) {
			return (int) ( $booking->enrollment_id ?? 0 );
		}

		$type = MZK_Class_Types::get( $booking->class_type_id );
		if ( ! $type || empty( $type->course_based ) ) {
			return 0;
		}

		$enrollment = MZK_Enrollments::find_usable( $booking->email, (int) $booking->class_type_id, (int) $booking->user_id );
		if ( ! $enrollment ) {
			return 0;
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'enrollment_id' => (int) $enrollment->id,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking_id )
		);

		return (int) $enrollment->id;
	}

	/**
	 * Student-facing bookability check for a session.
	 *
	 * @param object $session Decorated session row.
	 * @return true|WP_Error
	 */
	public static function check_bookable( $session ) {
		if ( 'cancelled' === $session->status ) {
			return new WP_Error( 'mzk_session_cancelled', __( 'This session has been cancelled.', 'mizuki-booking' ) );
		}
		if ( 'open' !== $session->status ) {
			return new WP_Error( 'mzk_session_closed', __( 'This session is not open for booking.', 'mizuki-booking' ) );
		}
		if ( $session->is_blacked_out ) {
			return new WP_Error( 'mzk_blackout', __( 'The studio is closed on this date.', 'mizuki-booking' ) );
		}
		if ( $session->is_past ) {
			return new WP_Error( 'mzk_past', __( 'This session has already started.', 'mizuki-booking' ) );
		}
		if ( $session->is_full ) {
			return new WP_Error( 'mzk_full', __( 'This session is fully booked.', 'mizuki-booking' ) );
		}
		return true;
	}

	/**
	 * Move a booking to another session, enforcing the class type's cutoff.
	 *
	 * @param int   $booking_id     Booking id.
	 * @param int   $new_session_id Target session id.
	 * @param array $opts           by_admin, skip_emails.
	 * @return true|WP_Error
	 */
	public static function reschedule( $booking_id, $new_session_id, $opts = array() ) {
		global $wpdb;

		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Booking not found.', 'mizuki-booking' ) );
		}
		if ( 'confirmed' !== $booking->status ) {
			return new WP_Error( 'mzk_not_active', __( 'Only confirmed bookings can be rescheduled.', 'mizuki-booking' ) );
		}

		$by_admin = ! empty( $opts['by_admin'] );
		$new      = MZK_Sessions::get( $new_session_id );
		if ( ! $new ) {
			return new WP_Error( 'mzk_no_session', __( 'The new session no longer exists.', 'mizuki-booking' ) );
		}
		if ( (int) $new->id === (int) $booking->session_id ) {
			return new WP_Error( 'mzk_same_session', __( 'That is the session you are already booked on.', 'mizuki-booking' ) );
		}
		if ( (int) $new->class_type_id !== (int) $booking->class_type_id ) {
			return new WP_Error( 'mzk_different_class', __( 'Bookings can only be moved to another session of the same class.', 'mizuki-booking' ) );
		}

		if ( ! $by_admin ) {
			$type    = MZK_Class_Types::get( $booking->class_type_id );
			$current = (object) array(
				'session_date'     => $booking->session_date,
				'start_time'       => $booking->start_time,
				'duration_minutes' => $booking->duration_minutes,
			);

			$allowed = MZK_Class_Types::can_reschedule( $type, $current, $booking );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}

			// The target session must also sit outside the cutoff window.
			$target_start = MZK_Utils::session_start( $new );
			if ( $target_start && MZK_Utils::hours_until( $target_start ) < (float) $type->reschedule_cutoff_hours ) {
				return new WP_Error(
					'mzk_target_too_close',
					sprintf(
						/* translators: %s: cutoff description. */
						__( 'Please choose a session that starts more than %s from now.', 'mizuki-booking' ),
						MZK_Class_Types::describe_cutoff( (float) $type->reschedule_cutoff_hours )
					)
				);
			}

			$gate = self::check_bookable( $new );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
		}

		$old_session = (object) array(
			'session_date'     => $booking->session_date,
			'start_time'       => $booking->start_time,
			'duration_minutes' => $booking->duration_minutes,
		);

		$lock      = self::lock_session( (int) $new->id );
		$taken     = MZK_Sessions::seats_taken( (int) $new->id );
		$effective = max( 0, (int) $new->capacity + (int) $new->capacity_adjustment );
		if ( ! $by_admin && ( $taken + (int) $booking->seats ) > $effective ) {
			self::release_lock( $lock );
			return new WP_Error( 'mzk_full', __( 'Sorry — that session just filled up. Please choose another date.', 'mizuki-booking' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'session_id'       => (int) $new->id,
				'reschedule_count' => (int) $booking->reschedule_count + 1,
				'rescheduled_from' => (int) $booking->session_id,
				'reminder_sent_at' => null,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking->id )
		);

		self::release_lock( $lock );

		/**
		 * Fires after a booking is moved to another session.
		 *
		 * @param int    $booking_id  Booking id.
		 * @param int    $new_session New session id.
		 * @param object $old_session Previous session data.
		 */
		do_action( 'mzk_booking_rescheduled', (int) $booking->id, (int) $new->id, $old_session );

		if ( empty( $opts['skip_emails'] ) ) {
			MZK_Mailer::send_reschedule( (int) $booking->id, $old_session );
		}

		return true;
	}

	/**
	 * Cancel a booking, freeing its seat.
	 *
	 * @param int   $booking_id Booking id.
	 * @param array $opts       by_admin, skip_emails, reason.
	 * @return true|WP_Error
	 */
	public static function cancel( $booking_id, $opts = array() ) {
		global $wpdb;

		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Booking not found.', 'mizuki-booking' ) );
		}
		if ( 'cancelled' === $booking->status ) {
			return true;
		}

		if ( empty( $opts['by_admin'] ) ) {
			$type    = MZK_Class_Types::get( $booking->class_type_id );
			$session = (object) array(
				'session_date'     => $booking->session_date,
				'start_time'       => $booking->start_time,
				'duration_minutes' => $booking->duration_minutes,
			);
			$allowed = MZK_Class_Types::can_cancel( $type, $session );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		}

		$notes = trim( (string) $booking->notes );
		if ( ! empty( $opts['reason'] ) ) {
			$notes = trim( $notes . "\n" . sanitize_textarea_field( $opts['reason'] ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'status'     => 'cancelled',
				'notes'      => $notes,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking->id )
		);

		/** Fires after a booking is cancelled. */
		do_action( 'mzk_booking_cancelled', (int) $booking->id );

		if ( empty( $opts['skip_emails'] ) ) {
			MZK_Mailer::send_cancellation( (int) $booking->id );
		}

		return true;
	}

	/**
	 * Change a booking's status directly (admin: attended / no-show / restore).
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $status     New status.
	 * @return true|WP_Error
	 */
	public static function set_status( $booking_id, $status ) {
		if ( ! array_key_exists( $status, MZK_Utils::booking_statuses() ) ) {
			return new WP_Error( 'mzk_bad_status', __( 'Unknown booking status.', 'mizuki-booking' ) );
		}

		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Booking not found.', 'mizuki-booking' ) );
		}

		// Re-activating must not push the session past its limit.
		if ( in_array( $status, MZK_Utils::occupying_statuses(), true )
			&& ! in_array( $booking->status, MZK_Utils::occupying_statuses(), true ) ) {
			$session = MZK_Sessions::get( $booking->session_id );
			if ( $session && ( $session->seats_taken + $booking->seats ) > $session->effective_capacity ) {
				return new WP_Error( 'mzk_full', __( 'That session is full — raise its participant limit first.', 'mizuki-booking' ) );
			}
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking_id )
		);
		return true;
	}

	/**
	 * Permanently delete a booking row.
	 *
	 * @param int $booking_id Booking id.
	 */
	public static function delete( $booking_id ) {
		global $wpdb;
		$wpdb->delete( MZK_DB::bookings(), array( 'id' => (int) $booking_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Bookings for a student, identified by e-mail and/or WP user.
	 *
	 * @param string $email   E-mail address.
	 * @param int    $user_id WP user id.
	 * @param bool   $upcoming_only Restrict to today onwards.
	 * @return object[]
	 */
	public static function for_student( $email, $user_id = 0, $upcoming_only = false ) {
		$args = array(
			'orderby'  => 's.session_date ASC, s.start_time ASC',
			'upcoming' => $upcoming_only,
		);
		if ( $email ) {
			$args['email'] = $email;
		} elseif ( $user_id ) {
			$args['user_id'] = $user_id;
		} else {
			return array();
		}
		return self::query( $args );
	}

	/**
	 * Take a MySQL advisory lock around a session's seat count.
	 *
	 * @param int $session_id Session id.
	 * @return string|null Lock name, or null when locking is unavailable.
	 */
	private static function lock_session( $session_id ) {
		return self::acquire_lock( 'session_' . (int) $session_id );
	}

	/**
	 * Take an advisory lock around a course package's remaining balance.
	 *
	 * @param int $enrollment_id Enrollment id.
	 * @return string|null Lock name, or null when locking is unavailable.
	 */
	private static function lock_enrollment( $enrollment_id ) {
		return self::acquire_lock( 'enrollment_' . (int) $enrollment_id );
	}

	/**
	 * Take a named MySQL advisory lock, scoped to this database and prefix so two
	 * sites sharing a server cannot block each other.
	 *
	 * @param string $key Lock key.
	 * @return string|null Lock name, or null when locking is unavailable.
	 */
	private static function acquire_lock( $key ) {
		global $wpdb;
		$name = 'mzk_' . $key . '_' . substr( md5( DB_NAME . $wpdb->prefix ), 0, 8 );
		$got  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) ); // phpcs:ignore WordPress.DB
		return '1' === (string) $got ? $name : null;
	}

	/**
	 * Release an advisory lock.
	 *
	 * @param string|null $name Lock name.
	 */
	private static function release_lock( $name ) {
		if ( ! $name ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB
	}
}
