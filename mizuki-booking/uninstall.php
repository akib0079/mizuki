<?php
/**
 * Removes every plugin table and option.
 * Only runs when the plugin is deleted from the Plugins screen.
 *
 * @package Mizuki_Booking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mzk-db.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mzk-install.php';

MZK_Install::uninstall();
