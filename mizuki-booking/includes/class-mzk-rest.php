<?php
/**
 * REST endpoints powering the front-end calendar and student self-service.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Rest {

	const NS = 'mizuki/v1';

	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Route table.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'from'       => array( 'type' => 'string' ),
					'to'         => array( 'type' => 'string' ),
					'class_type' => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_booking' ),
				'permission_callback' => array( __CLASS__, 'public_write_permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_booking' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/reschedule',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reschedule_booking' ),
				'permission_callback' => array( __CLASS__, 'public_write_permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'cancel_booking' ),
				'permission_callback' => array( __CLASS__, 'public_write_permission' ),
			)
		);

		register_rest_route(
			self::NS,
			'/my-bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'my_bookings' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public write guard: valid REST nonce plus a light per-IP throttle.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function public_write_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'mzk_bad_nonce',
				__( 'Your session expired. Please refresh the page and try again.', 'mizuki-booking' ),
				array( 'status' => 403 )
			);
		}

		if ( self::is_throttled() ) {
			return new WP_Error(
				'mzk_throttled',
				__( 'Too many attempts. Please wait a minute and try again.', 'mizuki-booking' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Simple per-IP throttle: 10 write requests per minute.
	 *
	 * @return bool
	 */
	private static function is_throttled() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'mzk_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 10 ) {
			return true;
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Calendar payload: class types, sessions grouped by date, blackout dates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_calendar( $request ) {
		// Expiring stale seat holds here keeps availability honest between cron
		// runs. Throttled, so a burst of visitors causes one sweep, not one each.
		if ( class_exists( 'MZK_Woo' ) && MZK_Woo::active() ) {
			MZK_Woo::expire_holds( true );
		}

		$today  = MZK_Utils::today();
		$months = max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) );

		$from = MZK_Utils::sanitize_date( $request->get_param( 'from' ) );
		$to   = MZK_Utils::sanitize_date( $request->get_param( 'to' ) );
		if ( ! $from || $from < $today ) {
			$from = $today;
		}
		if ( ! $to ) {
			$to = gmdate( 'Y-m-t', strtotime( $today . " +{$months} months" ) );
		}

		$type    = MZK_Class_Types::resolve( $request->get_param( 'class_type' ) );
		$type_id = $type ? (int) $type->id : 0;

		$sessions = MZK_Sessions::query(
			array(
				'from'          => $from,
				'to'            => $to,
				'class_type_id' => $type_id,
				'status'        => 'open',
			)
		);

		$days = array();
		foreach ( $sessions as $session ) {
			if ( $session->is_past || $session->is_blacked_out ) {
				continue;
			}
			$days[ $session->session_date ][] = self::session_payload( $session );
		}

		$classes = array();
		foreach ( MZK_Class_Types::all( true ) as $ct ) {
			$classes[] = array(
				'id'                 => (int) $ct->id,
				'slug'               => $ct->slug,
				'name'               => $ct->name,
				'colour'             => $ct->colour,
				'courseBased'        => (bool) $ct->course_based,
				'requiresEnrollment' => (bool) $ct->requires_enrollment,
				'rescheduleEnabled'  => (bool) $ct->reschedule_enabled,
				'rescheduleCutoff'   => MZK_Class_Types::describe_cutoff( (float) $ct->reschedule_cutoff_hours ),
				'description'        => wp_kses_post( $ct->description ),
			);
		}

		return rest_ensure_response(
			array(
				'from'      => $from,
				'to'        => $to,
				'today'     => $today,
				'classes'   => $classes,
				'days'      => $days,
				'blackouts' => MZK_Blackouts::map( $from, $to, $type_id ? $type_id : null ),
				'requirePhone' => (bool) MZK_Install::get_setting( 'require_phone' ),
			)
		);
	}

	/**
	 * Public shape of a session.
	 *
	 * @param object $session Decorated session.
	 * @return array
	 */
	public static function session_payload( $session ) {
		return array(
			'id'         => (int) $session->id,
			'classId'    => (int) $session->class_type_id,
			'className'  => $session->class_name,
			'classSlug'  => $session->class_slug,
			'colour'     => $session->class_colour,
			'title'      => $session->title ? $session->title : $session->class_name,
			'date'       => $session->session_date,
			'dateLabel'  => $session->date_label,
			'timeLabel'  => $session->time_label,
			'duration'   => (int) $session->duration_minutes,
			'durationLabel' => $session->duration_label,
			'seatsLeft'  => (int) $session->seats_available,
			'capacity'   => (int) $session->effective_capacity,
			'isFull'     => (bool) $session->is_full,
			'bookable'   => (bool) $session->is_bookable,
			'courseBased' => (bool) $session->course_based,
		);
	}

	/**
	 * Create a booking from the front-end form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_booking( $request ) {
		// Honeypot: real students never fill this field.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_Error( 'mzk_spam', __( 'Your booking could not be processed.', 'mizuki-booking' ), array( 'status' => 400 ) );
		}

		$id = MZK_Bookings::create(
			array(
				'session_id'   => (int) $request->get_param( 'session_id' ),
				'student_name' => $request->get_param( 'student_name' ),
				'email'        => $request->get_param( 'email' ),
				'phone'        => $request->get_param( 'phone' ),
				'notes'        => $request->get_param( 'notes' ),
				'seats'        => 1,
				'source'       => 'web',
				'user_id'      => get_current_user_id(),
			)
		);

		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $id;
		}

		$booking = MZK_Bookings::get( $id );

		return rest_ensure_response(
			array(
				'success'   => true,
				'bookingId' => (int) $id,
				'message'   => __( 'Your booking is confirmed. A confirmation e-mail is on its way.', 'mizuki-booking' ),
				'booking'   => self::booking_payload( $booking ),
				'manageUrl' => $booking->manage_url,
			)
		);
	}

	/**
	 * Public shape of a booking.
	 *
	 * @param object $booking Decorated booking.
	 * @return array
	 */
	public static function booking_payload( $booking ) {
		return array(
			'id'            => (int) $booking->id,
			'sessionId'     => (int) $booking->session_id,
			'className'     => $booking->class_name,
			'classSlug'     => $booking->class_slug,
			'title'         => $booking->session_title ? $booking->session_title : $booking->class_name,
			'date'          => $booking->session_date,
			'dateLabel'     => $booking->date_label,
			'timeLabel'     => $booking->time_label,
			'durationLabel' => $booking->duration_label,
			'status'        => $booking->status,
			'statusLabel'   => $booking->status_label,
			'studentName'   => $booking->student_name,
			'canReschedule' => (bool) $booking->can_reschedule,
			'canCancel'     => (bool) $booking->can_cancel,
			'rescheduleNote' => $booking->reschedule_block_note,
			'cancelNote'    => $booking->cancel_block_note,
			'isPast'        => (bool) $booking->is_past,
		);
	}

	/**
	 * Authorise a request against a booking: owner (logged in) or manage token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return object|WP_Error Booking row.
	 */
	private static function authorise_booking( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$booking = MZK_Bookings::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Booking not found.', 'mizuki-booking' ), array( 'status' => 404 ) );
		}

		$token = (string) $request->get_param( 'token' );
		if ( '' !== $token && hash_equals( $booking->manage_token, $token ) ) {
			return $booking;
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user = wp_get_current_user();
			if ( (int) $booking->user_id === $user_id
				|| ( $user && strtolower( $user->user_email ) === strtolower( $booking->email ) )
				|| current_user_can( MZK_Utils::cap() ) ) {
				return $booking;
			}
		}

		return new WP_Error(
			'mzk_forbidden',
			__( 'This booking link is not valid. Please use the link in your confirmation e-mail.', 'mizuki-booking' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Read one booking plus the sessions it may move to.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_booking( $request ) {
		$booking = self::authorise_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		return rest_ensure_response(
			array(
				'booking'    => self::booking_payload( $booking ),
				'alternates' => self::alternates( $booking ),
			)
		);
	}

	/**
	 * Sessions of the same class a booking could move to.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public static function alternates( $booking ) {
		if ( ! $booking->can_reschedule ) {
			return array();
		}

		$type   = MZK_Class_Types::get( $booking->class_type_id );
		$cutoff = $type ? (float) $type->reschedule_cutoff_hours : 0;
		$months = max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) );

		$sessions = MZK_Sessions::query(
			array(
				'from'          => MZK_Utils::today(),
				'to'            => gmdate( 'Y-m-t', strtotime( MZK_Utils::today() . " +{$months} months" ) ),
				'class_type_id' => (int) $booking->class_type_id,
				'status'        => 'open',
				'only_bookable' => true,
			)
		);

		$out = array();
		foreach ( $sessions as $session ) {
			if ( (int) $session->id === (int) $booking->session_id ) {
				continue;
			}
			$start = MZK_Utils::session_start( $session );
			if ( $start && MZK_Utils::hours_until( $start ) < $cutoff ) {
				continue;
			}
			$out[] = self::session_payload( $session );
		}
		return $out;
	}

	/**
	 * Move a booking to another session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reschedule_booking( $request ) {
		$booking = self::authorise_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$result = MZK_Bookings::reschedule( (int) $booking->id, (int) $request->get_param( 'session_id' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		$fresh = MZK_Bookings::get( $booking->id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Your booking has been moved. We have e-mailed you the new details.', 'mizuki-booking' ),
				'booking' => self::booking_payload( $fresh ),
			)
		);
	}

	/**
	 * Cancel a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cancel_booking( $request ) {
		$booking = self::authorise_booking( $request );
		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$result = MZK_Bookings::cancel( (int) $booking->id, array( 'reason' => $request->get_param( 'reason' ) ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Your booking has been cancelled.', 'mizuki-booking' ),
				'booking' => self::booking_payload( MZK_Bookings::get( $booking->id ) ),
			)
		);
	}

	/**
	 * Bookings for the logged-in student.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function my_bookings() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'mzk_not_logged_in', __( 'Please log in to see your bookings.', 'mizuki-booking' ), array( 'status' => 401 ) );
		}
		$user     = wp_get_current_user();
		$bookings = MZK_Bookings::for_student( $user->user_email, (int) $user->ID );

		return rest_ensure_response(
			array( 'bookings' => array_map( array( __CLASS__, 'booking_payload' ), $bookings ) )
		);
	}
}
