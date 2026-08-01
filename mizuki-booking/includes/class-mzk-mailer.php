<?php
/**
 * Transactional e-mail: confirmation on sign-up, reminder before class,
 * reschedule and cancellation notices.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Mailer {

	/**
	 * Merge tags available in every template.
	 *
	 * @param object      $booking     Decorated booking row.
	 * @param object|null $old_session Previous session, for reschedule mails.
	 * @return array<string,string>
	 */
	public static function tags( $booking, $old_session = null ) {
		$settings = MZK_Install::get_settings();

		$title = $booking->session_title ? $booking->session_title : $booking->class_name;

		$tags = array(
			'{student_name}'     => $booking->student_name,
			'{student_email}'    => $booking->email,
			'{student_phone}'    => $booking->phone,
			'{class_type}'       => $booking->class_name,
			'{session_title}'    => $title,
			'{session_date}'     => $booking->date_label,
			'{session_time}'     => $booking->time_label,
			'{session_duration}' => $booking->duration_label,
			'{seats}'            => (string) $booking->seats,
			'{booking_id}'       => (string) $booking->id,
			'{manage_url}'       => $booking->manage_url,
			'{studio_name}'      => $settings['studio_name'],
			'{site_url}'         => home_url( '/' ),
			'{old_session_date}' => '',
			'{old_session_time}' => '',
			'{sessions_left}'    => '',
		);

		if ( $old_session ) {
			$tags['{old_session_date}'] = MZK_Utils::format_date( $old_session->session_date );
			$tags['{old_session_time}'] = MZK_Utils::format_time_range( $old_session );
		}

		if ( $booking->enrollment_id ) {
			$enrollment = MZK_Enrollments::get( $booking->enrollment_id );
			if ( $enrollment ) {
				$tags['{sessions_left}'] = (string) $enrollment->sessions_left;
			}
		}

		/**
		 * Filter the merge tags used in booking e-mails.
		 *
		 * @param array  $tags    Tag map.
		 * @param object $booking Booking row.
		 */
		return apply_filters( 'mzk_mail_tags', $tags, $booking );
	}

	/**
	 * Replace merge tags in a string.
	 *
	 * @param string $text Template text.
	 * @param array  $tags Tag map.
	 * @return string
	 */
	public static function render( $text, $tags ) {
		return strtr( (string) $text, $tags );
	}

	/**
	 * Send an HTML e-mail through wp_mail().
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject line.
	 * @param string $body    Plain-text body; newlines become paragraphs.
	 * @param string $context Slug for filters/logging.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $context = 'general' ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$html = self::wrap_html( $subject, $body );

		/**
		 * Filter a booking e-mail immediately before it is sent.
		 *
		 * @param array  $mail    to, subject, html, headers.
		 * @param string $context Mail context slug.
		 */
		$mail = apply_filters(
			'mzk_mail',
			array(
				'to'      => $to,
				'subject' => $subject,
				'html'    => $html,
				'headers' => $headers,
			),
			$context
		);

		if ( empty( $mail['to'] ) ) {
			return false;
		}

		$sent  = false;
		$via   = 'wp_mail';
		$error = '';

		// Resend first when it is switched on: it reports real failures, where
		// wp_mail() on shared hosting usually reports success and delivers nothing.
		if ( class_exists( 'MZK_Resend' ) && MZK_Resend::enabled() ) {
			$via    = 'resend';
			$result = MZK_Resend::send( $mail['to'], $mail['subject'], $mail['html'] );

			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();

				if ( MZK_Install::get_setting( 'mail_fallback', 1 ) ) {
					$via  = 'resend → wp_mail';
					$sent = wp_mail( $mail['to'], $mail['subject'], $mail['html'], $mail['headers'] );
					if ( $sent ) {
						$error .= ' ' . __( '(fell back to the WordPress mailer)', 'mizuki-booking' );
					}
				}
			} else {
				$sent = true;
			}
		} else {
			$sent = wp_mail( $mail['to'], $mail['subject'], $mail['html'], $mail['headers'] );
			if ( ! $sent ) {
				$error = __( 'The WordPress mailer returned a failure. The host may not be able to send mail at all.', 'mizuki-booking' );
			}
		}

		self::log( $mail['to'], $mail['subject'], $context, $via, $sent, $error );

		return $sent;
	}

	/* ------------------------------------------------------- delivery log */

	const LOG_OPTION = 'mzk_mail_log';
	const LOG_LIMIT  = 100;

	/**
	 * Record one delivery attempt, so "no e-mails are arriving" can be answered
	 * with evidence rather than guesswork.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $context Mail context slug.
	 * @param string $via     Which transport was used.
	 * @param bool   $sent    Whether it went.
	 * @param string $error   Failure reason, if any.
	 */
	public static function log( $to, $subject, $context, $via, $sent, $error = '' ) {
		if ( ! MZK_Install::get_setting( 'mail_log', 1 ) ) {
			return;
		}

		$log = (array) get_option( self::LOG_OPTION, array() );

		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'to'      => $to,
				'subject' => $subject,
				'context' => $context,
				'via'     => $via,
				'sent'    => (bool) $sent,
				'error'   => $error,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_LIMIT ), false );
	}

	/**
	 * The most recent delivery attempts, newest first.
	 *
	 * @param int $limit How many.
	 * @return array
	 */
	public static function recent_log( $limit = 30 ) {
		return array_slice( (array) get_option( self::LOG_OPTION, array() ), 0, (int) $limit );
	}

	/**
	 * Empty the delivery log.
	 */
	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Minimal responsive HTML shell around the message body.
	 *
	 * @param string $subject Subject, used as the heading.
	 * @param string $body    Plain-text body.
	 * @return string
	 */
	public static function wrap_html( $subject, $body ) {
		$paragraphs = '';
		foreach ( preg_split( '/\n{2,}/', trim( $body ) ) as $chunk ) {
			$paragraphs .= '<p style="margin:0 0 16px;line-height:1.6;">' . nl2br( esc_html( $chunk ) ) . '</p>';
		}
		// Re-linkify URLs that survived escaping.
		$paragraphs = preg_replace(
			'#(https?://[^\s<]+)#',
			'<a href="$1" style="color:#3f827a;">$1</a>',
			$paragraphs
		);

		// Brand palette and type mirror mizuki.com.sg: teal action, navy text,
		// Poppins body, square corners.
		$html = '<div style="background:#f0f5fa;padding:28px 0;font-family:Poppins,Helvetica,Arial,sans-serif;color:#162a3c;font-weight:300;">'
			. '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-top:3px solid #3f827a;padding:36px 32px;">'
			. '<h1 style="margin:0 0 8px;font-size:15px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#162a3c;">' . esc_html( $subject ) . '</h1>'
			. '<div style="width:25px;border-top:1px solid #bcbcbc;margin:0 0 22px;"></div>'
			. $paragraphs
			. '</div></div>';

		/**
		 * Filter the wrapped e-mail HTML.
		 *
		 * @param string $html    Full HTML.
		 * @param string $subject Subject.
		 * @param string $body    Plain body.
		 */
		return apply_filters( 'mzk_mail_html', $html, $subject, $body );
	}

	/**
	 * Auto-confirmation, sent immediately after sign-up.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public static function send_confirmation( $booking_id ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}
		$settings = MZK_Install::get_settings();
		$tags     = self::tags( $booking );

		return self::send(
			$booking->email,
			self::render( $settings['confirm_subject'], $tags ),
			self::render( $settings['confirm_body'], $tags ),
			'confirmation'
		);
	}

	/**
	 * Reminder, sent by cron N days before the class.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public static function send_reminder( $booking_id ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking || 'confirmed' !== $booking->status ) {
			return false;
		}
		$settings = MZK_Install::get_settings();
		$tags     = self::tags( $booking );

		$sent = self::send(
			$booking->email,
			self::render( $settings['reminder_subject'], $tags ),
			self::render( $settings['reminder_body'], $tags ),
			'reminder'
		);

		if ( $sent ) {
			global $wpdb;
			$wpdb->update( // phpcs:ignore WordPress.DB
				MZK_DB::bookings(),
				array( 'reminder_sent_at' => current_time( 'mysql' ) ),
				array( 'id' => (int) $booking_id )
			);
		}

		return $sent;
	}

	/**
	 * Reschedule notice.
	 *
	 * @param int    $booking_id  Booking id.
	 * @param object $old_session Previous session data.
	 * @return bool
	 */
	public static function send_reschedule( $booking_id, $old_session ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}
		$settings = MZK_Install::get_settings();
		$tags     = self::tags( $booking, $old_session );

		return self::send(
			$booking->email,
			self::render( $settings['reschedule_subject'], $tags ),
			self::render( $settings['reschedule_body'], $tags ),
			'reschedule'
		);
	}

	/**
	 * Cancellation notice.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public static function send_cancellation( $booking_id ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}
		$settings = MZK_Install::get_settings();
		$tags     = self::tags( $booking );

		return self::send(
			$booking->email,
			self::render( $settings['cancel_subject'], $tags ),
			self::render( $settings['cancel_body'], $tags ),
			'cancellation'
		);
	}

	/**
	 * Internal copy of a new booking for the studio.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public static function notify_admin_new_booking( $booking_id ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}
		$to = MZK_Install::get_setting( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$body = sprintf(
			/* translators: 1: student name, 2: class, 3: date, 4: time, 5: phone, 6: email, 7: source. */
			__( "New booking received.\n\nStudent: %1\$s\nClass: %2\$s\nDate: %3\$s\nTime: %4\$s\nPhone: %5\$s\nE-mail: %6\$s\nSource: %7\$s", 'mizuki-booking' ),
			$booking->student_name,
			$booking->class_name,
			$booking->date_label,
			$booking->time_label,
			$booking->phone ? $booking->phone : '—',
			$booking->email,
			$booking->source
		);

		return self::send(
			$to,
			sprintf(
				/* translators: 1: class name, 2: date. */
				__( 'New booking: %1$s on %2$s', 'mizuki-booking' ),
				$booking->class_name,
				$booking->date_label
			),
			$body,
			'admin_notice'
		);
	}

	/**
	 * Send a test copy of a template to an address.
	 *
	 * @param string $template confirm|reminder|reschedule|cancel.
	 * @param string $to       Recipient.
	 * @return bool
	 */
	public static function send_test( $template, $to ) {
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'mzk_bad_email', __( 'That is not a valid e-mail address.', 'mizuki-booking' ) );
		}

		// Report the real reason a test fails, rather than a bare "could not send".
		if ( class_exists( 'MZK_Resend' ) && MZK_Resend::enabled() ) {
			$check = MZK_Resend::verify();
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		$settings = MZK_Install::get_settings();
		$key      = in_array( $template, array( 'confirm', 'reminder', 'reschedule', 'cancel' ), true ) ? $template : 'confirm';

		$sample = array(
			'{student_name}'     => __( 'Sample Student', 'mizuki-booking' ),
			'{student_email}'    => $to,
			'{student_phone}'    => '+65 9123 4567',
			'{class_type}'       => __( 'Preserved Flower', 'mizuki-booking' ),
			'{session_title}'    => __( 'Preserved Flower Workshop', 'mizuki-booking' ),
			'{session_date}'     => MZK_Utils::format_date( gmdate( 'Y-m-d', strtotime( '+7 days' ) ) ),
			'{session_time}'     => '10:00 am – 12:00 pm',
			'{session_duration}' => MZK_Utils::format_duration( 120 ),
			'{seats}'            => '1',
			'{booking_id}'       => '0',
			'{manage_url}'       => home_url( '/' ),
			'{studio_name}'      => $settings['studio_name'],
			'{site_url}'         => home_url( '/' ),
			'{old_session_date}' => MZK_Utils::format_date( gmdate( 'Y-m-d', strtotime( '+3 days' ) ) ),
			'{old_session_time}' => '2:00 pm – 4:00 pm',
			'{sessions_left}'    => '12',
		);

		$sent = self::send(
			$to,
			'[' . __( 'Test', 'mizuki-booking' ) . '] ' . self::render( $settings[ $key . '_subject' ], $sample ),
			self::render( $settings[ $key . '_body' ], $sample ),
			'test'
		);

		if ( $sent ) {
			return true;
		}

		$log  = self::recent_log( 1 );
		$note = isset( $log[0]['error'] ) && $log[0]['error'] ? $log[0]['error'] : '';

		return new WP_Error(
			'mzk_send_failed',
			$note ? $note : __( 'The message could not be sent.', 'mizuki-booking' )
		);
	}
}
