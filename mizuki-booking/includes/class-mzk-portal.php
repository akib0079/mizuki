<?php
/**
 * Course student portal — the IFDA / Preserved Flower side of the site.
 *
 * These students paid for their course up front, so their booking journey has
 * no shop and no checkout in it. They sign in, see how many sessions they have
 * left and how long they have to use them, pick a date, and it is confirmed on
 * the spot with one session deducted.
 *
 * [mizuki_course_portal]              — every course the student holds
 * [mizuki_course_portal course="ifda"] — one course only
 *
 * Everything it shows comes from the same tables as the public calendar, so the
 * studio manages both from one admin area.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Portal {

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( 'mizuki_course_portal', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [mizuki_course_portal]
	 *
	 * @param array $atts course.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'course' => '' ), $atts, 'mizuki_course_portal' );

		MZK_Shortcodes::ensure_assets();

		$course = $atts['course'] ? MZK_Class_Types::resolve( $atts['course'] ) : null;

		ob_start();

		if ( ! is_user_logged_in() ) {
			self::render_signed_out( $course );
		} else {
			self::render_portal( $course );
		}

		return ob_get_clean();
	}

	/**
	 * Course packages the signed-in student holds.
	 *
	 * @param object|null $course Restrict to one course.
	 * @return object[]
	 */
	public static function packages( $course = null ) {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return array();
		}

		$rows = MZK_Enrollments::query( array( 'email' => $user->user_email ) );
		if ( ! $rows ) {
			$rows = MZK_Enrollments::query( array( 'user_id' => (int) $user->ID ) );
		}

		if ( $course ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $course ) {
						return (int) $row->class_type_id === (int) $course->id;
					}
				)
			);
		}

		return $rows;
	}

	/**
	 * Signed-out view: log in, or set up a log-in.
	 *
	 * @param object|null $course Course this page is for.
	 */
	private static function render_signed_out( $course ) {
		$course_name = $course ? $course->name : __( 'course', 'mizuki-booking' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['mzk_error'] ) ? sanitize_key( wp_unslash( $_GET['mzk_error'] ) ) : '';

		include MZK_PATH . 'public/views/portal-signed-out.php';
	}

	/**
	 * Signed-in view: balance, book, upcoming, history.
	 *
	 * @param object|null $course Restrict to one course.
	 */
	private static function render_portal( $course ) {
		$user     = wp_get_current_user();
		$packages = self::packages( $course );

		if ( ! $packages ) {
			$course_name = $course ? $course->name : __( 'course', 'mizuki-booking' );
			include MZK_PATH . 'public/views/portal-empty.php';
			return;
		}

		$bookings = MZK_Bookings::for_student( $user->user_email, (int) $user->ID );
		$today    = MZK_Utils::today();
		$ids      = array_map( 'intval', wp_list_pluck( $packages, 'class_type_id' ) );

		$upcoming = array();
		$past     = array();

		foreach ( $bookings as $booking ) {
			if ( ! in_array( (int) $booking->class_type_id, $ids, true ) ) {
				continue;
			}
			if ( in_array( $booking->status, array( 'confirmed', 'awaiting_approval' ), true ) && $booking->session_date >= $today ) {
				$upcoming[] = $booking;
			} else {
				$past[] = $booking;
			}
		}

		// The very next class leads the page — it is what a student opens this for.
		$next = $upcoming ? $upcoming[0] : null;
		$rest = $upcoming ? array_slice( $upcoming, 1 ) : array();

		include MZK_PATH . 'public/views/portal.php';
	}
}
