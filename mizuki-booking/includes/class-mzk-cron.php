<?php
/**
 * Scheduled work: class reminders, schedule horizon top-up, housekeeping.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Cron {

	const HOOK_REMINDERS   = 'mzk_send_reminders';
	const HOOK_MAINTENANCE = 'mzk_daily_maintenance';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::HOOK_REMINDERS, array( __CLASS__, 'run_reminders' ) );
		add_action( self::HOOK_MAINTENANCE, array( __CLASS__, 'run_maintenance' ) );
		add_action( 'mzk_blackout_saved', array( __CLASS__, 'apply_blackout' ), 10, 2 );

		// Self-heal if the events were lost (e.g. a database restore).
		if ( ! wp_next_scheduled( self::HOOK_REMINDERS ) || ! wp_next_scheduled( self::HOOK_MAINTENANCE ) ) {
			self::schedule_events();
		}
	}

	/**
	 * Schedule the recurring events.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::HOOK_REMINDERS ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK_REMINDERS );
		}
		if ( ! wp_next_scheduled( self::HOOK_MAINTENANCE ) ) {
			wp_schedule_event( time() + 600, 'daily', self::HOOK_MAINTENANCE );
		}
	}

	/**
	 * Clear the recurring events.
	 */
	public static function clear_events() {
		wp_clear_scheduled_hook( self::HOOK_REMINDERS );
		wp_clear_scheduled_hook( self::HOOK_MAINTENANCE );
	}

	/**
	 * Send reminders for classes starting N days from now.
	 * Runs hourly; the send window opens at the configured hour and each booking
	 * is stamped once, so re-runs never double-send.
	 *
	 * @param bool $force Ignore the hour-of-day gate (used by the manual admin button).
	 * @return int Number of reminders sent.
	 */
	public static function run_reminders( $force = false ) {
		// Release seats held by orders that were never paid. This runs before the
		// hour gate below so holds are always cleared, whatever time it is.
		if ( class_exists( 'MZK_Woo' ) ) {
			MZK_Woo::expire_holds();
		}

		$days = (int) MZK_Install::get_setting( 'reminder_days_before', 2 );
		if ( $days < 0 ) {
			return 0;
		}

		$hour = (int) MZK_Install::get_setting( 'reminder_hour', 9 );
		$now  = MZK_Utils::now();
		if ( ! $force && (int) $now->format( 'G' ) < $hour ) {
			return 0;
		}

		$target = $now->modify( "+{$days} days" )->format( 'Y-m-d' );

		global $wpdb;
		$bookings = MZK_DB::bookings();
		$sessions = MZK_DB::sessions();

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT b.id
				 FROM {$bookings} b
				 INNER JOIN {$sessions} s ON s.id = b.session_id
				 WHERE b.status = 'confirmed'
				   AND b.reminder_sent_at IS NULL
				   AND s.status = 'open'
				   AND s.session_date = %s", // phpcs:ignore WordPress.DB
				$target
			)
		);

		$sent = 0;
		foreach ( $ids as $id ) {
			if ( MZK_Mailer::send_reminder( (int) $id ) ) {
				++$sent;
			}
		}

		/**
		 * Fires after a reminder batch.
		 *
		 * @param int    $sent   Reminders sent.
		 * @param string $target Session date targeted.
		 */
		do_action( 'mzk_reminders_sent', $sent, $target );

		return $sent;
	}

	/**
	 * Daily housekeeping: keep the schedule horizon deep enough, close sessions that
	 * fall inside blackouts, and complete exhausted course packages.
	 *
	 * @return array Summary counts.
	 */
	public static function run_maintenance() {
		$generated = MZK_Sessions::ensure_horizon();
		$closed    = self::close_blacked_out_sessions();
		$completed = MZK_Enrollments::sync_completion();

		return array(
			'generated' => $generated,
			'closed'    => $closed,
			'completed' => $completed,
		);
	}

	/**
	 * Close any open session sitting on a blacked-out date.
	 *
	 * @return int Sessions closed.
	 */
	public static function close_blacked_out_sessions() {
		$closed = 0;
		$from   = MZK_Utils::today();
		$to     = gmdate( 'Y-m-d', strtotime( $from . ' +12 months' ) );

		foreach ( MZK_Blackouts::all( array( 'from' => $from, 'to' => $to ) ) as $blackout ) {
			$closed += MZK_Sessions::close_range(
				max( $blackout->start_date, $from ),
				$blackout->end_date,
				(int) $blackout->class_type_id
			);
		}
		return $closed;
	}

	/**
	 * Close sessions as soon as a blackout is saved, so the calendar updates immediately.
	 *
	 * @param int   $id  Blackout id.
	 * @param array $row Blackout values.
	 */
	public static function apply_blackout( $id, $row ) {
		MZK_Sessions::close_range( $row['start_date'], $row['end_date'], (int) $row['class_type_id'] );
	}
}
