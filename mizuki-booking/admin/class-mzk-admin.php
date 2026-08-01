<?php
/**
 * Admin: menus, form handling, notices.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Admin {

	const SLUG = 'mizuki-booking';

	/**
	 * Where to send the browser after handling a form. Set when a front-end
	 * screen posts a `mzk_return` field, so the studio stays on the front end.
	 *
	 * @var string
	 */
	private static $return_url = '';

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_mzk_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . MZK_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings shortcut on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Schedule', 'mizuki-booking' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Admin menu tree.
	 */
	public static function menu() {
		$cap = MZK_Utils::cap();

		// A count bubble on the menu, so registrations waiting for approval are
		// impossible to miss from anywhere in wp-admin.
		$waiting = count( MZK_Students::pending( 50 ) );
		$label   = __( 'Bookings', 'mizuki-booking' );
		if ( $waiting ) {
			$label .= ' <span class="update-plugins count-' . (int) $waiting . '"><span class="update-count">'
				. number_format_i18n( $waiting ) . '</span></span>';
		}

		add_menu_page(
			__( 'Mizuki Booking', 'mizuki-booking' ),
			$label,
			$cap,
			self::SLUG,
			array( __CLASS__, 'render_schedule' ),
			'dashicons-calendar-alt',
			26
		);

		$pages = array(
			self::SLUG              => __( 'Schedule', 'mizuki-booking' ),
			'mzk-setup'             => __( 'Setup', 'mizuki-booking' ),
			'mzk-sessions'          => __( 'Sessions', 'mizuki-booking' ),
			'mzk-bookings'          => __( 'Bookings', 'mizuki-booking' ),
			'mzk-enrollments'       => __( 'Course Packages', 'mizuki-booking' ),
			'mzk-blackouts'         => __( 'Blocked Dates', 'mizuki-booking' ),
			'mzk-classes'           => __( 'Classes & Rules', 'mizuki-booking' ),
			'mzk-settings'          => __( 'Settings', 'mizuki-booking' ),
		);

		$callbacks = array(
			self::SLUG        => 'render_schedule',
			'mzk-setup'       => 'render_setup',
			'mzk-sessions'    => 'render_sessions',
			'mzk-bookings'    => 'render_bookings',
			'mzk-enrollments' => 'render_enrollments',
			'mzk-blackouts'   => 'render_blackouts',
			'mzk-classes'     => 'render_classes',
			'mzk-settings'    => 'render_settings',
		);

		foreach ( $pages as $slug => $title ) {
			add_submenu_page(
				self::SLUG,
				$title,
				$title,
				$cap,
				$slug,
				array( __CLASS__, $callbacks[ $slug ] )
			);
		}
	}

	/**
	 * Enqueue admin assets on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'mizuki-booking' ) && false === strpos( $hook, 'mzk-' ) ) {
			return;
		}
		wp_enqueue_style( 'mzk-admin', MZK_URL . 'assets/css/mzk-admin.css', array(), MZK_VERSION );

		// The class editor picks a photo from the media library. wp_enqueue_media()
		// must run first, and mzk-admin must depend on media-editor — otherwise our
		// script executes before wp.media exists and the button does nothing.
		$deps = array();
		if ( false !== strpos( $hook, 'mzk-classes' ) ) {
			wp_enqueue_media();
			$deps[] = 'media-editor';
		}

		wp_enqueue_script( 'mzk-admin', MZK_URL . 'assets/js/mzk-admin.js', $deps, MZK_VERSION, true );
		wp_localize_script(
			'mzk-admin',
			'MZK_ADMIN',
			array(
				'confirmDelete'  => __( 'Delete this permanently?', 'mizuki-booking' ),
				'confirmCancel'  => __( 'Cancel this booking? The student will be notified.', 'mizuki-booking' ),
				'confirmSession' => __( 'Cancel this session? Students booked on it keep their booking until you move or cancel them.', 'mizuki-booking' ),
			)
		);
	}

	/* ------------------------------------------------------------- notices */

	/**
	 * Queue an admin notice for the current user.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message text.
	 */
	public static function add_notice( $type, $message ) {
		$key      = 'mzk_notices_' . get_current_user_id();
		$existing = (array) get_transient( $key );
		$existing[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( $key, $existing, 60 );
	}

	/**
	 * Print and clear queued notices.
	 */
	public static function notices() {
		$key    = 'mzk_notices_' . get_current_user_id();
		$queued = get_transient( $key );
		if ( ! $queued ) {
			return;
		}
		delete_transient( $key );
		foreach ( (array) $queued as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Redirect back to a plugin screen.
	 *
	 * @param string $page  Page slug.
	 * @param array  $extra Extra query args.
	 */
	private static function redirect( $page, $extra = array() ) {
		if ( self::$return_url ) {
			wp_safe_redirect( self::$return_url );
			exit;
		}
		$url = add_query_arg( array_merge( array( 'page' => $page ), $extra ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Read and clear the queued notices. Used by the front-end manager, which
	 * cannot rely on admin_notices.
	 *
	 * @return array
	 */
	public static function take_notices() {
		$key    = 'mzk_notices_' . get_current_user_id();
		$queued = (array) get_transient( $key );
		delete_transient( $key );
		return $queued;
	}

	/**
	 * Translate a WP_Error or success into a queued notice.
	 *
	 * @param mixed  $result  Result value.
	 * @param string $success Success message.
	 * @return bool Whether the operation succeeded.
	 */
	private static function report( $result, $success ) {
		if ( is_wp_error( $result ) ) {
			self::add_notice( 'error', $result->get_error_message() );
			return false;
		}
		self::add_notice( 'success', $success );
		return true;
	}

	/* -------------------------------------------------------- form handling */

	/**
	 * Central dispatcher for every admin form and row action.
	 */
	public static function handle() {
		MZK_Utils::require_cap();

		$action = isset( $_REQUEST['mzk_do'] ) ? sanitize_key( wp_unslash( $_REQUEST['mzk_do'] ) ) : '';
		check_admin_referer( 'mzk_' . $action );

		$post = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- individual fields are sanitised downstream.

		// Front-end screens pass where to come back to. wp_safe_redirect() keeps
		// this on-site, so it cannot be pointed at another domain.
		if ( ! empty( $post['mzk_return'] ) ) {
			self::$return_url = esc_url_raw( $post['mzk_return'] );
		}

		switch ( $action ) {

			/* ---- registration approvals ---- */

			case 'approve_booking':
				$result = MZK_Students::approve( (int) ( $post['id'] ?? 0 ) );
				self::report( $result, __( 'Registration approved — the student has been told.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings', array( 'status' => 'awaiting_approval' ) );
				break;

			case 'decline_booking':
				$result = MZK_Students::decline( (int) ( $post['id'] ?? 0 ), $post['reason'] ?? '' );
				self::report( $result, __( 'Registration declined — the place is free again.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings', array( 'status' => 'awaiting_approval' ) );
				break;

			/* ---- setup wizard ---- */

			case 'create_pages':
				$made = MZK_Setup::create_pages();
				self::add_notice(
					'success',
					sprintf(
						/* translators: 1: pages created, 2: pages already present. */
						__( 'Pages ready: %1$d created, %2$d already existed. Their shortcodes are in place.', 'mizuki-booking' ),
						$made['created'],
						$made['existing']
					)
				);
				self::redirect( 'mzk-setup' );
				break;

			case 'install_demo':
				$counts = MZK_Setup::install_demo();
				if ( isset( $counts['error'] ) ) {
					self::add_notice( 'error', $counts['error'] );
				} else {
					self::add_notice(
						'success',
						sprintf(
							/* translators: 1: sessions, 2: bookings, 3: course students. */
							__( 'Demo content added: %1$d sessions, %2$d bookings and %3$d course students. Remove it any time.', 'mizuki-booking' ),
							$counts['sessions'],
							$counts['bookings'],
							$counts['enrollments']
						)
					);
				}
				self::redirect( 'mzk-setup' );
				break;

			case 'remove_demo':
				$counts = MZK_Setup::remove_demo();
				self::add_notice(
					'success',
					sprintf(
						/* translators: 1: sessions, 2: bookings. */
						__( 'Demo content removed: %1$d sessions and %2$d bookings deleted.', 'mizuki-booking' ),
						$counts['sessions'],
						$counts['bookings']
					)
				);
				self::redirect( 'mzk-setup' );
				break;

			/* ---- sessions ---- */

			case 'save_session':
				$result = MZK_Sessions::save(
					array(
						'id'                  => (int) ( $post['id'] ?? 0 ),
						'class_type_id'       => (int) ( $post['class_type_id'] ?? 0 ),
						'title'               => $post['title'] ?? '',
						'session_date'        => $post['session_date'] ?? '',
						'start_time'          => $post['start_time'] ?? '',
						'duration_minutes'    => (int) ( $post['duration_minutes'] ?? 0 ),
						'capacity'            => (int) ( $post['capacity'] ?? 0 ),
						'capacity_adjustment' => (int) ( $post['capacity_adjustment'] ?? 0 ),
						'status'              => $post['status'] ?? 'open',
						'notes'               => $post['notes'] ?? '',
					)
				);
				self::report( $result, __( 'Session saved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-sessions', array( 'from' => sanitize_text_field( $post['return_from'] ?? '' ) ) );
				break;

			case 'adjust_capacity':
				$result = MZK_Sessions::adjust_capacity( (int) ( $post['id'] ?? 0 ), (int) ( $post['delta'] ?? 0 ) );
				self::report( $result, __( 'Participant limit updated.', 'mizuki-booking' ) );
				self::redirect( sanitize_key( $post['return_page'] ?? 'mzk-sessions' ) );
				break;

			case 'session_status':
				$result = MZK_Sessions::set_status( (int) ( $post['id'] ?? 0 ), sanitize_key( $post['status'] ?? 'open' ) );
				self::report( $result, __( 'Session status updated.', 'mizuki-booking' ) );
				self::redirect( sanitize_key( $post['return_page'] ?? 'mzk-sessions' ) );
				break;

			case 'delete_session':
				$result = MZK_Sessions::delete( (int) ( $post['id'] ?? 0 ), ! empty( $post['force'] ) );
				self::report( $result, __( 'Session deleted.', 'mizuki-booking' ) );
				self::redirect( 'mzk-sessions' );
				break;

			case 'save_template':
				$result = MZK_Sessions::save_template(
					array(
						'id'               => (int) ( $post['id'] ?? 0 ),
						'class_type_id'    => (int) ( $post['class_type_id'] ?? 0 ),
						'label'            => $post['label'] ?? '',
						'weekday'          => (int) ( $post['weekday'] ?? 0 ),
						'start_time'       => $post['start_time'] ?? '',
						'duration_minutes' => (int) ( $post['duration_minutes'] ?? 0 ),
						'capacity'         => (int) ( $post['capacity'] ?? 0 ),
						'valid_from'       => $post['valid_from'] ?? '',
						'valid_until'      => $post['valid_until'] ?? '',
						'active'           => ! empty( $post['active'] ),
					)
				);
				self::report( $result, __( 'Weekly session saved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-sessions', array( 'tab' => 'templates' ) );
				break;

			case 'delete_template':
				MZK_Sessions::delete_template( (int) ( $post['id'] ?? 0 ) );
				self::add_notice( 'success', __( 'Weekly session removed. Sessions already on the calendar were kept.', 'mizuki-booking' ) );
				self::redirect( 'mzk-sessions', array( 'tab' => 'templates' ) );
				break;

			case 'create_products':
				$made = MZK_Setup::create_products();
				if ( $made['created'] ) {
					self::add_notice(
						'success',
						sprintf(
							/* translators: 1: number created, 2: class names. */
							__( '%1$d draft product(s) created and linked: %2$s. Set a price on each, then publish it.', 'mizuki-booking' ),
							$made['created'],
							implode( ', ', $made['names'] )
						)
					);
				} else {
					self::add_notice(
						'info',
						__( 'Nothing to create — every paid class and course already has a product.', 'mizuki-booking' )
					);
				}
				self::redirect( 'mzk-setup' );
				break;

			case 'repair_tables':
				$result = MZK_Setup::repair_tables();
				if ( $result['ok'] ) {
					self::add_notice( 'success', __( 'Database checked — all tables are present.', 'mizuki-booking' ) );
				} else {
					self::add_notice(
						'error',
						sprintf(
							/* translators: %s: table names. */
							__( 'These tables could not be created: %s. Your database user may not have permission to create tables — ask your host.', 'mizuki-booking' ),
							implode( ', ', $result['missing'] )
						)
					);
				}
				self::redirect( 'mzk-setup' );
				break;

			case 'generate':
				$stats = MZK_Sessions::generate(
					sanitize_text_field( $post['from'] ?? '' ),
					sanitize_text_field( $post['to'] ?? '' ),
					(int) ( $post['template_id'] ?? 0 )
				);

				if ( ! empty( $stats['error'] ) ) {
					self::add_notice(
						'error',
						sprintf(
							/* translators: %s: database error. */
							__( 'The schedule could not be generated. The database said: %s', 'mizuki-booking' ),
							$stats['error']
						)
					);
					self::redirect( 'mzk-sessions', array( 'tab' => 'templates' ) );
					break;
				}

				if ( ! $stats['created'] && ! $stats['skipped'] ) {
					self::add_notice(
						'warning',
						__( 'No sessions were created. Check that you have at least one active weekly session, and that the dates you chose include the right days of the week.', 'mizuki-booking' )
					);
					self::redirect( 'mzk-sessions', array( 'tab' => 'templates' ) );
					break;
				}

				self::add_notice(
					'success',
					sprintf(
						/* translators: 1: created, 2: skipped, 3: blocked. */
						__( 'Schedule generated: %1$d sessions created, %2$d already existed, %3$d skipped on blocked dates.', 'mizuki-booking' ),
						$stats['created'],
						$stats['skipped'],
						$stats['blocked']
					)
				);
				self::redirect( 'mzk-sessions', array( 'tab' => 'templates' ) );
				break;

			case 'extend_horizon':
				$stats = MZK_Sessions::ensure_horizon();
				self::add_notice(
					'success',
					sprintf(
						/* translators: %d: number of sessions created. */
						__( 'Schedule topped up: %d new sessions created.', 'mizuki-booking' ),
						$stats['created']
					)
				);
				self::redirect( self::SLUG );
				break;

			/* ---- bookings ---- */

			case 'save_booking':
				$id = (int) ( $post['id'] ?? 0 );
				if ( $id ) {
					$result = MZK_Bookings::set_status( $id, sanitize_key( $post['status'] ?? 'confirmed' ) );
					self::report( $result, __( 'Booking updated.', 'mizuki-booking' ) );
				} else {
					$result = MZK_Bookings::create(
						array(
							'session_id'      => (int) ( $post['session_id'] ?? 0 ),
							'student_name'    => $post['student_name'] ?? '',
							'email'           => $post['email'] ?? '',
							'phone'           => $post['phone'] ?? '',
							'notes'           => $post['notes'] ?? '',
							'seats'           => (int) ( $post['seats'] ?? 1 ),
							'source'          => sanitize_key( $post['source'] ?? 'admin' ),
							'allow_overbook'  => ! empty( $post['allow_overbook'] ),
							'allow_duplicate' => ! empty( $post['allow_overbook'] ),
							'skip_emails'     => ! empty( $post['skip_emails'] ),
						)
					);
					self::report( $result, __( 'Booking added.', 'mizuki-booking' ) );
				}
				self::redirect( 'mzk-bookings' );
				break;

			case 'booking_status':
				$result = MZK_Bookings::set_status( (int) ( $post['id'] ?? 0 ), sanitize_key( $post['status'] ?? '' ) );
				self::report( $result, __( 'Booking updated.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings', self::preserve_filters( $post ) );
				break;

			case 'cancel_booking':
				$result = MZK_Bookings::cancel(
					(int) ( $post['id'] ?? 0 ),
					array(
						'by_admin'    => true,
						'skip_emails' => ! empty( $post['skip_emails'] ),
						'reason'      => $post['reason'] ?? '',
					)
				);
				self::report( $result, __( 'Booking cancelled.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings', self::preserve_filters( $post ) );
				break;

			case 'move_booking':
				$result = MZK_Bookings::reschedule(
					(int) ( $post['id'] ?? 0 ),
					(int) ( $post['session_id'] ?? 0 ),
					array(
						'by_admin'    => true,
						'skip_emails' => ! empty( $post['skip_emails'] ),
					)
				);
				self::report( $result, __( 'Booking moved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings', self::preserve_filters( $post ) );
				break;

			case 'delete_booking':
				MZK_Bookings::delete( (int) ( $post['id'] ?? 0 ) );
				self::add_notice( 'success', __( 'Booking deleted.', 'mizuki-booking' ) );
				self::redirect( 'mzk-bookings' );
				break;

			case 'resend_confirmation':
				$sent = MZK_Mailer::send_confirmation( (int) ( $post['id'] ?? 0 ) );
				self::add_notice(
					$sent ? 'success' : 'error',
					$sent ? __( 'Confirmation e-mail resent.', 'mizuki-booking' ) : __( 'The e-mail could not be sent.', 'mizuki-booking' )
				);
				self::redirect( 'mzk-bookings', self::preserve_filters( $post ) );
				break;

			/* ---- enrollments ---- */

			case 'save_enrollment':
				$result = MZK_Enrollments::save(
					array(
						'id'             => (int) ( $post['id'] ?? 0 ),
						'class_type_id'  => (int) ( $post['class_type_id'] ?? 0 ),
						'student_name'   => $post['student_name'] ?? '',
						'email'          => $post['email'] ?? '',
						'phone'          => $post['phone'] ?? '',
						'sessions_total' => (int) ( $post['sessions_total'] ?? 0 ),
						'start_date'     => $post['start_date'] ?? '',
						'expiry_date'    => $post['expiry_date'] ?? '',
						'status'         => sanitize_key( $post['status'] ?? 'active' ),
						'notes'          => $post['notes'] ?? '',
					)
				);
				self::report( $result, __( 'Course package saved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-enrollments' );
				break;

			case 'extend_enrollment':
				$result = MZK_Enrollments::extend(
					(int) ( $post['id'] ?? 0 ),
					(int) ( $post['add_sessions'] ?? 0 ),
					$post['new_expiry'] ?? '',
					$post['reason'] ?? ''
				);
				self::report( $result, __( 'Course package extended.', 'mizuki-booking' ) );
				self::redirect( 'mzk-enrollments', array( 'edit' => (int) ( $post['id'] ?? 0 ) ) );
				break;

			case 'delete_enrollment':
				MZK_Enrollments::delete( (int) ( $post['id'] ?? 0 ) );
				self::add_notice( 'success', __( 'Course package deleted.', 'mizuki-booking' ) );
				self::redirect( 'mzk-enrollments' );
				break;

			/* ---- blackouts ---- */

			case 'save_blackout':
				$result = MZK_Blackouts::save(
					array(
						'id'            => (int) ( $post['id'] ?? 0 ),
						'class_type_id' => (int) ( $post['class_type_id'] ?? 0 ),
						'start_date'    => $post['start_date'] ?? '',
						'end_date'      => $post['end_date'] ?? '',
						'reason'        => $post['reason'] ?? '',
					)
				);
				if ( self::report( $result, __( 'Blocked dates saved. Sessions in that period are now hidden from students.', 'mizuki-booking' ) ) ) {
					$affected = MZK_Blackouts::affected_bookings( (int) $result );
					if ( $affected ) {
						self::add_notice(
							'warning',
							sprintf(
								/* translators: %d: number of bookings. */
								_n(
									'Heads up: %d existing booking falls inside these dates. Move or cancel it from the Bookings screen.',
									'Heads up: %d existing bookings fall inside these dates. Move or cancel them from the Bookings screen.',
									count( $affected ),
									'mizuki-booking'
								),
								count( $affected )
							)
						);
					}
				}
				self::redirect( 'mzk-blackouts' );
				break;

			case 'delete_blackout':
				MZK_Blackouts::delete( (int) ( $post['id'] ?? 0 ) );
				self::add_notice( 'success', __( 'Blocked dates removed. Re-open any sessions you still want to run.', 'mizuki-booking' ) );
				self::redirect( 'mzk-blackouts' );
				break;

			/* ---- class types ---- */

			case 'save_class':
				$result = MZK_Class_Types::save(
					array(
						'id'                      => (int) ( $post['id'] ?? 0 ),
						'name'                    => $post['name'] ?? '',
						'slug'                    => $post['slug'] ?? '',
						'colour'                  => $post['colour'] ?? '',
						'default_capacity'        => (int) ( $post['default_capacity'] ?? 6 ),
						'default_duration'        => (int) ( $post['default_duration'] ?? 120 ),
						'reschedule_enabled'      => ! empty( $post['reschedule_enabled'] ),
						'reschedule_cutoff_hours' => (int) ( $post['reschedule_cutoff_hours'] ?? 72 ),
						'cancel_enabled'          => ! empty( $post['cancel_enabled'] ),
						'cancel_cutoff_hours'     => (int) ( $post['cancel_cutoff_hours'] ?? 72 ),
						'max_reschedules'         => (int) ( $post['max_reschedules'] ?? 0 ),
						'requires_approval'       => ! empty( $post['requires_approval'] ),
						'description'             => $post['description'] ?? '',
						'summary'                 => $post['summary'] ?? '',
						'price_note'              => $post['price_note'] ?? '',
						'image_id'                => (int) ( $post['image_id'] ?? 0 ),
						'booking_url'             => $post['booking_url'] ?? '',
						'payment_mode'            => sanitize_key( $post['payment_mode'] ?? 'free' ),
						'product_id'              => (int) ( $post['product_id'] ?? 0 ),
						// "Course" implies a package is required; keep the two in step
						// so the booking gate and the UI can never disagree.
						'requires_enrollment'     => 'package' === ( $post['payment_mode'] ?? '' )
							? true
							: ! empty( $post['requires_enrollment'] ),
						'course_based'            => 'package' === ( $post['payment_mode'] ?? '' )
							? true
							: ! empty( $post['course_based'] ),
						'sort_order'              => (int) ( $post['sort_order'] ?? 0 ),
						'active'                  => ! empty( $post['active'] ),
					)
				);
				self::report( $result, __( 'Class saved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-classes' );
				break;

			case 'delete_class':
				$result = MZK_Class_Types::delete( (int) ( $post['id'] ?? 0 ) );
				self::report( $result, __( 'Class deleted.', 'mizuki-booking' ) );
				self::redirect( 'mzk-classes' );
				break;

			/* ---- settings ---- */

			case 'save_settings':
				$settings = MZK_Install::get_settings();
				$fields   = array(
					'studio_name'      => 'text',
					'admin_email'      => 'email',
					'confirm_subject'  => 'text',
					'reminder_subject' => 'text',
					'reschedule_subject' => 'text',
					'cancel_subject'   => 'text',
					'confirm_body'     => 'textarea',
					'reminder_body'    => 'textarea',
					'reschedule_body'  => 'textarea',
					'cancel_body'      => 'textarea',
				);
				foreach ( $fields as $key => $type ) {
					if ( ! isset( $post[ $key ] ) ) {
						continue;
					}
					$settings[ $key ] = 'textarea' === $type
						? sanitize_textarea_field( $post[ $key ] )
						: ( 'email' === $type ? sanitize_email( $post[ $key ] ) : sanitize_text_field( $post[ $key ] ) );
				}
				$settings['months_ahead']         = max( 2, (int) ( $post['months_ahead'] ?? 3 ) );
				$settings['reminder_days_before'] = max( 0, (int) ( $post['reminder_days_before'] ?? 2 ) );
				$settings['reminder_hour']        = max( 0, min( 23, (int) ( $post['reminder_hour'] ?? 9 ) ) );
				$settings['notify_admin']         = empty( $post['notify_admin'] ) ? 0 : 1;
				$settings['require_phone']        = empty( $post['require_phone'] ) ? 0 : 1;
				// The payment fields only render when WooCommerce is active, so leave
				// them untouched otherwise rather than resetting them to defaults.
				if ( class_exists( 'WooCommerce' ) ) {
					$settings['woo_enabled']          = empty( $post['woo_enabled'] ) ? 0 : 1;
					$settings['woo_hold_minutes']     = max( 5, min( 1440, (int) ( $post['woo_hold_minutes'] ?? 45 ) ) );
					$settings['woo_confirm_on']       = in_array( $post['woo_confirm_on'] ?? 'processing', array( 'processing', 'completed' ), true )
						? $post['woo_confirm_on']
						: 'processing';
					$settings['woo_package_validity'] = max( 0, min( 60, (int) ( $post['woo_package_validity'] ?? 12 ) ) );
				}
				$settings['booking_page_id']      = (int) ( $post['booking_page_id'] ?? 0 );
				$settings['manage_page_id']       = (int) ( $post['manage_page_id'] ?? 0 );

				// E-mail delivery.
				$settings['mail_provider']   = in_array( $post['mail_provider'] ?? 'wp', array( 'wp', 'resend' ), true )
					? $post['mail_provider']
					: 'wp';
				$settings['mail_from_name']  = sanitize_text_field( $post['mail_from_name'] ?? '' );
				$settings['mail_from_email'] = sanitize_email( $post['mail_from_email'] ?? '' );
				$settings['mail_reply_to']   = sanitize_email( $post['mail_reply_to'] ?? '' );
				$settings['mail_fallback']   = empty( $post['mail_fallback'] ) ? 0 : 1;
				$settings['mail_log']        = empty( $post['mail_log'] ) ? 0 : 1;

				// Only overwrite the API key when a new one is actually typed, so
				// re-saving the page cannot wipe it with the masked placeholder.
				$new_key = trim( (string) ( $post['resend_api_key'] ?? '' ) );
				if ( '' !== $new_key && false === strpos( $new_key, '•' ) ) {
					$settings['resend_api_key'] = sanitize_text_field( $new_key );
				}

				update_option( MZK_Install::OPTION_SETTINGS, $settings );
				self::add_notice( 'success', __( 'Settings saved.', 'mizuki-booking' ) );
				self::redirect( 'mzk-settings' );
				break;

			case 'send_test_email':
				$sent = MZK_Mailer::send_test(
					sanitize_key( $post['template'] ?? 'confirm' ),
					sanitize_email( $post['to'] ?? '' )
				);
				if ( is_wp_error( $sent ) ) {
					self::add_notice( 'error', $sent->get_error_message() );
				} else {
					self::add_notice(
						'success',
						__( 'Test e-mail sent. If it does not arrive, check the delivery log below and your spam folder.', 'mizuki-booking' )
					);
				}
				self::redirect( 'mzk-settings' );
				break;

			case 'verify_resend':
				$check = MZK_Resend::verify();
				if ( is_wp_error( $check ) ) {
					self::add_notice( 'error', $check->get_error_message() );
				} else {
					self::add_notice( 'success', __( 'Resend is connected and your sending domain is verified.', 'mizuki-booking' ) );
				}
				self::redirect( 'mzk-settings' );
				break;

			case 'clear_mail_log':
				MZK_Mailer::clear_log();
				self::add_notice( 'success', __( 'Delivery log cleared.', 'mizuki-booking' ) );
				self::redirect( 'mzk-settings' );
				break;

			case 'run_reminders':
				$sent = MZK_Cron::run_reminders( true );
				self::add_notice(
					'success',
					sprintf(
						/* translators: %d: number of reminders. */
						__( 'Reminder run finished: %d e-mail(s) sent.', 'mizuki-booking' ),
						$sent
					)
				);
				self::redirect( 'mzk-settings' );
				break;

			default:
				self::add_notice( 'error', __( 'Unknown action.', 'mizuki-booking' ) );
				self::redirect( self::SLUG );
		}
	}

	/**
	 * Keep list filters across a row action redirect.
	 *
	 * @param array $post Request data.
	 * @return array
	 */
	private static function preserve_filters( $post ) {
		$keep = array();
		foreach ( array( 'status', 'class_type', 'from', 'to', 's', 'paged' ) as $key ) {
			if ( ! empty( $post[ 'f_' . $key ] ) ) {
				$keep[ $key ] = sanitize_text_field( $post[ 'f_' . $key ] );
			}
		}
		return $keep;
	}

	/* --------------------------------------------------------------- views */

	/**
	 * Render a view file.
	 *
	 * @param string $view View slug.
	 * @param array  $vars Variables extracted into the view.
	 */
	private static function view( $view, $vars = array() ) {
		MZK_Utils::require_cap();
		$file = MZK_PATH . 'admin/views/' . $view . '.php';
		if ( ! file_exists( $file ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars );
		include $file;
	}

	/**
	 * Hidden fields shared by every admin form.
	 *
	 * @param string $action Action slug.
	 */
	public static function form_fields( $action ) {
		echo '<input type="hidden" name="action" value="mzk_action" />';
		echo '<input type="hidden" name="mzk_do" value="' . esc_attr( $action ) . '" />';
		wp_nonce_field( 'mzk_' . $action );
	}

	/**
	 * URL for a one-click row action.
	 *
	 * @param string $action Action slug.
	 * @param array  $args   Extra query args.
	 * @return string
	 */
	public static function action_url( $action, $args = array() ) {
		$url = add_query_arg(
			array_merge(
				array(
					'action' => 'mzk_action',
					'mzk_do' => $action,
				),
				$args
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'mzk_' . $action );
	}

	public static function render_schedule() {
		self::view( 'schedule' );
	}

	public static function render_setup() {
		self::view( 'setup' );
	}

	public static function render_sessions() {
		self::view( 'sessions' );
	}

	public static function render_bookings() {
		self::view( 'bookings' );
	}

	public static function render_enrollments() {
		self::view( 'enrollments' );
	}

	public static function render_blackouts() {
		self::view( 'blackouts' );
	}

	public static function render_classes() {
		self::view( 'classes' );
	}

	public static function render_settings() {
		self::view( 'settings' );
	}
}
