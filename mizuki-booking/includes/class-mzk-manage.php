<?php
/**
 * Front-end studio manager.
 *
 * Everything the studio needs day to day, without going into wp-admin: approve
 * registrations, add or adjust sessions, change places, block dates and see who
 * is coming. Only users who can manage bookings ever see it.
 *
 * Forms post to the same handler the admin screens use, so there is one set of
 * rules, one nonce check and one capability check for both.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Manage {

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( 'mizuki_manage', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [mizuki_manage] — the front-end control panel.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! current_user_can( MZK_Utils::cap() ) ) {
			// Say nothing at all to visitors — the page simply looks empty.
			return current_user_can( 'read' )
				? '<div class="mzk-manage"><div class="mzk-notice mzk-notice--info">'
					. esc_html__( 'You do not have permission to manage bookings.', 'mizuki-booking' )
					. '</div></div>'
				: '';
		}

		MZK_Shortcodes::ensure_assets();

		ob_start();
		include MZK_PATH . 'public/views/manage.php';
		return ob_get_clean();
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	public static function tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['mzk_tab'] ) ? sanitize_key( wp_unslash( $_GET['mzk_tab'] ) ) : 'today';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'today';
	}

	/**
	 * Tab definitions.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		$waiting = count( MZK_Students::pending( 50 ) );

		return array(
			'today'     => __( 'Coming up', 'mizuki-booking' ),
			'approvals' => $waiting
				/* translators: %d: number waiting. */
				? sprintf( __( 'Approvals (%d)', 'mizuki-booking' ), $waiting )
				: __( 'Approvals', 'mizuki-booking' ),
			'sessions'  => __( 'Sessions', 'mizuki-booking' ),
			'closures'  => __( 'Blocked dates', 'mizuki-booking' ),
		);
	}

	/**
	 * URL for a tab on the current page.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg( 'mzk_tab', $tab, self::current_url() );
	}

	/**
	 * The page we are on, without one-shot query args.
	 *
	 * @return string
	 */
	public static function current_url() {
		$page = (int) MZK_Install::get_setting( 'studio_page_id' );
		if ( $page && get_post_status( $page ) ) {
			$base = get_permalink( $page );
		} else {
			global $wp;
			$base = home_url( add_query_arg( array(), $wp->request ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['mzk_tab'] ) ? sanitize_key( wp_unslash( $_GET['mzk_tab'] ) ) : '';
		return $tab ? add_query_arg( 'mzk_tab', $tab, $base ) : $base;
	}

	/**
	 * Hidden fields for a form that should come back to this page.
	 *
	 * @param string $action Action slug.
	 */
	public static function form_fields( $action ) {
		MZK_Admin::form_fields( $action );
		printf( '<input type="hidden" name="mzk_return" value="%s" />', esc_url( self::current_url() ) );
	}

	/**
	 * A one-click action URL that returns to this page.
	 *
	 * @param string $action Action slug.
	 * @param array  $args   Extra arguments.
	 * @return string
	 */
	public static function action_url( $action, $args = array() ) {
		$args['mzk_return'] = self::current_url();
		return MZK_Admin::action_url( $action, $args );
	}
}
