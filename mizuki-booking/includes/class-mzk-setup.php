<?php
/**
 * One-click setup: generates every page the plugin needs with its shortcode
 * already in place, and can install (and later remove) demo content so the
 * studio can see a working calendar straight away.
 *
 * Everything it creates is tracked, so "remove demo content" takes the site
 * back to exactly where it started.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Setup {

	const OPTION_PAGES = 'mzk_generated_pages';
	const OPTION_DEMO  = 'mzk_demo_content';

	/**
	 * The pages the plugin generates.
	 *
	 * Each entry: setting key it fills, page title, shortcode, and a short
	 * description shown in the setup screen.
	 *
	 * @return array<string,array>
	 */
	public static function pages() {
		return array(
			'booking_page_id'   => array(
				'title'     => __( 'Book a Class', 'mizuki-booking' ),
				'slug'      => 'book-a-class',
				'shortcode' => '[mizuki_calendar]',
				'intro'     => __( 'Choose a date to see the sessions available. Your place is confirmed by e-mail.', 'mizuki-booking' ),
				'desc'      => __( 'The main calendar. Students pick a date and book.', 'mizuki-booking' ),
			),
			'login_page_id'     => array(
				'title'     => __( 'Student Login', 'mizuki-booking' ),
				'slug'      => 'student-login',
				'shortcode' => '[mizuki_login]',
				'intro'     => '',
				'desc'      => __( 'Log in, or register a new student account.', 'mizuki-booking' ),
			),
			'dashboard_page_id' => array(
				'title'     => __( 'My Classes', 'mizuki-booking' ),
				'slug'      => 'my-classes',
				'shortcode' => '[mizuki_dashboard]',
				'intro'     => '',
				'desc'      => __( 'The student area: upcoming classes, course balance, details.', 'mizuki-booking' ),
			),
			'manage_page_id'    => array(
				'title'     => __( 'Manage My Booking', 'mizuki-booking' ),
				'slug'      => 'manage-booking',
				'shortcode' => '[mizuki_my_bookings]',
				'intro'     => '',
				'desc'      => __( 'Where confirmation e-mails link to, so students can reschedule without logging in.', 'mizuki-booking' ),
			),
			'studio_page_id'    => array(
				'title'     => __( 'Studio Manager', 'mizuki-booking' ),
				'slug'      => 'studio-manager',
				'shortcode' => '[mizuki_manage]',
				'intro'     => '',
				'desc'      => __( 'Front-end control panel — add sessions, approve registrations, block dates. Only visible to you.', 'mizuki-booking' ),
			),
		);
	}

	/**
	 * Create any missing page and store its id in the settings.
	 *
	 * Existing pages are reused rather than duplicated, so this is safe to press
	 * more than once.
	 *
	 * @return array{created:int,existing:int,pages:array}
	 */
	public static function create_pages() {
		$settings = MZK_Install::get_settings();
		$tracked  = (array) get_option( self::OPTION_PAGES, array() );
		$result   = array(
			'created'  => 0,
			'existing' => 0,
			'pages'    => array(),
		);

		foreach ( self::pages() as $key => $page ) {
			$existing_id = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;

			// Still there and not in the bin? Leave it alone.
			if ( $existing_id && 'page' === get_post_type( $existing_id ) && 'trash' !== get_post_status( $existing_id ) ) {
				++$result['existing'];
				$result['pages'][ $key ] = $existing_id;
				continue;
			}

			// A page with the same shortcode may already exist from an earlier run.
			$found = self::find_page_with_shortcode( $page['shortcode'] );
			if ( $found ) {
				$settings[ $key ]        = $found;
				$tracked[ $key ]         = $found;
				$result['pages'][ $key ] = $found;
				++$result['existing'];
				continue;
			}

			$content = '';
			if ( $page['intro'] ) {
				$content .= '<!-- wp:paragraph --><p>' . esc_html( $page['intro'] ) . '</p><!-- /wp:paragraph -->' . "\n\n";
			}
			$content .= '<!-- wp:shortcode -->' . $page['shortcode'] . '<!-- /wp:shortcode -->';

			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_author'  => get_current_user_id(),
					'comment_status' => 'closed',
					'ping_status'  => 'closed',
				)
			);

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				continue;
			}

			$settings[ $key ]        = (int) $page_id;
			$tracked[ $key ]         = (int) $page_id;
			$result['pages'][ $key ] = (int) $page_id;
			++$result['created'];
		}

		update_option( MZK_Install::OPTION_SETTINGS, $settings );
		update_option( self::OPTION_PAGES, $tracked );

		flush_rewrite_rules();

		return $result;
	}

	/**
	 * Look for a published page already containing a shortcode.
	 *
	 * @param string $shortcode Shortcode text, e.g. '[mizuki_calendar]'.
	 * @return int Page id or 0.
	 */
	private static function find_page_with_shortcode( $shortcode ) {
		global $wpdb;

		$tag  = trim( $shortcode, '[]' );
		$like = '%' . $wpdb->esc_like( '[' . $tag ) . '%';

		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s
				 ORDER BY ID ASC LIMIT 1",
				$like
			)
		);

		return $id ? (int) $id : 0;
	}

	/**
	 * Which pages exist right now, for the setup screen.
	 *
	 * @return array
	 */
	public static function status() {
		$settings = MZK_Install::get_settings();
		$out      = array();

		foreach ( self::pages() as $key => $page ) {
			$id     = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			$exists = $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id );

			$out[ $key ] = array(
				'title'     => $page['title'],
				'shortcode' => $page['shortcode'],
				'desc'      => $page['desc'],
				'id'        => $exists ? $id : 0,
				'url'       => $exists ? get_permalink( $id ) : '',
				'edit'      => $exists ? get_edit_post_link( $id, 'raw' ) : '',
			);
		}

		return $out;
	}

	/**
	 * Is the plugin ready to take bookings?
	 *
	 * @return array List of outstanding things, empty when ready.
	 */
	public static function outstanding() {
		$todo = array();

		$status = self::status();
		foreach ( $status as $page ) {
			if ( ! $page['id'] ) {
				$todo[] = sprintf(
					/* translators: %s: page name. */
					__( 'The “%s” page has not been created yet.', 'mizuki-booking' ),
					$page['title']
				);
			}
		}

		if ( ! MZK_Sessions::templates( true ) ) {
			$todo[] = __( 'No weekly pattern yet — add the classes you run each week.', 'mizuki-booking' );
		}

		$upcoming = MZK_Sessions::query(
			array(
				'from'          => MZK_Utils::today(),
				'to'            => gmdate( 'Y-m-d', strtotime( MZK_Utils::today() . ' +2 months' ) ),
				'status'        => 'open',
				'only_bookable' => true,
				'limit'         => 1,
			)
		);
		if ( ! $upcoming ) {
			$todo[] = __( 'No bookable sessions in the next 2 months — generate the schedule.', 'mizuki-booking' );
		}

		if ( 'Asia/Singapore' !== wp_timezone_string() && '+08:00' !== wp_timezone_string() ) {
			$todo[] = sprintf(
				/* translators: %s: current timezone. */
				__( 'Site timezone is “%s”. Set it to Singapore so the 3-day rule falls on the right day.', 'mizuki-booking' ),
				wp_timezone_string()
			);
		}

		return $todo;
	}

	/* --------------------------------------------------------- demo data */

	/**
	 * Has demo content been installed?
	 *
	 * @return bool
	 */
	public static function has_demo() {
		$demo = (array) get_option( self::OPTION_DEMO, array() );
		return ! empty( $demo['sessions'] ) || ! empty( $demo['bookings'] );
	}

	/**
	 * Install a realistic set of demo content: a weekly pattern, eight weeks of
	 * sessions, a studio closure, two course students and a handful of bookings
	 * (including one waiting for approval), so every screen has something in it.
	 *
	 * @return array Summary counts.
	 */
	public static function install_demo() {
		$demo = array(
			'templates'   => array(),
			'sessions'    => array(),
			'bookings'    => array(),
			'enrollments' => array(),
			'blackouts'   => array(),
		);

		$fresh     = MZK_Class_Types::get_by_slug( 'fresh-flower' );
		$ikebana   = MZK_Class_Types::get_by_slug( 'ikebana' );
		$preserved = MZK_Class_Types::get_by_slug( 'preserved-flower' );
		$ifda      = MZK_Class_Types::get_by_slug( 'ifda' );

		if ( ! $fresh || ! $ikebana || ! $preserved || ! $ifda ) {
			return array( 'error' => __( 'The four classes are missing — reactivate the plugin and try again.', 'mizuki-booking' ) );
		}

		// A typical studio week: two sessions on Saturday, one midweek evening,
		// plus the long course days.
		$patterns = array(
			array( 'class' => $fresh->id,     'weekday' => 6, 'time' => '10:00', 'mins' => 120, 'cap' => 6, 'label' => __( 'Fresh Flower — Saturday morning', 'mizuki-booking' ) ),
			array( 'class' => $ikebana->id,   'weekday' => 6, 'time' => '14:00', 'mins' => 120, 'cap' => 6, 'label' => __( 'Ikebana — Saturday afternoon', 'mizuki-booking' ) ),
			array( 'class' => $ikebana->id,   'weekday' => 3, 'time' => '19:00', 'mins' => 120, 'cap' => 5, 'label' => __( 'Ikebana — Wednesday evening', 'mizuki-booking' ) ),
			array( 'class' => $preserved->id, 'weekday' => 0, 'time' => '10:00', 'mins' => 240, 'cap' => 4, 'label' => __( 'Preserved Flower — Sunday', 'mizuki-booking' ) ),
			array( 'class' => $ifda->id,      'weekday' => 2, 'time' => '10:00', 'mins' => 240, 'cap' => 4, 'label' => __( 'IFDA course — Tuesday', 'mizuki-booking' ) ),
		);

		foreach ( $patterns as $pattern ) {
			$id = MZK_Sessions::save_template(
				array(
					'class_type_id'    => $pattern['class'],
					'label'            => $pattern['label'],
					'weekday'          => $pattern['weekday'],
					'start_time'       => $pattern['time'],
					'duration_minutes' => $pattern['mins'],
					'capacity'         => $pattern['cap'],
					'active'           => 1,
				)
			);
			if ( ! is_wp_error( $id ) ) {
				$demo['templates'][] = (int) $id;
			}
		}

		// A studio closure three weeks out, so the blocked-date screen has content.
		$closure_start = MZK_Utils::now()->modify( '+21 days' )->format( 'Y-m-d' );
		$closure_end   = MZK_Utils::now()->modify( '+23 days' )->format( 'Y-m-d' );
		$blackout      = MZK_Blackouts::save(
			array(
				'start_date' => $closure_start,
				'end_date'   => $closure_end,
				'reason'     => __( 'Studio closed — flower sourcing trip', 'mizuki-booking' ),
			)
		);
		if ( ! is_wp_error( $blackout ) ) {
			$demo['blackouts'][] = (int) $blackout;
		}

		// Eight weeks of sessions from those patterns.
		$before = self::session_ids();
		MZK_Sessions::generate( MZK_Utils::today(), MZK_Utils::now()->modify( '+8 weeks' )->format( 'Y-m-d' ) );
		$demo['sessions'] = array_values( array_diff( self::session_ids(), $before ) );

		// Two course students, one part-way through, one nearly finished.
		$students = array(
			array(
				'class'  => $preserved->id,
				'name'   => 'Wei Lin Tan',
				'email'  => 'weilin.demo@example.com',
				'phone'  => '+65 8123 4567',
				'total'  => 25,
				'expiry' => MZK_Utils::now()->modify( '+10 months' )->format( 'Y-m-d' ),
			),
			array(
				'class'  => $ifda->id,
				'name'   => 'Priya Menon',
				'email'  => 'priya.demo@example.com',
				'phone'  => '+65 9234 5678',
				'total'  => 25,
				'expiry' => MZK_Utils::now()->modify( '+2 months' )->format( 'Y-m-d' ),
			),
		);

		foreach ( $students as $student ) {
			$id = MZK_Enrollments::save(
				array(
					'class_type_id'  => $student['class'],
					'student_name'   => $student['name'],
					'email'          => $student['email'],
					'phone'          => $student['phone'],
					'sessions_total' => $student['total'],
					'start_date'     => MZK_Utils::now()->modify( '-2 months' )->format( 'Y-m-d' ),
					'expiry_date'    => $student['expiry'],
					'status'         => 'active',
					'notes'          => __( 'Demo student — safe to delete.', 'mizuki-booking' ),
				)
			);
			if ( ! is_wp_error( $id ) ) {
				$demo['enrollments'][] = (int) $id;
			}
		}

		// A few bookings spread over the first open sessions.
		$open = MZK_Sessions::query(
			array(
				'from'          => MZK_Utils::today(),
				'to'            => MZK_Utils::now()->modify( '+5 weeks' )->format( 'Y-m-d' ),
				'status'        => 'open',
				'only_bookable' => true,
				'limit'         => 12,
			)
		);

		$people = array(
			array( 'Wei Lin Tan', 'weilin.demo@example.com', '+65 8123 4567' ),
			array( 'Priya Menon', 'priya.demo@example.com', '+65 9234 5678' ),
			array( 'Sarah Lim', 'sarah.demo@example.com', '+65 9345 6789' ),
			array( 'Aiko Tanaka', 'aiko.demo@example.com', '+65 9456 7890' ),
			array( 'Grace Wong', 'grace.demo@example.com', '+65 9567 8901' ),
			array( 'Hui Min Chua', 'huimin.demo@example.com', '+65 9678 9012' ),
		);

		$index = 0;
		foreach ( $open as $session ) {
			// Two bookings on the first session so one screen shows a part-full class.
			$take = 0 === $index ? 2 : 1;
			for ( $i = 0; $i < $take; $i++ ) {
				$person = $people[ ( $index + $i ) % count( $people ) ];
				$id     = MZK_Bookings::create(
					array(
						'session_id'      => (int) $session->id,
						'student_name'    => $person[0],
						'email'           => $person[1],
						'phone'           => $person[2],
						'source'          => 0 === $index % 3 ? 'chat' : 'web',
						'notes'           => __( 'Demo booking — safe to delete.', 'mizuki-booking' ),
						'allow_overbook'  => true,
						'allow_duplicate' => true,
						'skip_emails'     => true,
						'no_account'      => true,
					)
				);
				if ( ! is_wp_error( $id ) ) {
					$demo['bookings'][] = (int) $id;
				}
			}
			++$index;
			if ( $index >= 6 ) {
				break;
			}
		}

		// One registration waiting for approval, so that screen isn't empty.
		if ( $open ) {
			global $wpdb;
			$waiting = MZK_Bookings::create(
				array(
					'session_id'      => (int) $open[ min( 2, count( $open ) - 1 ) ]->id,
					'student_name'    => 'Nurul Hidayah',
					'email'           => 'nurul.demo@example.com',
					'phone'           => '+65 9789 0123',
					'source'          => 'web',
					'notes'           => __( 'Demo registration — safe to delete.', 'mizuki-booking' ),
					'allow_overbook'  => true,
					'allow_duplicate' => true,
					'skip_emails'     => true,
					'no_account'      => true,
				)
			);
			if ( ! is_wp_error( $waiting ) ) {
				$wpdb->update( // phpcs:ignore WordPress.DB
					MZK_DB::bookings(),
					array( 'status' => 'awaiting_approval' ),
					array( 'id' => (int) $waiting )
				);
				$demo['bookings'][] = (int) $waiting;
			}
		}

		update_option( self::OPTION_DEMO, $demo );

		return array(
			'templates'   => count( $demo['templates'] ),
			'sessions'    => count( $demo['sessions'] ),
			'bookings'    => count( $demo['bookings'] ),
			'enrollments' => count( $demo['enrollments'] ),
			'blackouts'   => count( $demo['blackouts'] ),
		);
	}

	/**
	 * All session ids, used to work out which ones a generate run created.
	 *
	 * @return int[]
	 */
	private static function session_ids() {
		global $wpdb;
		$table = MZK_DB::sessions();
		return array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$table}" ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove everything install_demo() created, and nothing else.
	 *
	 * @return array Summary counts.
	 */
	public static function remove_demo() {
		global $wpdb;

		$demo   = (array) get_option( self::OPTION_DEMO, array() );
		$counts = array(
			'bookings'    => 0,
			'sessions'    => 0,
			'templates'   => 0,
			'enrollments' => 0,
			'blackouts'   => 0,
		);

		foreach ( (array) ( $demo['bookings'] ?? array() ) as $id ) {
			$wpdb->delete( MZK_DB::bookings(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
			++$counts['bookings'];
		}
		foreach ( (array) ( $demo['enrollments'] ?? array() ) as $id ) {
			MZK_Enrollments::delete( (int) $id );
			++$counts['enrollments'];
		}
		foreach ( (array) ( $demo['sessions'] ?? array() ) as $id ) {
			$wpdb->delete( MZK_DB::sessions(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
			++$counts['sessions'];
		}
		foreach ( (array) ( $demo['templates'] ?? array() ) as $id ) {
			MZK_Sessions::delete_template( (int) $id );
			++$counts['templates'];
		}
		foreach ( (array) ( $demo['blackouts'] ?? array() ) as $id ) {
			MZK_Blackouts::delete( (int) $id );
			++$counts['blackouts'];
		}

		delete_option( self::OPTION_DEMO );

		return $counts;
	}
}
