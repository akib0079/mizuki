<?php
/**
 * Shared helpers: timezone-aware dates, formatting, capability checks.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Utils {

	/**
	 * Capability required for all studio management screens.
	 *
	 * @return string
	 */
	public static function cap() {
		return apply_filters( 'mzk_manage_capability', 'manage_options' );
	}

	/**
	 * Bail unless the current user may manage bookings.
	 */
	public static function require_cap() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage bookings.', 'mizuki-booking' ) );
		}
	}

	/**
	 * Site timezone object.
	 *
	 * @return DateTimeZone
	 */
	public static function tz() {
		return wp_timezone();
	}

	/**
	 * "Now" in site timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return new DateTimeImmutable( 'now', self::tz() );
	}

	/**
	 * Today's date in site timezone, Y-m-d.
	 *
	 * @return string
	 */
	public static function today() {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Build a DateTimeImmutable from a date + time pair in site timezone.
	 *
	 * @param string $date Y-m-d.
	 * @param string $time H:i(:s).
	 * @return DateTimeImmutable|null
	 */
	public static function make_datetime( $date, $time = '00:00:00' ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );
		if ( '' === $date ) {
			return null;
		}
		if ( 5 === strlen( $time ) ) {
			$time .= ':00';
		}
		if ( '' === $time ) {
			$time = '00:00:00';
		}
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $time, self::tz() );
		return $dt ?: null;
	}

	/**
	 * Session start as DateTimeImmutable.
	 *
	 * @param object|array $session Session row.
	 * @return DateTimeImmutable|null
	 */
	public static function session_start( $session ) {
		$session = (object) $session;
		return self::make_datetime( $session->session_date, $session->start_time );
	}

	/**
	 * Session end as DateTimeImmutable.
	 *
	 * @param object|array $session Session row.
	 * @return DateTimeImmutable|null
	 */
	public static function session_end( $session ) {
		$session = (object) $session;
		$start   = self::session_start( $session );
		if ( ! $start ) {
			return null;
		}
		return $start->modify( '+' . (int) $session->duration_minutes . ' minutes' );
	}

	/**
	 * Hours from now until a datetime. Negative if in the past.
	 *
	 * @param DateTimeInterface $dt Target.
	 * @return float
	 */
	public static function hours_until( DateTimeInterface $dt ) {
		return ( $dt->getTimestamp() - self::now()->getTimestamp() ) / HOUR_IN_SECONDS;
	}

	/**
	 * Localised date string.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public static function format_date( $date ) {
		$dt = self::make_datetime( $date );
		return $dt ? wp_date( get_option( 'date_format' ), $dt->getTimestamp() ) : '';
	}

	/**
	 * Localised time range, e.g. "10:00 am - 12:00 pm".
	 *
	 * @param object|array $session Session row.
	 * @return string
	 */
	public static function format_time_range( $session ) {
		$start = self::session_start( $session );
		$end   = self::session_end( $session );
		if ( ! $start || ! $end ) {
			return '';
		}
		$fmt = get_option( 'time_format' );
		return wp_date( $fmt, $start->getTimestamp() ) . ' – ' . wp_date( $fmt, $end->getTimestamp() );
	}

	/**
	 * Human duration, e.g. "2 hrs", "2 hrs 30 min".
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function format_duration( $minutes ) {
		$minutes = (int) $minutes;
		$hours   = intdiv( $minutes, 60 );
		$rest    = $minutes % 60;
		$parts   = array();
		if ( $hours ) {
			/* translators: %d: number of hours. */
			$parts[] = sprintf( _n( '%d hr', '%d hrs', $hours, 'mizuki-booking' ), $hours );
		}
		if ( $rest ) {
			/* translators: %d: number of minutes. */
			$parts[] = sprintf( __( '%d min', 'mizuki-booking' ), $rest );
		}
		return $parts ? implode( ' ', $parts ) : __( '0 min', 'mizuki-booking' );
	}

	/**
	 * Validate a Y-m-d string, returning null when malformed.
	 *
	 * @param string $date Candidate.
	 * @return string|null
	 */
	public static function sanitize_date( $date ) {
		$date = trim( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}
		$parts = explode( '-', $date );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return null;
		}
		return $date;
	}

	/**
	 * Normalise a time string to H:i:s, returning null when malformed.
	 *
	 * @param string $time Candidate.
	 * @return string|null
	 */
	public static function sanitize_time( $time ) {
		$time = trim( (string) $time );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m ) ) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		$s = isset( $m[3] ) ? (int) $m[3] : 0;
		if ( $h > 23 || $i > 59 || $s > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d:%02d', $h, $i, $s );
	}

	/**
	 * Random token for e-mail manage links.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return wp_generate_password( 40, false, false );
	}

	/**
	 * Weekday labels indexed 0 (Sunday) - 6 (Saturday).
	 *
	 * @return array<int,string>
	 */
	public static function weekdays() {
		global $wp_locale;
		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$days[ $i ] = $wp_locale ? $wp_locale->get_weekday( $i ) : gmdate( 'l', strtotime( "Sunday +{$i} day" ) );
		}
		return $days;
	}

	/**
	 * Booking statuses that occupy a seat.
	 *
	 * 'pending' holds a seat while an order is awaiting payment, so two students
	 * cannot check out into the same place. Stale holds are released by cron.
	 *
	 * @return string[]
	 */
	public static function occupying_statuses() {
		return array( 'confirmed', 'attended', 'pending', 'awaiting_approval' );
	}

	/**
	 * Human labels for booking statuses.
	 *
	 * @return array<string,string>
	 */
	public static function booking_statuses() {
		return array(
			'awaiting_approval' => __( 'Awaiting approval', 'mizuki-booking' ),
			'declined'  => __( 'Declined', 'mizuki-booking' ),
			'pending'   => __( 'Awaiting payment', 'mizuki-booking' ),
			'confirmed' => __( 'Confirmed', 'mizuki-booking' ),
			'attended'  => __( 'Attended', 'mizuki-booking' ),
			'cancelled' => __( 'Cancelled', 'mizuki-booking' ),
			'no_show'   => __( 'No show', 'mizuki-booking' ),
			'moved'     => __( 'Rescheduled away', 'mizuki-booking' ),
			'expired'   => __( 'Expired (unpaid)', 'mizuki-booking' ),
		);
	}

	/**
	 * Human labels for session statuses.
	 *
	 * @return array<string,string>
	 */
	public static function session_statuses() {
		return array(
			'open'      => __( 'Open', 'mizuki-booking' ),
			'closed'    => __( 'Closed (hidden from students)', 'mizuki-booking' ),
			'cancelled' => __( 'Cancelled', 'mizuki-booking' ),
		);
	}

	/**
	 * Front-end URL where students manage a booking.
	 *
	 * @param object|array $booking Booking row.
	 * @return string
	 */
	public static function manage_url( $booking ) {
		$booking = (object) $booking;
		$page_id = (int) MZK_Install::get_setting( 'manage_page_id' );
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		return add_query_arg(
			array(
				'mzk_booking' => (int) $booking->id,
				'mzk_token'   => rawurlencode( $booking->manage_token ),
			),
			$base
		);
	}
}
