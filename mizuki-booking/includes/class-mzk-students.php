<?php
/**
 * Student accounts, registration approval, and the login / dashboard screens.
 *
 * A student registers for a class in one step. If the class needs approving, the
 * booking waits in "awaiting approval" — the seat is held so it cannot be taken
 * meanwhile — until the studio approves or declines it. Either way the student
 * gets an account, so every class, package and detail sits in one place.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Students {

	const ROLE = 'mzk_student';

	/**
	 * Register hooks and shortcodes.
	 */
	public static function init() {
		add_shortcode( 'mizuki_login', array( __CLASS__, 'login_shortcode' ) );
		add_shortcode( 'mizuki_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );

		add_action( 'admin_post_nopriv_mzk_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_mzk_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_nopriv_mzk_login', array( __CLASS__, 'handle_login' ) );

		// Keep students out of wp-admin; send them to their own dashboard.
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
	}

	/**
	 * Create the student role. Called on activation.
	 */
	public static function add_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Student', 'mizuki-booking' ),
				array( 'read' => true )
			);
		}
	}

	/**
	 * Find or create the WordPress account behind a booking.
	 *
	 * @param string $email E-mail address.
	 * @param string $name  Display name.
	 * @param string $phone Contact number.
	 * @return int User id, or 0 when accounts are switched off or creation failed.
	 */
	public static function ensure_account( $email, $name = '', $phone = '' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return 0;
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			if ( $phone && ! get_user_meta( $existing->ID, 'mzk_phone', true ) ) {
				update_user_meta( $existing->ID, 'mzk_phone', $phone );
			}
			return (int) $existing->ID;
		}

		if ( ! MZK_Install::get_setting( 'auto_create_account', 1 ) ) {
			return 0;
		}

		$username = self::unique_username( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20 ),
				'display_name' => $name ? $name : $email,
				'first_name'   => $name,
				'role'         => self::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		if ( $phone ) {
			update_user_meta( $user_id, 'mzk_phone', $phone );
		}

		self::send_welcome( (int) $user_id );

		return (int) $user_id;
	}

	/**
	 * Build a username that is not taken.
	 *
	 * @param string $email E-mail address.
	 * @return string
	 */
	private static function unique_username( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'student';
		}
		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}
		return $username;
	}

	/**
	 * Welcome e-mail with a set-password link.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public static function send_welcome( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$settings = MZK_Install::get_settings();
		$key      = get_password_reset_key( $user );
		$password_url = is_wp_error( $key )
			? wp_login_url()
			: network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );

		$tags = array(
			'{student_name}'  => $user->display_name,
			'{student_email}' => $user->user_email,
			'{studio_name}'   => $settings['studio_name'],
			'{dashboard_url}' => self::dashboard_url(),
			'{password_url}'  => $password_url,
			'{site_url}'      => home_url( '/' ),
		);

		return MZK_Mailer::send(
			$user->user_email,
			MZK_Mailer::render( $settings['welcome_subject'], $tags ),
			MZK_Mailer::render( $settings['welcome_body'], $tags ),
			'welcome'
		);
	}

	/**
	 * URL of the student dashboard page.
	 *
	 * @return string
	 */
	public static function dashboard_url() {
		$page = (int) MZK_Install::get_setting( 'dashboard_page_id' );
		return $page ? get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * URL of the login / register page.
	 *
	 * @return string
	 */
	public static function login_url() {
		$page = (int) MZK_Install::get_setting( 'login_page_id' );
		return $page ? get_permalink( $page ) : wp_login_url();
	}

	/* -------------------------------------------------------- approvals */

	/**
	 * Does this class need the studio to approve registrations?
	 *
	 * @param int $class_type_id Class type id.
	 * @return bool
	 */
	public static function needs_approval( $class_type_id ) {
		$type = MZK_Class_Types::get( $class_type_id );
		return $type && ! empty( $type->requires_approval );
	}

	/**
	 * Approve a registration: confirm the seat and tell the student.
	 *
	 * @param int $booking_id Booking id.
	 * @return true|WP_Error
	 */
	public static function approve( $booking_id ) {
		global $wpdb;

		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Registration not found.', 'mizuki-booking' ) );
		}
		if ( 'awaiting_approval' !== $booking->status ) {
			return new WP_Error( 'mzk_not_pending', __( 'That registration is not awaiting approval.', 'mizuki-booking' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'status'      => 'confirmed',
				'approved_at' => current_time( 'mysql' ),
				'approved_by' => get_current_user_id(),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking_id )
		);

		MZK_Bookings::attach_enrollment( (int) $booking_id );
		self::send_decision( (int) $booking_id, 'approved' );

		/** Fires when a registration is approved. */
		do_action( 'mzk_registration_approved', (int) $booking_id );

		return true;
	}

	/**
	 * Decline a registration and free the seat.
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $reason     Optional note shown to the student.
	 * @return true|WP_Error
	 */
	public static function decline( $booking_id, $reason = '' ) {
		global $wpdb;

		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'mzk_no_booking', __( 'Registration not found.', 'mizuki-booking' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB
			MZK_DB::bookings(),
			array(
				'status'         => 'declined',
				'decline_reason' => sanitize_text_field( $reason ),
				'approved_by'    => get_current_user_id(),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $booking_id )
		);

		self::send_decision( (int) $booking_id, 'declined' );

		/** Fires when a registration is declined. */
		do_action( 'mzk_registration_declined', (int) $booking_id, $reason );

		return true;
	}

	/**
	 * Registrations waiting on the studio.
	 *
	 * @param int $limit Maximum rows.
	 * @return object[]
	 */
	public static function pending( $limit = 100 ) {
		return MZK_Bookings::query(
			array(
				'status'  => 'awaiting_approval',
				'limit'   => $limit,
				'orderby' => 's.session_date ASC, s.start_time ASC',
			)
		);
	}

	/**
	 * Send an approval-decision e-mail.
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $decision   'approved', 'declined' or 'pending'.
	 * @return bool
	 */
	public static function send_decision( $booking_id, $decision ) {
		$booking = MZK_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		$key = in_array( $decision, array( 'approved', 'declined', 'pending' ), true ) ? $decision : 'pending';

		$settings = MZK_Install::get_settings();
		$tags     = MZK_Mailer::tags( $booking );

		$tags['{dashboard_url}']  = self::dashboard_url();
		$tags['{decline_reason}'] = $booking->decline_reason ? $booking->decline_reason : '';

		return MZK_Mailer::send(
			$booking->email,
			MZK_Mailer::render( $settings[ $key . '_subject' ], $tags ),
			MZK_Mailer::render( $settings[ $key . '_body' ], $tags ),
			'registration_' . $key
		);
	}

	/**
	 * Tell the studio a registration is waiting.
	 *
	 * @param int $booking_id Booking id.
	 * @return bool
	 */
	public static function notify_admin_pending( $booking_id ) {
		$booking = MZK_Bookings::get( $booking_id );
		$to      = MZK_Install::get_setting( 'admin_email' );
		if ( ! $booking || ! is_email( $to ) ) {
			return false;
		}

		$studio = (int) MZK_Install::get_setting( 'studio_page_id' );
		$link   = $studio ? get_permalink( $studio ) : admin_url( 'admin.php?page=mzk-bookings&status=awaiting_approval' );

		$body = sprintf(
			/* translators: 1: student, 2: class, 3: date, 4: time, 5: email, 6: phone, 7: link. */
			__( "A new registration is waiting for your approval.\n\nStudent: %1\$s\nClass: %2\$s\nDate: %3\$s\nTime: %4\$s\nE-mail: %5\$s\nPhone: %6\$s\n\nApprove or decline here:\n%7\$s", 'mizuki-booking' ),
			$booking->student_name,
			$booking->class_name,
			$booking->date_label,
			$booking->time_label,
			$booking->email,
			$booking->phone ? $booking->phone : '—',
			$link
		);

		return MZK_Mailer::send(
			$to,
			sprintf(
				/* translators: 1: class, 2: date. */
				__( 'Registration to approve: %1$s on %2$s', 'mizuki-booking' ),
				$booking->class_name,
				$booking->date_label
			),
			$body,
			'admin_approval'
		);
	}

	/* ------------------------------------------------------ login pages */

	/**
	 * [mizuki_login] — login form, with registration for new students.
	 *
	 * @return string
	 */
	public static function login_shortcode() {
		MZK_Shortcodes::ensure_assets();

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			ob_start();
			echo '<div class="mzk-manage"><div class="mzk-booking">';
			printf(
				'<h3 class="mzk-booking__title">%s</h3>',
				esc_html( sprintf( /* translators: %s: name. */ __( 'You are logged in as %s', 'mizuki-booking' ), $user->display_name ) )
			);
			printf(
				'<p class="mzk-booking__actions"><a class="mzk-btn mzk-btn--primary" href="%1$s">%2$s</a> <a class="mzk-btn mzk-btn--ghost" href="%3$s">%4$s</a></p>',
				esc_url( self::dashboard_url() ),
				esc_html__( 'Go to my classes', 'mizuki-booking' ),
				esc_url( wp_logout_url( self::login_url() ) ),
				esc_html__( 'Log out', 'mizuki-booking' )
			);
			echo '</div></div>';
			return ob_get_clean();
		}

		ob_start();
		include MZK_PATH . 'public/views/login.php';
		return ob_get_clean();
	}

	/**
	 * [mizuki_dashboard] — the student's own area.
	 *
	 * @return string
	 */
	public static function dashboard_shortcode() {
		MZK_Shortcodes::ensure_assets();

		if ( ! is_user_logged_in() ) {
			return '<div class="mzk-manage"><div class="mzk-notice mzk-notice--info">'
				. esc_html__( 'Please log in to see your classes.', 'mizuki-booking' )
				. '</div><p><a class="mzk-btn mzk-btn--primary" href="' . esc_url( self::login_url() ) . '">'
				. esc_html__( 'Log in', 'mizuki-booking' ) . '</a></p></div>';
		}

		$user = wp_get_current_user();
		ob_start();
		include MZK_PATH . 'public/views/dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Handle the registration form (creates the account, then logs them in).
	 */
	public static function handle_register() {
		check_admin_referer( 'mzk_register' );

		$redirect = wp_get_referer() ? wp_get_referer() : self::login_url();

		$name  = isset( $_POST['student_name'] ) ? sanitize_text_field( wp_unslash( $_POST['student_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' === $name || ! is_email( $email ) || strlen( $pass ) < 8 ) {
			wp_safe_redirect( add_query_arg( 'mzk_error', 'invalid', $redirect ) );
			exit;
		}

		if ( email_exists( $email ) ) {
			wp_safe_redirect( add_query_arg( 'mzk_error', 'exists', $redirect ) );
			exit;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_username( $email ),
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => self::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'mzk_error', 'failed', $redirect ) );
			exit;
		}

		if ( $phone ) {
			update_user_meta( $user_id, 'mzk_phone', $phone );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		/** Fires after a student registers an account. */
		do_action( 'mzk_student_registered', (int) $user_id );

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	/**
	 * Handle the login form.
	 */
	public static function handle_login() {
		check_admin_referer( 'mzk_login' );

		$redirect = wp_get_referer() ? wp_get_referer() : self::login_url();

		$creds = array(
			'user_login'    => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
			'user_password' => isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '',
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'mzk_error', 'login', $redirect ) );
			exit;
		}

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	/**
	 * Send students to their dashboard after a wp-login.php sign-in.
	 *
	 * @param string  $redirect Requested redirect.
	 * @param string  $request  Requested URL.
	 * @param WP_User $user     User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $request, $user ) {
		if ( $user instanceof WP_User && in_array( self::ROLE, (array) $user->roles, true ) ) {
			return self::dashboard_url();
		}
		return $redirect;
	}

	/**
	 * Students have no business in wp-admin.
	 */
	public static function block_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( in_array( self::ROLE, (array) $user->roles, true ) ) {
			wp_safe_redirect( self::dashboard_url() );
			exit;
		}
	}

	/**
	 * Hide the toolbar for students.
	 *
	 * @param bool $show Current value.
	 * @return bool
	 */
	public static function hide_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		$user = wp_get_current_user();
		return in_array( self::ROLE, (array) $user->roles, true ) ? false : $show;
	}
}
