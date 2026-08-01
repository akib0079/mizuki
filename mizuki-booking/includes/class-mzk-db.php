<?php
/**
 * Table name helpers.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_DB {

	/**
	 * Fully qualified table name.
	 *
	 * @param string $key One of: class_types, sessions, templates, blackouts, bookings, enrollments, enrollment_log.
	 * @return string
	 */
	public static function table( $key ) {
		global $wpdb;
		return $wpdb->prefix . 'mzk_' . $key;
	}

	public static function class_types() {
		return self::table( 'class_types' );
	}

	public static function sessions() {
		return self::table( 'sessions' );
	}

	public static function templates() {
		return self::table( 'templates' );
	}

	public static function blackouts() {
		return self::table( 'blackouts' );
	}

	public static function bookings() {
		return self::table( 'bookings' );
	}

	public static function enrollments() {
		return self::table( 'enrollments' );
	}

	public static function enrollment_log() {
		return self::table( 'enrollment_log' );
	}

	/**
	 * All plugin tables, in drop-safe order.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			self::enrollment_log(),
			self::bookings(),
			self::enrollments(),
			self::sessions(),
			self::templates(),
			self::blackouts(),
			self::class_types(),
		);
	}
}
