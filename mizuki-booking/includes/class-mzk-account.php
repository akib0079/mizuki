<?php
/**
 * Student area inside the WooCommerce My Account pages.
 *
 * Adds two tabs:
 *   My Classes — upcoming and past bookings, with reschedule and cancel.
 *   My Courses — IFDA / Preserved Flower package balances and expiry.
 *
 * The same views are available on any page through the plugin's shortcodes, so
 * the studio can use My Account, a custom page, or both.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Account {

	const EP_CLASSES = 'my-classes';
	const EP_COURSES = 'my-courses';

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ) );
		add_action( 'woocommerce_account_' . self::EP_CLASSES . '_endpoint', array( __CLASS__, 'render_classes' ) );
		add_action( 'woocommerce_account_' . self::EP_COURSES . '_endpoint', array( __CLASS__, 'render_courses' ) );

		add_shortcode( 'mizuki_my_courses', array( __CLASS__, 'render_courses_shortcode' ) );
	}

	/**
	 * Register the rewrite endpoints.
	 */
	public static function add_endpoints() {
		// EP_PAGES only — never EP_ROOT. An EP_ROOT endpoint claims the top-level
		// URL of the same name, which stopped WordPress resolving the "My Classes"
		// page at /my-classes/ and silently fell back to the blog index.
		// These only ever need to hang off the My Account page.
		add_rewrite_endpoint( self::EP_CLASSES, EP_PAGES );
		add_rewrite_endpoint( self::EP_COURSES, EP_PAGES );
	}

	/**
	 * Make the endpoints available as query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::EP_CLASSES;
		$vars[] = self::EP_COURSES;
		return $vars;
	}

	/**
	 * Insert the tabs above "Logout".
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu_items( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new[ self::EP_CLASSES ] = __( 'My Classes', 'mizuki-booking' );
				if ( self::has_courses() ) {
					$new[ self::EP_COURSES ] = __( 'My Courses', 'mizuki-booking' );
				}
			}
			$new[ $key ] = $label;
		}

		// No logout item (rare): append instead of dropping the tabs.
		if ( ! isset( $new[ self::EP_CLASSES ] ) ) {
			$new[ self::EP_CLASSES ] = __( 'My Classes', 'mizuki-booking' );
			if ( self::has_courses() ) {
				$new[ self::EP_COURSES ] = __( 'My Courses', 'mizuki-booking' );
			}
		}

		return $new;
	}

	/**
	 * Does the logged-in student hold any course package?
	 *
	 * @return bool
	 */
	private static function has_courses() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return (bool) self::current_enrollments();
	}

	/**
	 * Course packages belonging to the logged-in student.
	 *
	 * @return object[]
	 */
	private static function current_enrollments() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return array();
		}

		$rows = MZK_Enrollments::query( array( 'email' => $user->user_email ) );
		if ( ! $rows ) {
			$rows = MZK_Enrollments::query( array( 'user_id' => (int) $user->ID ) );
		}
		return $rows;
	}

	/**
	 * "My Classes" tab — reuses the booking manager shortcode.
	 */
	public static function render_classes() {
		echo do_shortcode( '[mizuki_my_bookings]' );
	}

	/**
	 * "My Courses" tab.
	 */
	public static function render_courses() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside.
		echo self::courses_markup( self::current_enrollments() );
	}

	/**
	 * [mizuki_my_courses] — the same panel on any page.
	 *
	 * @return string
	 */
	public static function render_courses_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="mzk-root mzk-manage"><p class="mzk-note">'
				. esc_html__( 'Please log in to see your course balance.', 'mizuki-booking' )
				. '</p></div>';
		}
		MZK_Shortcodes::ensure_assets();
		return self::courses_markup( self::current_enrollments() );
	}

	/**
	 * Render the package cards.
	 *
	 * @param object[] $enrollments Enrollment rows.
	 * @return string
	 */
	private static function courses_markup( $enrollments ) {
		ob_start();
		echo '<div class="mzk-root mzk-manage">';

		if ( ! $enrollments ) {
			echo '<p class="mzk-note">' . esc_html__( 'You have no course packages yet.', 'mizuki-booking' ) . '</p>';
			echo '</div>';
			return ob_get_clean();
		}

		foreach ( $enrollments as $enrollment ) {
			$percent = $enrollment->sessions_total
				? min( 100, round( 100 * $enrollment->sessions_used / $enrollment->sessions_total ) )
				: 0;
			?>
			<div class="mzk-booking">
				<h3 class="mzk-booking__title"><?php echo esc_html( $enrollment->class_name ); ?></h3>

				<div class="mzk-booking__meta">
					<span>
						<?php
						printf(
							/* translators: 1: sessions used, 2: sessions purchased. */
							esc_html__( '%1$d of %2$d sessions used', 'mizuki-booking' ),
							(int) $enrollment->sessions_used,
							(int) $enrollment->sessions_total
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: expiry date. */
							esc_html__( 'Valid until %s', 'mizuki-booking' ),
							esc_html( $enrollment->expiry_label )
						);
						?>
					</span>
					<span class="mzk-badge">
						<?php
						if ( $enrollment->is_expired ) {
							esc_html_e( 'Expired', 'mizuki-booking' );
						} elseif ( ! $enrollment->has_balance ) {
							esc_html_e( 'Completed', 'mizuki-booking' );
						} else {
							printf(
								/* translators: %d: sessions remaining. */
								esc_html__( '%d left', 'mizuki-booking' ),
								(int) $enrollment->sessions_left
							);
						}
						?>
					</span>
				</div>

				<div class="mzk-progress" role="img"
					aria-label="<?php echo esc_attr( sprintf( '%d%%', $percent ) ); ?>">
					<span style="width: <?php echo esc_attr( $percent ); ?>%"></span>
				</div>

				<?php if ( $enrollment->is_usable ) : ?>
					<?php $booking_page = (int) MZK_Install::get_setting( 'booking_page_id' ); ?>
					<?php if ( $booking_page ) : ?>
						<p class="mzk-booking__actions">
							<a class="mzk-btn mzk-btn--primary"
								href="<?php echo esc_url( add_query_arg( 'class', $enrollment->class_slug, get_permalink( $booking_page ) ) ); ?>">
								<?php esc_html_e( 'Book a session', 'mizuki-booking' ); ?>
							</a>
						</p>
					<?php endif; ?>
				<?php elseif ( $enrollment->is_expired || ! $enrollment->has_balance ) : ?>
					<p class="mzk-notice mzk-notice--info">
						<?php esc_html_e( 'Need more time or more sessions? Contact the studio and we can extend your package.', 'mizuki-booking' ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}

		echo '</div>';
		return ob_get_clean();
	}
}
