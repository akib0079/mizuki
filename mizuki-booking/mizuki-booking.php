<?php
/**
 * Plugin Name:       Mizuki Booking
 * Plugin URI:        https://mizuki.com.sg/
 * Description:       Class booking calendar for Mizuki Flower Studio: multi-session days, per-session participant limits, 2+ month schedule, rule-based rescheduling, blackout dates, course session packages with extensions, auto confirmation and reminder emails.
 * Version:           3.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Avix Digital Agency
 * License:           GPL-2.0-or-later
 * Text Domain:       mizuki-booking
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'MZK_VERSION', '3.6.0' );
define( 'MZK_DB_VERSION', '3.4.0' );
define( 'MZK_FILE', __FILE__ );
define( 'MZK_PATH', plugin_dir_path( __FILE__ ) );
define( 'MZK_URL', plugin_dir_url( __FILE__ ) );
define( 'MZK_BASENAME', plugin_basename( __FILE__ ) );

require_once MZK_PATH . 'includes/class-mzk-install.php';
require_once MZK_PATH . 'includes/class-mzk-db.php';
require_once MZK_PATH . 'includes/class-mzk-utils.php';
require_once MZK_PATH . 'includes/class-mzk-class-types.php';
require_once MZK_PATH . 'includes/class-mzk-blackouts.php';
require_once MZK_PATH . 'includes/class-mzk-sessions.php';
require_once MZK_PATH . 'includes/class-mzk-enrollments.php';
require_once MZK_PATH . 'includes/class-mzk-bookings.php';
require_once MZK_PATH . 'includes/class-mzk-resend.php';
require_once MZK_PATH . 'includes/class-mzk-mailer.php';
require_once MZK_PATH . 'includes/class-mzk-cron.php';
require_once MZK_PATH . 'includes/class-mzk-rest.php';
require_once MZK_PATH . 'includes/class-mzk-shortcodes.php';
require_once MZK_PATH . 'includes/class-mzk-woo.php';
require_once MZK_PATH . 'includes/class-mzk-account.php';
require_once MZK_PATH . 'includes/class-mzk-students.php';
require_once MZK_PATH . 'includes/class-mzk-setup.php';
require_once MZK_PATH . 'includes/class-mzk-manage.php';
require_once MZK_PATH . 'includes/class-mzk-classes-page.php';
require_once MZK_PATH . 'includes/class-mzk-portal.php';
require_once MZK_PATH . 'admin/class-mzk-admin.php';

register_activation_hook( __FILE__, array( 'MZK_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MZK_Install', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function mzk_init() {
	load_plugin_textdomain( 'mizuki-booking', false, dirname( MZK_BASENAME ) . '/languages' );

	MZK_Install::maybe_upgrade();
	MZK_Cron::init();
	MZK_Rest::init();
	MZK_Shortcodes::init();
	MZK_Woo::init();
	MZK_Account::init();
	MZK_Students::init();
	MZK_Manage::init();
	MZK_Classes_Page::init();
	MZK_Portal::init();

	if ( is_admin() ) {
		MZK_Admin::init();
	}
}
add_action( 'plugins_loaded', 'mzk_init' );
