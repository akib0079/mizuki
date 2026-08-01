<?php
/**
 * Class types (Fresh Flower, Ikebana, Preserved Flower, IFDA, ...).
 *
 * Each type carries its own capacity default, duration default, course/package flag
 * and reschedule policy, so studio rules are data rather than hard-coded logic.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Class_Types {

	/**
	 * Fetch all class types.
	 *
	 * @param bool $active_only Only active types.
	 * @return object[]
	 */
	public static function all( $active_only = false ) {
		global $wpdb;
		$table = MZK_DB::class_types();
		$where = $active_only ? 'WHERE active = 1' : '';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, name ASC" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Fetch one class type by id.
	 *
	 * @param int $id Class type id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = MZK_DB::class_types();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Fetch one class type by slug.
	 *
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = MZK_DB::class_types();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $slug ) ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Resolve a class type from an id or a slug.
	 *
	 * @param mixed $ref Id or slug.
	 * @return object|null
	 */
	public static function resolve( $ref ) {
		if ( is_numeric( $ref ) ) {
			return self::get( (int) $ref );
		}
		if ( is_string( $ref ) && '' !== $ref ) {
			return self::get_by_slug( $ref );
		}
		return null;
	}

	/**
	 * id => name map.
	 *
	 * @param bool $active_only Only active types.
	 * @return array<int,string>
	 */
	public static function options( $active_only = false ) {
		$out = array();
		foreach ( self::all( $active_only ) as $type ) {
			$out[ (int) $type->id ] = $type->name;
		}
		return $out;
	}

	/**
	 * Insert or update a class type.
	 *
	 * @param array $data Field values; include 'id' to update.
	 * @return int|WP_Error Class type id.
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = MZK_DB::class_types();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'mzk_missing_name', __( 'Class name is required.', 'mizuki-booking' ) );
		}

		$slug = isset( $data['slug'] ) && '' !== $data['slug'] ? sanitize_title( $data['slug'] ) : sanitize_title( $name );

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id <> %d", $slug, $id ) ); // phpcs:ignore WordPress.DB
		if ( $existing ) {
			$slug .= '-' . wp_rand( 100, 999 );
		}

		$row = array(
			'slug'                    => $slug,
			'name'                    => $name,
			'colour'                  => isset( $data['colour'] ) ? sanitize_hex_color( $data['colour'] ) ?: '#3f827a' : '#3f827a',
			'default_capacity'        => max( 1, (int) ( $data['default_capacity'] ?? 6 ) ),
			'default_duration'        => max( 15, (int) ( $data['default_duration'] ?? 120 ) ),
			'course_based'            => empty( $data['course_based'] ) ? 0 : 1,
			'requires_enrollment'     => empty( $data['requires_enrollment'] ) ? 0 : 1,
			'reschedule_enabled'      => empty( $data['reschedule_enabled'] ) ? 0 : 1,
			'reschedule_cutoff_hours' => max( 0, (int) ( $data['reschedule_cutoff_hours'] ?? 72 ) ),
			'cancel_enabled'          => empty( $data['cancel_enabled'] ) ? 0 : 1,
			'cancel_cutoff_hours'     => max( 0, (int) ( $data['cancel_cutoff_hours'] ?? 72 ) ),
			'max_reschedules'         => max( 0, (int) ( $data['max_reschedules'] ?? 0 ) ),
			'requires_approval'       => empty( $data['requires_approval'] ) ? 0 : 1,
			'description'             => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'sort_order'              => (int) ( $data['sort_order'] ?? 0 ),
			'active'                  => empty( $data['active'] ) ? 0 : 1,
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
	 * Delete a class type. Refuses while sessions still reference it.
	 *
	 * @param int $id Class type id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id       = (int) $id;
		$sessions = MZK_DB::sessions();
		$count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions} WHERE class_type_id = %d", $id ) ); // phpcs:ignore WordPress.DB
		if ( $count ) {
			return new WP_Error(
				'mzk_type_in_use',
				__( 'This class still has sessions. Deactivate it instead of deleting, or remove its sessions first.', 'mizuki-booking' )
			);
		}
		$wpdb->delete( MZK_DB::class_types(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		return true;
	}

	/**
	 * Whether a booking of this class type may be rescheduled right now.
	 *
	 * Fresh Flower / Ikebana are configured with a 72 hour cutoff, so a Saturday class
	 * locks on the preceding Wednesday. Preserved Flower / IFDA use a shorter cutoff.
	 *
	 * @param object $type    Class type row.
	 * @param object $session Session row the booking currently sits on.
	 * @param object $booking Booking row.
	 * @return true|WP_Error
	 */
	public static function can_reschedule( $type, $session, $booking = null ) {
		if ( ! $type ) {
			return new WP_Error( 'mzk_no_type', __( 'Unknown class type.', 'mizuki-booking' ) );
		}
		if ( empty( $type->reschedule_enabled ) ) {
			return new WP_Error(
				'mzk_reschedule_disabled',
				/* translators: %s: class name. */
				sprintf( __( '%s bookings cannot be rescheduled online. Please contact the studio.', 'mizuki-booking' ), $type->name )
			);
		}

		$start = MZK_Utils::session_start( $session );
		if ( ! $start ) {
			return new WP_Error( 'mzk_bad_session', __( 'Session date is invalid.', 'mizuki-booking' ) );
		}

		$hours_left = MZK_Utils::hours_until( $start );
		$cutoff     = (float) $type->reschedule_cutoff_hours;

		if ( $hours_left < $cutoff ) {
			return new WP_Error(
				'mzk_reschedule_cutoff',
				sprintf(
					/* translators: 1: class name, 2: cutoff description. */
					__( '%1$s bookings can no longer be changed — rescheduling closes %2$s before the class starts. Please contact the studio.', 'mizuki-booking' ),
					$type->name,
					self::describe_cutoff( $cutoff )
				)
			);
		}

		if ( $booking && (int) $type->max_reschedules > 0 && (int) $booking->reschedule_count >= (int) $type->max_reschedules ) {
			return new WP_Error(
				'mzk_reschedule_limit',
				sprintf(
					/* translators: %d: allowed number of reschedules. */
					_n(
						'This booking has already been rescheduled %d time, which is the maximum allowed.',
						'This booking has already been rescheduled %d times, which is the maximum allowed.',
						(int) $type->max_reschedules,
						'mizuki-booking'
					),
					(int) $type->max_reschedules
				)
			);
		}

		return true;
	}

	/**
	 * Whether a booking of this class type may be cancelled right now.
	 *
	 * @param object $type    Class type row.
	 * @param object $session Session row.
	 * @return true|WP_Error
	 */
	public static function can_cancel( $type, $session ) {
		if ( ! $type ) {
			return new WP_Error( 'mzk_no_type', __( 'Unknown class type.', 'mizuki-booking' ) );
		}
		if ( empty( $type->cancel_enabled ) ) {
			return new WP_Error(
				'mzk_cancel_disabled',
				__( 'Online cancellation is not available for this class. Please contact the studio.', 'mizuki-booking' )
			);
		}
		$start = MZK_Utils::session_start( $session );
		if ( ! $start ) {
			return new WP_Error( 'mzk_bad_session', __( 'Session date is invalid.', 'mizuki-booking' ) );
		}
		if ( MZK_Utils::hours_until( $start ) < (float) $type->cancel_cutoff_hours ) {
			return new WP_Error(
				'mzk_cancel_cutoff',
				sprintf(
					/* translators: %s: cutoff description. */
					__( 'Cancellation closes %s before the class starts. Please contact the studio.', 'mizuki-booking' ),
					self::describe_cutoff( (float) $type->cancel_cutoff_hours )
				)
			);
		}
		return true;
	}

	/**
	 * "3 days" / "24 hours" phrasing for a cutoff in hours.
	 *
	 * @param float $hours Cutoff hours.
	 * @return string
	 */
	public static function describe_cutoff( $hours ) {
		$hours = (float) $hours;
		if ( $hours <= 0 ) {
			return __( 'no time', 'mizuki-booking' );
		}
		if ( 0 === (int) fmod( $hours, 24 ) ) {
			$days = (int) ( $hours / 24 );
			/* translators: %d: number of days. */
			return sprintf( _n( '%d day', '%d days', $days, 'mizuki-booking' ), $days );
		}
		$whole = (int) round( $hours );
		/* translators: %d: number of hours. */
		return sprintf( _n( '%d hour', '%d hours', $whole, 'mizuki-booking' ), $whole );
	}
}
