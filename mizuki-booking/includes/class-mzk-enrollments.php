<?php
/**
 * Course packages for IFDA and Preserved Flower students: a fixed number of
 * sessions with an expiry, extendable by the studio at any time.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Enrollments {

	/**
	 * Query enrollments with sessions-used counts attached.
	 *
	 * @param array $args status, class_type_id, email, user_id, search, limit, offset.
	 * @return object[]
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'id'            => 0,
				'status'        => '',
				'class_type_id' => 0,
				'email'         => '',
				'user_id'       => 0,
				'search'        => '',
				'limit'         => 0,
				'offset'        => 0,
			)
		);

		$table    = MZK_DB::enrollments();
		$types    = MZK_DB::class_types();
		$bookings = MZK_DB::bookings();
		$statuses = MZK_Utils::occupying_statuses();
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$params = $statuses;
		$where  = array( '1=1' );

		if ( $args['id'] ) {
			$where[]  = 'e.id = %d';
			$params[] = (int) $args['id'];
		}
		if ( $args['status'] ) {
			$where[]  = 'e.status = %s';
			$params[] = $args['status'];
		}
		if ( $args['class_type_id'] ) {
			$where[]  = 'e.class_type_id = %d';
			$params[] = (int) $args['class_type_id'];
		}
		if ( $args['email'] ) {
			$where[]  = 'e.email = %s';
			$params[] = sanitize_email( $args['email'] );
		}
		if ( $args['user_id'] ) {
			$where[]  = 'e.user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(e.student_name LIKE %s OR e.email LIKE %s OR e.phone LIKE %s)';
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

		$sql = "SELECT e.*, ct.name AS class_name, ct.slug AS class_slug, ct.colour AS class_colour,
					COALESCE(bk.used, 0) AS sessions_used
				FROM {$table} e
				LEFT JOIN {$types} ct ON ct.id = e.class_type_id
				LEFT JOIN (
					SELECT enrollment_id, SUM(seats) AS used
					FROM {$bookings}
					WHERE status IN ({$in}) AND enrollment_id > 0
					GROUP BY enrollment_id
				) bk ON bk.enrollment_id = e.id
				WHERE " . implode( ' AND ', $where ) . "
				ORDER BY e.created_at DESC {$limit_sql}";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB

		return array_map( array( __CLASS__, 'decorate' ), $rows ? $rows : array() );
	}

	/**
	 * Attach computed balance fields.
	 *
	 * @param object $row Enrollment row.
	 * @return object
	 */
	public static function decorate( $row ) {
		$row->id                = (int) $row->id;
		$row->sessions_total    = (int) $row->sessions_total;
		$row->sessions_used     = (int) $row->sessions_used;
		$row->sessions_left     = max( 0, $row->sessions_total - $row->sessions_used );
		$row->is_expired        = $row->expiry_date && $row->expiry_date < MZK_Utils::today();
		$row->expiry_label      = $row->expiry_date ? MZK_Utils::format_date( $row->expiry_date ) : __( 'No expiry', 'mizuki-booking' );
		$row->has_balance       = $row->sessions_left > 0;
		$row->is_usable         = 'active' === $row->status && ! $row->is_expired && $row->has_balance;
		return $row;
	}

	/**
	 * Get one enrollment.
	 *
	 * @param int $id Enrollment id.
	 * @return object|null
	 */
	public static function get( $id ) {
		$rows = self::query( array( 'id' => (int) $id ) );
		return $rows ? $rows[0] : null;
	}

	/**
	 * Find the usable enrollment a student can spend on a class type.
	 *
	 * @param string $email         Student e-mail.
	 * @param int    $class_type_id Class type id.
	 * @param int    $user_id       Optional WP user id.
	 * @return object|null
	 */
	public static function find_usable( $email, $class_type_id, $user_id = 0 ) {
		$candidates = self::query(
			array(
				'email'         => $email,
				'class_type_id' => (int) $class_type_id,
				'status'        => 'active',
			)
		);

		if ( ! $candidates && $user_id ) {
			$candidates = self::query(
				array(
					'user_id'       => (int) $user_id,
					'class_type_id' => (int) $class_type_id,
					'status'        => 'active',
				)
			);
		}

		foreach ( $candidates as $enrollment ) {
			if ( $enrollment->is_usable ) {
				return $enrollment;
			}
		}
		return null;
	}

	/**
	 * Create or update an enrollment.
	 *
	 * @param array $data Enrollment fields; include 'id' to update.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = MZK_DB::enrollments();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$type = MZK_Class_Types::get( (int) ( $data['class_type_id'] ?? 0 ) );
		if ( ! $type ) {
			return new WP_Error( 'mzk_bad_class', __( 'Please choose a course.', 'mizuki-booking' ) );
		}
		if ( empty( $type->course_based ) ) {
			return new WP_Error(
				'mzk_not_course',
				/* translators: %s: class name. */
				sprintf( __( '%s is not a course-based class, so it has no session package.', 'mizuki-booking' ), $type->name )
			);
		}

		$name  = sanitize_text_field( $data['student_name'] ?? '' );
		$email = sanitize_email( $data['email'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'mzk_bad_name', __( 'Student name is required.', 'mizuki-booking' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'mzk_bad_email', __( 'A valid e-mail address is required.', 'mizuki-booking' ) );
		}

		$total = (int) ( $data['sessions_total'] ?? 0 );
		if ( $total < 1 ) {
			return new WP_Error( 'mzk_bad_total', __( 'Enter how many sessions the student purchased.', 'mizuki-booking' ) );
		}

		$user_id = (int) ( $data['user_id'] ?? 0 );
		if ( ! $user_id ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = (int) $user->ID;
			}
		}

		$row = array(
			'user_id'        => $user_id,
			'class_type_id'  => (int) $type->id,
			'student_name'   => $name,
			'email'          => $email,
			'phone'          => sanitize_text_field( $data['phone'] ?? '' ),
			'sessions_total' => $total,
			'start_date'     => MZK_Utils::sanitize_date( $data['start_date'] ?? '' ),
			'expiry_date'    => MZK_Utils::sanitize_date( $data['expiry_date'] ?? '' ),
			'status'         => in_array( $data['status'] ?? 'active', array( 'active', 'completed', 'paused', 'cancelled' ), true ) ? $data['status'] : 'active',
			'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		// Only write the order link when one is supplied, so editing a package in
		// the admin never severs its connection to the order that created it.
		if ( ! empty( $data['order_id'] ) ) {
			$row['order_id'] = (int) $data['order_id'];
		}
		if ( ! empty( $data['product_id'] ) ) {
			$row['product_id'] = (int) $data['product_id'];
		}

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
	 * Extend a course: add sessions, push the expiry date, or both.
	 * Every extension is written to an audit log.
	 *
	 * @param int    $id         Enrollment id.
	 * @param int    $add        Sessions to add (may be negative to correct a mistake).
	 * @param string $new_expiry New expiry date, Y-m-d, or '' to leave unchanged.
	 * @param string $reason     Free-text note.
	 * @return true|WP_Error
	 */
	public static function extend( $id, $add, $new_expiry = '', $reason = '' ) {
		global $wpdb;
		$enrollment = self::get( $id );
		if ( ! $enrollment ) {
			return new WP_Error( 'mzk_no_enrollment', __( 'Enrollment not found.', 'mizuki-booking' ) );
		}

		$add        = (int) $add;
		$new_expiry = MZK_Utils::sanitize_date( $new_expiry );

		if ( 0 === $add && ! $new_expiry ) {
			return new WP_Error( 'mzk_nothing_to_do', __( 'Add sessions, set a new expiry date, or both.', 'mizuki-booking' ) );
		}

		$new_total = $enrollment->sessions_total + $add;
		if ( $new_total < $enrollment->sessions_used ) {
			return new WP_Error(
				'mzk_below_used',
				sprintf(
					/* translators: %d: sessions already used. */
					__( 'The student has already used %d sessions; the package cannot be smaller than that.', 'mizuki-booking' ),
					$enrollment->sessions_used
				)
			);
		}

		$update = array(
			'sessions_total' => $new_total,
			'updated_at'     => current_time( 'mysql' ),
		);
		if ( $new_expiry ) {
			$update['expiry_date'] = $new_expiry;
		}
		// Re-open a package that had been marked complete.
		if ( 'completed' === $enrollment->status && $new_total > $enrollment->sessions_used ) {
			$update['status'] = 'active';
		}

		$wpdb->update( MZK_DB::enrollments(), $update, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB

		$wpdb->insert( // phpcs:ignore WordPress.DB
			MZK_DB::enrollment_log(),
			array(
				'enrollment_id'  => (int) $id,
				'delta_sessions' => $add,
				'old_expiry'     => $enrollment->expiry_date,
				'new_expiry'     => $new_expiry ? $new_expiry : $enrollment->expiry_date,
				'reason'         => sanitize_text_field( $reason ),
				'actor_id'       => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			)
		);

		/**
		 * Fires after a course package is extended.
		 *
		 * @param int    $id         Enrollment id.
		 * @param int    $add        Sessions added.
		 * @param string $new_expiry New expiry date or ''.
		 */
		do_action( 'mzk_enrollment_extended', (int) $id, $add, $new_expiry );

		return true;
	}

	/**
	 * Extension history for an enrollment.
	 *
	 * @param int $id Enrollment id.
	 * @return object[]
	 */
	public static function log( $id ) {
		global $wpdb;
		$table = MZK_DB::enrollment_log();
		return $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE enrollment_id = %d ORDER BY created_at DESC", (int) $id ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Delete an enrollment. Bookings keep their history but lose the link.
	 *
	 * @param int $id Enrollment id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->update( MZK_DB::bookings(), array( 'enrollment_id' => 0 ), array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( MZK_DB::enrollment_log(), array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( MZK_DB::enrollments(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Mark packages complete once every session is used. Runs from daily cron.
	 *
	 * @return int Rows touched.
	 */
	public static function sync_completion() {
		$count = 0;
		foreach ( self::query( array( 'status' => 'active' ) ) as $enrollment ) {
			if ( $enrollment->sessions_used >= $enrollment->sessions_total ) {
				global $wpdb;
				$wpdb->update( // phpcs:ignore WordPress.DB
					MZK_DB::enrollments(),
					array(
						'status'     => 'completed',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $enrollment->id )
				);
				++$count;
			}
		}
		return $count;
	}
}
