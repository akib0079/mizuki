<?php
/**
 * Schema creation, seed data, activation/deactivation.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Install {

	const OPTION_DB_VERSION = 'mzk_db_version';
	const OPTION_SETTINGS   = 'mzk_settings';

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'studio_name'          => get_bloginfo( 'name' ),
			'admin_email'          => get_option( 'admin_email' ),
			'notify_admin'         => 1,
			'months_ahead'         => 3,
			'reminder_days_before' => 2,
			'reminder_hour'        => 9,
			'classes_page_id'      => 0,
			'booking_page_id'      => 0,
			'manage_page_id'       => 0,
			'login_page_id'        => 0,
			'dashboard_page_id'    => 0,
			'studio_page_id'       => 0,
			'require_phone'        => 1,
			'auto_create_account'  => 1,
			// E-mail delivery.
			'mail_provider'        => 'wp',
			'resend_api_key'       => '',
			'mail_from_name'       => get_bloginfo( 'name' ),
			'mail_from_email'      => get_option( 'admin_email' ),
			'mail_reply_to'        => get_option( 'admin_email' ),
			'mail_fallback'        => 1,
			'mail_log'             => 1,
			// WooCommerce integration.
			'woo_enabled'          => 1,
			'woo_hold_minutes'     => 45,
			'woo_confirm_on'       => 'processing',
			'woo_package_validity' => 12,
			'confirm_subject'      => __( 'Your booking is confirmed - {session_title}', 'mizuki-booking' ),
			'confirm_body'         => self::default_confirm_body(),
			'reminder_subject'     => __( 'Reminder: {session_title} on {session_date}', 'mizuki-booking' ),
			'reminder_body'        => self::default_reminder_body(),
			'reschedule_subject'   => __( 'Your booking has been rescheduled', 'mizuki-booking' ),
			'reschedule_body'      => self::default_reschedule_body(),
			'cancel_subject'       => __( 'Your booking has been cancelled', 'mizuki-booking' ),
			'cancel_body'          => self::default_cancel_body(),
			'pending_subject'      => __( 'We have received your registration - {session_title}', 'mizuki-booking' ),
			'pending_body'         => self::default_pending_body(),
			'approved_subject'     => __( 'Your place is confirmed - {session_title}', 'mizuki-booking' ),
			'approved_body'        => self::default_approved_body(),
			'declined_subject'     => __( 'About your registration - {session_title}', 'mizuki-booking' ),
			'declined_body'        => self::default_declined_body(),
			'welcome_subject'      => __( 'Your {studio_name} account', 'mizuki-booking' ),
			'welcome_body'         => self::default_welcome_body(),
			'moved_subject'        => __( 'Your class has moved - {class_type}', 'mizuki-booking' ),
			'moved_body'           => self::default_moved_body(),
		);
	}

	public static function default_moved_body() {
		return "Hi {student_name},\n\nWe have had to move your class. Your place is still reserved — you do not need to book again.\n\nClass: {class_type}\nWas: {old_session_date} at {old_session_time}\nNow: {session_date} at {session_time}\n\n{reason}\n\nIf the new time does not suit you, you can change it here:\n{manage_url}\n\nWith apologies for the change,\n{studio_name}";
	}

	public static function default_pending_body() {
		return "Hi {student_name},\n\nThank you for registering for {class_type}. We have your request and will confirm your place shortly.\n\nClass: {class_type}\nDate: {session_date}\nTime: {session_time}\n\nYou can check the status of your registration any time here:\n{dashboard_url}\n\n{studio_name}";
	}

	public static function default_approved_body() {
		return "Hi {student_name},\n\nGood news — your place is confirmed.\n\nClass: {class_type}\nDate: {session_date}\nTime: {session_time}\nDuration: {session_duration}\n\nManage or reschedule your booking here:\n{manage_url}\n\nSee you soon!\n{studio_name}";
	}

	public static function default_declined_body() {
		return "Hi {student_name},\n\nThank you for your interest in {class_type} on {session_date}.\n\nUnfortunately we are not able to confirm your place for this session. {decline_reason}\n\nPlease do have a look at our other available dates, or reply to this e-mail and we will help you find one.\n\n{studio_name}";
	}

	public static function default_welcome_body() {
		return "Hi {student_name},\n\nWelcome to {studio_name}. We have created an account for you so you can see your classes, reschedule them and check your course balance in one place.\n\nYour account: {dashboard_url}\nYour username: {student_email}\n\nSet your password here:\n{password_url}\n\n{studio_name}";
	}

	public static function default_confirm_body() {
		return "Hi {student_name},\n\nThank you for booking with {studio_name}. Your place is confirmed.\n\nClass: {class_type}\nSession: {session_title}\nDate: {session_date}\nTime: {session_time}\nDuration: {session_duration}\n\nManage or reschedule your booking here:\n{manage_url}\n\nSee you soon!\n{studio_name}";
	}

	public static function default_reminder_body() {
		return "Hi {student_name},\n\nThis is a friendly reminder about your upcoming class.\n\nClass: {class_type}\nSession: {session_title}\nDate: {session_date}\nTime: {session_time}\nDuration: {session_duration}\n\nManage your booking:\n{manage_url}\n\nSee you soon!\n{studio_name}";
	}

	public static function default_reschedule_body() {
		return "Hi {student_name},\n\nYour booking has been moved.\n\nPrevious: {old_session_date} at {old_session_time}\nNew: {session_date} at {session_time}\nClass: {class_type}\n\nManage your booking:\n{manage_url}\n\n{studio_name}";
	}

	public static function default_cancel_body() {
		return "Hi {student_name},\n\nYour booking for {class_type} on {session_date} at {session_time} has been cancelled.\n\nIf this was not intended, please contact us.\n\n{studio_name}";
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION_SETTINGS, array() ), self::default_settings() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Read all settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return wp_parse_args( (array) get_option( self::OPTION_SETTINGS, array() ), self::default_settings() );
	}

	/**
	 * Activation: build schema, seed class types, schedule cron.
	 */
	public static function activate() {
		self::create_tables();
		self::seed_class_types();
		MZK_Students::add_role();

		if ( false === get_option( self::OPTION_SETTINGS ) ) {
			add_option( self::OPTION_SETTINGS, self::default_settings() );
		}

		update_option( self::OPTION_DB_VERSION, MZK_DB_VERSION );

		MZK_Cron::schedule_events();

		if ( class_exists( 'MZK_Account' ) ) {
			MZK_Account::add_endpoints();
		}
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: clear cron only. Data is preserved.
	 */
	public static function deactivate() {
		MZK_Cron::clear_events();
		flush_rewrite_rules();
	}

	/**
	 * Run schema updates when the plugin files are newer than the stored DB version.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION_DB_VERSION ) === MZK_DB_VERSION ) {
			return;
		}
		self::create_tables();
		self::seed_class_types();
		if ( class_exists( 'MZK_Students' ) ) {
			MZK_Students::add_role();
		}
		update_option( self::OPTION_DB_VERSION, MZK_DB_VERSION );

		// The My Account endpoints are new in 2.0; their rewrite rules need one
		// flush after the upgrade or the tabs 404.
		if ( class_exists( 'MZK_Account' ) ) {
			MZK_Account::add_endpoints();
		}
		flush_rewrite_rules();
	}

	/**
	 * Create/patch tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$class_types = MZK_DB::class_types();
		$sessions    = MZK_DB::sessions();
		$templates   = MZK_DB::templates();
		$blackouts   = MZK_DB::blackouts();
		$bookings    = MZK_DB::bookings();
		$enrollments = MZK_DB::enrollments();
		$log         = MZK_DB::enrollment_log();

		$sql = array();

		$sql[] = "CREATE TABLE {$class_types} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(64) NOT NULL,
			name varchar(160) NOT NULL,
			colour varchar(16) NOT NULL DEFAULT '#3f827a',
			default_capacity smallint(5) unsigned NOT NULL DEFAULT 6,
			default_duration smallint(5) unsigned NOT NULL DEFAULT 120,
			course_based tinyint(1) NOT NULL DEFAULT 0,
			requires_enrollment tinyint(1) NOT NULL DEFAULT 0,
			reschedule_enabled tinyint(1) NOT NULL DEFAULT 1,
			reschedule_cutoff_hours int(11) NOT NULL DEFAULT 72,
			cancel_enabled tinyint(1) NOT NULL DEFAULT 1,
			cancel_cutoff_hours int(11) NOT NULL DEFAULT 72,
			max_reschedules smallint(5) unsigned NOT NULL DEFAULT 0,
			requires_approval tinyint(1) NOT NULL DEFAULT 0,
			summary varchar(255) NOT NULL DEFAULT '',
			price_note varchar(120) NOT NULL DEFAULT '',
			image_id bigint(20) unsigned NOT NULL DEFAULT 0,
			booking_url varchar(255) NOT NULL DEFAULT '',
			payment_mode varchar(20) NOT NULL DEFAULT 'free',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			description text NULL,
			sort_order smallint(5) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY active (active)
		) {$charset};";

		$sql[] = "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_type_id bigint(20) unsigned NOT NULL,
			template_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			session_date date NOT NULL,
			start_time time NOT NULL,
			duration_minutes smallint(5) unsigned NOT NULL DEFAULT 120,
			capacity smallint(5) unsigned NOT NULL DEFAULT 6,
			capacity_adjustment smallint(6) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'open',
			notes text NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY session_date (session_date),
			KEY class_type_id (class_type_id),
			KEY status (status),
			KEY date_class (session_date,class_type_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_type_id bigint(20) unsigned NOT NULL,
			label varchar(190) NOT NULL DEFAULT '',
			weekday tinyint(1) NOT NULL,
			start_time time NOT NULL,
			duration_minutes smallint(5) unsigned NOT NULL DEFAULT 120,
			capacity smallint(5) unsigned NOT NULL DEFAULT 6,
			valid_from date NULL,
			valid_until date NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY class_type_id (class_type_id),
			KEY weekday (weekday)
		) {$charset};";

		$sql[] = "CREATE TABLE {$blackouts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
			start_date date NOT NULL,
			end_date date NOT NULL,
			reason varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY start_date (start_date),
			KEY end_date (end_date)
		) {$charset};";

		$sql[] = "CREATE TABLE {$enrollments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			class_type_id bigint(20) unsigned NOT NULL,
			student_name varchar(190) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(64) NOT NULL DEFAULT '',
			sessions_total smallint(5) unsigned NOT NULL DEFAULT 0,
			start_date date NULL,
			expiry_date date NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			notes text NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY email (email),
			KEY user_id (user_id),
			KEY class_type_id (class_type_id),
			KEY status (status),
			KEY order_id (order_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			enrollment_id bigint(20) unsigned NOT NULL,
			delta_sessions smallint(6) NOT NULL DEFAULT 0,
			old_expiry date NULL,
			new_expiry date NULL,
			reason varchar(190) NOT NULL DEFAULT '',
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY enrollment_id (enrollment_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			class_type_id bigint(20) unsigned NOT NULL,
			enrollment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			student_name varchar(190) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(64) NOT NULL DEFAULT '',
			seats smallint(5) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'confirmed',
			source varchar(20) NOT NULL DEFAULT 'web',
			notes text NULL,
			manage_token varchar(64) NOT NULL DEFAULT '',
			reschedule_count smallint(5) unsigned NOT NULL DEFAULT 0,
			rescheduled_from bigint(20) unsigned NOT NULL DEFAULT 0,
			reminder_sent_at datetime NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			hold_expires_at datetime NULL,
			approved_at datetime NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			decline_reason varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY email (email),
			KEY user_id (user_id),
			KEY status (status),
			KEY enrollment_id (enrollment_id),
			KEY manage_token (manage_token),
			KEY order_id (order_id),
			KEY session_status (session_id,status),
			KEY enrollment_status (enrollment_id,status),
			KEY status_hold (status,hold_expires_at)
		) {$charset};";
		// NOTE: never put comments inside these CREATE TABLE strings. dbDelta()
		// parses them line by line as column and key definitions, so a comment
		// line silently corrupts the table it belongs to.

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Seed the four Mizuki class types on first install. Existing rows are left alone.
	 */
	public static function seed_class_types() {
		global $wpdb;
		$table = MZK_DB::class_types();
		$now   = current_time( 'mysql' );

		$seeds = array(
			array(
				'slug'                => 'fresh-flower',
				'name'                => 'Fresh Flower',
				'colour'              => '#3f827a',
				'default_capacity'    => 6,
				'default_duration'    => 120,
				'course_based'        => 0,
				'requires_enrollment' => 0,
				'reschedule_enabled'  => 1,
				// Restricted: no changes inside 3 days of the class.
				'reschedule_cutoff_hours' => 72,
				'cancel_enabled'          => 1,
				'cancel_cutoff_hours'     => 72,
				'sort_order'              => 10,
			),
			array(
				'slug'                => 'ikebana',
				'name'                => 'Ikebana',
				'colour'              => '#2d5778',
				'default_capacity'    => 6,
				'default_duration'    => 120,
				'course_based'        => 0,
				'requires_enrollment' => 0,
				'reschedule_enabled'  => 1,
				'reschedule_cutoff_hours' => 72,
				'cancel_enabled'          => 1,
				'cancel_cutoff_hours'     => 72,
				'sort_order'              => 20,
			),
			array(
				'slug'                => 'preserved-flower',
				'name'                => 'Preserved Flower',
				'colour'              => '#8fa9a5',
				'default_capacity'    => 6,
				'default_duration'    => 240,
				'course_based'        => 1,
				'requires_enrollment' => 1,
				'reschedule_enabled'  => 1,
				// Flexible: same-day changes blocked only inside 24h.
				'reschedule_cutoff_hours' => 24,
				'cancel_enabled'          => 1,
				'cancel_cutoff_hours'     => 24,
				'sort_order'              => 30,
			),
			array(
				'slug'                => 'ifda',
				'name'                => 'IFDA',
				'colour'              => '#55708a',
				'default_capacity'    => 6,
				'default_duration'    => 240,
				'course_based'        => 1,
				'requires_enrollment' => 1,
				'reschedule_enabled'  => 1,
				'reschedule_cutoff_hours' => 24,
				'cancel_enabled'          => 1,
				'cancel_cutoff_hours'     => 24,
				'sort_order'              => 40,
			),
		);

		$copy = self::seed_copy();

		foreach ( $seeds as $seed ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $seed['slug'] ) ); // phpcs:ignore WordPress.DB

			if ( $exists ) {
				// Fill in the classes-page copy for sites that installed before it
				// existed, without touching anything the studio has written.
				if ( isset( $copy[ $seed['slug'] ] ) ) {
					$wpdb->query( // phpcs:ignore WordPress.DB
						$wpdb->prepare(
							"UPDATE {$table} SET summary = %s WHERE id = %d AND (summary IS NULL OR summary = '')", // phpcs:ignore WordPress.DB
							$copy[ $seed['slug'] ],
							(int) $exists
						)
					);
				}
				continue;
			}

			if ( isset( $copy[ $seed['slug'] ] ) ) {
				$seed['summary'] = $copy[ $seed['slug'] ];
			}
			$seed['created_at'] = $now;
			$wpdb->insert( $table, $seed ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Starter one-liners for the classes page, so it is never blank.
	 * The studio can rewrite every one of these.
	 *
	 * @return array<string,string>
	 */
	public static function seed_copy() {
		return array(
			'fresh-flower'     => __( 'Work with seasonal blooms and take home an arrangement you made yourself. No experience needed.', 'mizuki-booking' ),
			'ikebana'          => __( 'The Japanese art of arranging flowers through balance, movement and space. A calm, unhurried class.', 'mizuki-booking' ),
			'preserved-flower' => __( 'A certified course in Korean-style preserved flower design, taken at your own pace over a set number of sessions.', 'mizuki-booking' ),
			'ifda'             => __( 'The official IFDA certification course, accredited by Korea IFDA and taught here in Singapore.', 'mizuki-booking' ),
		);
	}

	/**
	 * Drop every table and option. Called from uninstall.php only.
	 */
	public static function uninstall() {
		global $wpdb;

		foreach ( MZK_DB::all() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
		}

		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_DB_VERSION );
		delete_option( 'mzk_generated_pages' );
		delete_option( 'mzk_demo_content' );
		delete_transient( 'mzk_holds_swept' );

		// Students keep their accounts — deleting people's logins on an uninstall
		// would be destructive and unexpected. Only the role definition goes.
		if ( get_role( 'mzk_student' ) ) {
			remove_role( 'mzk_student' );
		}

		wp_clear_scheduled_hook( 'mzk_send_reminders' );
		wp_clear_scheduled_hook( 'mzk_daily_maintenance' );
	}
}
