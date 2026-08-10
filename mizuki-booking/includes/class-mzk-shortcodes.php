<?php
/**
 * Front-end shortcodes: the booking calendar and the student booking manager.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Shortcodes {

	/**
	 * Whether assets have already been enqueued this request.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Register shortcodes.
	 */
	public static function init() {
		add_shortcode( 'mizuki_calendar', array( __CLASS__, 'calendar' ) );
		add_shortcode( 'mizuki_my_bookings', array( __CLASS__, 'my_bookings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (but do not enqueue) front-end assets.
	 */
	public static function register_assets() {
		wp_register_style( 'mzk-front', MZK_URL . 'assets/css/mzk-front.css', array(), MZK_VERSION );
		wp_register_script( 'mzk-front', MZK_URL . 'assets/js/mzk-front.js', array(), MZK_VERSION, true );
	}

	/**
	 * Enqueue the front-end assets and hand configuration to the script.
	 *
	 * Every screen that renders plugin markup must call this — not
	 * wp_enqueue_script() directly. The script is useless without MZK_CFG:
	 * it would have no REST root and no nonce, and every request would fall
	 * through to a 404 HTML page.
	 */
	public static function ensure_assets() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		wp_enqueue_style( 'mzk-front' );
		wp_enqueue_script( 'mzk-front' );

		wp_localize_script(
			'mzk-front',
			'MZK_CFG',
			array(
				'root'         => esc_url_raw( rest_url( MZK_Rest::NS ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'months'       => max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) ),
				'requirePhone' => (bool) MZK_Install::get_setting( 'require_phone' ),
				'startOfWeek'  => (int) get_option( 'start_of_week', 0 ),
				'weekdays'     => array_values( self::short_weekdays() ),
				'months_names' => array_values( self::month_names() ),
				'i18n'         => self::strings(),
			)
		);
	}

	/**
	 * Short weekday names, Sunday first.
	 *
	 * @return array<int,string>
	 */
	private static function short_weekdays() {
		global $wp_locale;
		$out = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$out[ $i ] = $wp_locale
				? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $i ) )
				: gmdate( 'D', strtotime( "Sunday +{$i} day" ) );
		}
		return $out;
	}

	/**
	 * Month names.
	 *
	 * @return array<int,string>
	 */
	private static function month_names() {
		global $wp_locale;
		$out = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$out[ $i ] = $wp_locale ? $wp_locale->get_month( $i ) : gmdate( 'F', mktime( 0, 0, 0, $i, 1, 2020 ) );
		}
		return $out;
	}

	/**
	 * Translatable strings used by the script.
	 *
	 * @return array<string,string>
	 */
	private static function strings() {
		return array(
			'loading'        => __( 'Loading the schedule…', 'mizuki-booking' ),
			'noSessions'     => __( 'No sessions available on this date.', 'mizuki-booking' ),
			'noneInRange'    => __( 'No sessions are open in this period yet. Please check back soon.', 'mizuki-booking' ),
			'selectDate'     => __( 'Select a date to see the sessions available.', 'mizuki-booking' ),
			'seatsLeft'      => __( '%d place(s) left', 'mizuki-booking' ),
			'full'           => __( 'Fully booked', 'mizuki-booking' ),
			'closed'         => __( 'Studio closed', 'mizuki-booking' ),
			'book'           => __( 'Book this session', 'mizuki-booking' ),
			'booking'        => __( 'Booking…', 'mizuki-booking' ),
			'name'           => __( 'Full name', 'mizuki-booking' ),
			'email'          => __( 'E-mail', 'mizuki-booking' ),
			'phone'          => __( 'Contact number', 'mizuki-booking' ),
			'notes'          => __( 'Anything we should know? (optional)', 'mizuki-booking' ),
			'confirm'        => __( 'Confirm booking', 'mizuki-booking' ),
			'cancel'         => __( 'Cancel', 'mizuki-booking' ),
			'back'           => __( 'Back', 'mizuki-booking' ),
			'allClasses'     => __( 'All classes', 'mizuki-booking' ),
			'prev'           => __( 'Previous month', 'mizuki-booking' ),
			'next'           => __( 'Next month', 'mizuki-booking' ),
			'error'          => __( 'Something went wrong. Please try again.', 'mizuki-booking' ),
			'required'       => __( 'Please complete the required fields.', 'mizuki-booking' ),
			'reschedule'     => __( 'Reschedule', 'mizuki-booking' ),
			'cancelBooking'  => __( 'Cancel booking', 'mizuki-booking' ),
			'confirmCancel'  => __( 'Cancel this booking? This cannot be undone.', 'mizuki-booking' ),
			'chooseNew'      => __( 'Choose a new session', 'mizuki-booking' ),
			'moveHere'       => __( 'Move to this session', 'mizuki-booking' ),
			'noAlternates'   => __( 'There are no other sessions available to move to right now.', 'mizuki-booking' ),
			'noBookings'     => __( 'You have no bookings yet.', 'mizuki-booking' ),
			'step1'          => __( 'Choose the date that suits you', 'mizuki-booking' ),
			'step2'          => __( 'Your details', 'mizuki-booking' ),
			'payFirst'       => __( 'This class is paid for when you book. You will be taken to the shop to complete it.', 'mizuki-booking' ),
			'bookAndPay'     => __( 'Book and pay', 'mizuki-booking' ),
			'awaitingNote'   => __( 'Your place is held while the studio confirms it. We will e-mail you as soon as it is approved.', 'mizuki-booking' ),
			'viewBooking'    => __( 'View my booking', 'mizuki-booking' ),
			'notReady'       => __( 'The booking system did not load correctly on this page. Please refresh, and tell the studio if it keeps happening.', 'mizuki-booking' ),
		);
	}

	/**
	 * [mizuki_calendar] — month grid with per-day sessions and a booking form.
	 *
	 * @param array $atts class, months, view.
	 * @return string
	 */
	public static function calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'    => '',
				'months'   => 0,
				'view'     => 'calendar',
				'showfilter' => 'yes',
			),
			$atts,
			'mizuki_calendar'
		);

		self::ensure_assets();

		$months = (int) $atts['months'];
		if ( $months < 2 ) {
			$months = max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) );
		}

		// ?class=ikebana preselects a class, so "Book a session" links from the
		// class pages and the student dashboard land on the right filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_GET['class'] ) ? sanitize_title( wp_unslash( $_GET['class'] ) ) : '';

		$class_slug = '';
		$type       = MZK_Class_Types::resolve( $requested ? $requested : $atts['class'] );
		if ( $type ) {
			$class_slug = $type->slug;
		}

		ob_start();
		?>
		<div class="mzk-root mzk-calendar"
			data-mzk-calendar
			data-class="<?php echo esc_attr( $class_slug ); ?>"
			data-months="<?php echo esc_attr( $months ); ?>"
			data-view="<?php echo esc_attr( 'list' === $atts['view'] ? 'list' : 'calendar' ); ?>"
			data-showfilter="<?php echo esc_attr( 'no' === $atts['showfilter'] ? 'no' : 'yes' ); ?>">
			<div class="mzk-loading"><?php esc_html_e( 'Loading the schedule…', 'mizuki-booking' ); ?></div>
			<noscript>
				<p><?php esc_html_e( 'Please enable JavaScript to view the booking calendar.', 'mizuki-booking' ); ?></p>
			</noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [mizuki_my_bookings] — student self-service: view, reschedule, cancel.
	 * Works for logged-in students and for e-mail links carrying a manage token.
	 *
	 * @return string
	 */
	public static function my_bookings() {
		self::ensure_assets();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$booking_id = isset( $_GET['mzk_booking'] ) ? absint( $_GET['mzk_booking'] ) : 0;
		$token      = isset( $_GET['mzk_token'] ) ? sanitize_text_field( wp_unslash( $_GET['mzk_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="mzk-root mzk-manage"
			data-mzk-manage
			data-booking="<?php echo esc_attr( $booking_id ); ?>"
			data-token="<?php echo esc_attr( $token ); ?>"
			data-logged-in="<?php echo is_user_logged_in() ? '1' : '0'; ?>">
			<div class="mzk-loading"><?php esc_html_e( 'Loading your bookings…', 'mizuki-booking' ); ?></div>
			<?php if ( ! $booking_id && ! is_user_logged_in() ) : ?>
				<p class="mzk-note">
					<?php esc_html_e( 'Open the link in your confirmation e-mail to manage a booking, or log in to see them all.', 'mizuki-booking' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
