<?php
/**
 * Studio closure dates. A blackout hides every matching session from students.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Blackouts {

	/**
	 * Fetch blackouts, newest range first.
	 *
	 * @param array $args from, to, class_type_id.
	 * @return object[]
	 */
	public static function all( $args = array() ) {
		global $wpdb;
		$table = MZK_DB::blackouts();
		$where = array( '1=1' );
		$vals  = array();

		if ( ! empty( $args['from'] ) ) {
			$where[] = 'end_date >= %s';
			$vals[]  = $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$where[] = 'start_date <= %s';
			$vals[]  = $args['to'];
		}
		if ( isset( $args['class_type_id'] ) && '' !== $args['class_type_id'] ) {
			$where[] = '(class_type_id = 0 OR class_type_id = %d)';
			$vals[]  = (int) $args['class_type_id'];
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY start_date DESC';
		if ( $vals ) {
			$sql = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Get one blackout.
	 *
	 * @param int $id Blackout id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = MZK_DB::blackouts();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Create or update a blackout range.
	 *
	 * @param array $data start_date, end_date, class_type_id, reason, id.
	 * @return int|WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$table = MZK_DB::blackouts();
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$start = MZK_Utils::sanitize_date( $data['start_date'] ?? '' );
		$end   = MZK_Utils::sanitize_date( $data['end_date'] ?? '' );
		if ( ! $start ) {
			return new WP_Error( 'mzk_bad_date', __( 'A valid start date is required.', 'mizuki-booking' ) );
		}
		if ( ! $end ) {
			$end = $start;
		}
		if ( $end < $start ) {
			list( $start, $end ) = array( $end, $start );
		}

		$row = array(
			'class_type_id' => (int) ( $data['class_type_id'] ?? 0 ),
			'start_date'    => $start,
			'end_date'      => $end,
			'reason'        => sanitize_text_field( $data['reason'] ?? '' ),
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a blackout is saved, so callers can warn about affected bookings.
		 *
		 * @param int   $id  Blackout id.
		 * @param array $row Stored values.
		 */
		do_action( 'mzk_blackout_saved', $id, $row );

		return $id;
	}

	/**
	 * Delete a blackout.
	 *
	 * @param int $id Blackout id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( MZK_DB::blackouts(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * All blacked-out dates in a range, as a lookup map.
	 *
	 * @param string   $from          Y-m-d.
	 * @param string   $to            Y-m-d.
	 * @param int|null $class_type_id Restrict to a class type (studio-wide blackouts always apply).
	 * @return array<string,string> date => reason
	 */
	public static function map( $from, $to, $class_type_id = null ) {
		$map = array();
		$args = array(
			'from' => $from,
			'to'   => $to,
		);
		if ( null !== $class_type_id ) {
			$args['class_type_id'] = (int) $class_type_id;
		}

		foreach ( self::all( $args ) as $row ) {
			$cursor = max( $row->start_date, $from );
			$last   = min( $row->end_date, $to );
			while ( $cursor <= $last ) {
				$map[ $cursor ] = $row->reason;
				$cursor         = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
			}
		}
		return $map;
	}

	/**
	 * Is a given date blacked out for a class type?
	 *
	 * @param string   $date          Y-m-d.
	 * @param int|null $class_type_id Class type id.
	 * @return bool
	 */
	public static function is_blocked( $date, $class_type_id = null ) {
		global $wpdb;
		$table = MZK_DB::blackouts();
		$date  = MZK_Utils::sanitize_date( $date );
		if ( ! $date ) {
			return false;
		}
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE start_date <= %s AND end_date >= %s AND (class_type_id = 0 OR class_type_id = %d)", // phpcs:ignore WordPress.DB
			$date,
			$date,
			(int) $class_type_id
		);
		return (bool) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Bookings that fall inside a blackout range — shown to the admin so nobody is stranded.
	 *
	 * @param int $blackout_id Blackout id.
	 * @return object[]
	 */
	public static function affected_bookings( $blackout_id ) {
		global $wpdb;
		$blackout = self::get( $blackout_id );
		if ( ! $blackout ) {
			return array();
		}
		$bookings = MZK_DB::bookings();
		$sessions = MZK_DB::sessions();
		$statuses = MZK_Utils::occupying_statuses();
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$params = array_merge(
			$statuses,
			array( $blackout->start_date, $blackout->end_date )
		);
		$type_clause = '';
		if ( (int) $blackout->class_type_id ) {
			$type_clause = ' AND s.class_type_id = %d';
			$params[]    = (int) $blackout->class_type_id;
		}

		$sql = $wpdb->prepare(
			"SELECT b.*, s.session_date, s.start_time, s.duration_minutes
			 FROM {$bookings} b
			 INNER JOIN {$sessions} s ON s.id = b.session_id
			 WHERE b.status IN ({$in}) AND s.session_date BETWEEN %s AND %s{$type_clause}
			 ORDER BY s.session_date ASC, s.start_time ASC", // phpcs:ignore WordPress.DB
			$params
		);
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB
	}
}
