<?php
/**
 * WooCommerce bridge — the all-in-one flow.
 *
 * A product can be linked to the booking calendar in two ways:
 *
 *   1. Session booking  — the student picks a session on the product page, the
 *      seat is held while the order is unpaid, and the booking is confirmed the
 *      moment payment lands.
 *   2. Course package   — buying the product creates (or tops up) the student's
 *      IFDA / Preserved Flower package of N sessions.
 *
 * Everything else in the plugin — capacity, blackouts, reschedule rules,
 * reminders — applies unchanged, so paid workshops and course students share
 * one source of truth for seats.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Woo {

	const META_MODE     = '_mzk_mode';            // '', 'session', 'package'.
	const META_CLASS    = '_mzk_class_type_id';
	const META_SESSIONS = '_mzk_package_sessions';
	const META_VALIDITY = '_mzk_package_validity';
	const CART_SESSION  = 'mzk_session_id';

	/**
	 * Is WooCommerce active and the integration switched on?
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' ) && MZK_Install::get_setting( 'woo_enabled' );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Product admin.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_meta' ) );

		if ( ! self::active() ) {
			return;
		}

		// Product page.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'session_picker' ) );

		// Cart.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_meta' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'revalidate_cart' ) );

		// Order lifecycle.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'hold_seats' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'hold_seats' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_confirm' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_confirm' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'release' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'release' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'release' ), 10, 1 );

		// Order screens.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_panel' ) );
	}

	/* ------------------------------------------------------------ product */

	/**
	 * Add the "Mizuki Booking" tab to the product data box.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function product_tab( $tabs ) {
		$tabs['mzk'] = array(
			'label'    => __( 'Mizuki Booking', 'mizuki-booking' ),
			'target'   => 'mzk_product_data',
			'class'    => array(),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Render the product data panel.
	 */
	public static function product_panel() {
		global $post;

		$mode     = get_post_meta( $post->ID, self::META_MODE, true );
		$class_id = (int) get_post_meta( $post->ID, self::META_CLASS, true );
		$sessions = (int) get_post_meta( $post->ID, self::META_SESSIONS, true );
		$validity = (int) get_post_meta( $post->ID, self::META_VALIDITY, true );

		$options = array( '' => __( '— not a booking product —', 'mizuki-booking' ) );
		foreach ( MZK_Class_Types::all() as $type ) {
			$options[ (int) $type->id ] = $type->name;
		}

		echo '<div id="mzk_product_data" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_select(
			array(
				'id'          => self::META_MODE,
				'label'       => __( 'Booking behaviour', 'mizuki-booking' ),
				'value'       => $mode,
				'options'     => array(
					''        => __( 'None — ordinary product', 'mizuki-booking' ),
					'session' => __( 'Session booking — student picks a date', 'mizuki-booking' ),
					'package' => __( 'Course package — grants a number of sessions', 'mizuki-booking' ),
				),
				'description' => __( 'Choose how this product connects to the booking calendar.', 'mizuki-booking' ),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => self::META_CLASS,
				'label'       => __( 'Class', 'mizuki-booking' ),
				'value'       => $class_id ? (string) $class_id : '',
				'options'     => $options,
				'description' => __( 'Which class this product books or tops up.', 'mizuki-booking' ),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_SESSIONS,
				'label'             => __( 'Sessions granted', 'mizuki-booking' ),
				'value'             => $sessions ? $sessions : '',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'description'       => __( 'Course packages only — e.g. 25 for the IFDA course.', 'mizuki-booking' ),
				'desc_tip'          => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_VALIDITY,
				'label'             => __( 'Valid for (months)', 'mizuki-booking' ),
				'value'             => $validity ? $validity : '',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'description'       => __( 'Course packages only. Leave empty for the studio default; 0 means no expiry. You can always extend a package later.', 'mizuki-booking' ),
				'desc_tip'          => true,
			)
		);

		echo '</div>';
	}

	/**
	 * Persist the product panel fields.
	 *
	 * @param int $product_id Product id.
	 */
	public static function save_product_meta( $product_id ) {
		// WooCommerce verifies its own nonce before firing this hook.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$mode = isset( $_POST[ self::META_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_MODE ] ) ) : '';
		$mode = in_array( $mode, array( 'session', 'package' ), true ) ? $mode : '';

		update_post_meta( $product_id, self::META_MODE, $mode );
		update_post_meta( $product_id, self::META_CLASS, isset( $_POST[ self::META_CLASS ] ) ? (int) $_POST[ self::META_CLASS ] : 0 );
		update_post_meta( $product_id, self::META_SESSIONS, isset( $_POST[ self::META_SESSIONS ] ) ? max( 0, (int) $_POST[ self::META_SESSIONS ] ) : 0 );
		update_post_meta(
			$product_id,
			self::META_VALIDITY,
			isset( $_POST[ self::META_VALIDITY ] ) && '' !== $_POST[ self::META_VALIDITY ] ? max( 0, (int) $_POST[ self::META_VALIDITY ] ) : ''
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Booking mode for a product.
	 *
	 * @param int $product_id Product id.
	 * @return string '', 'session' or 'package'.
	 */
	public static function mode( $product_id ) {
		$mode = get_post_meta( (int) $product_id, self::META_MODE, true );
		return in_array( $mode, array( 'session', 'package' ), true ) ? $mode : '';
	}

	/**
	 * Class type linked to a product.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function class_id( $product_id ) {
		return (int) get_post_meta( (int) $product_id, self::META_CLASS, true );
	}

	/* --------------------------------------------------------- front end */

	/**
	 * Render the session picker above the add-to-cart button.
	 */
	public static function session_picker() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();
		if ( 'session' !== self::mode( $product_id ) ) {
			return;
		}

		$class_id = self::class_id( $product_id );
		if ( ! $class_id ) {
			return;
		}

		$months   = max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) );
		$sessions = MZK_Sessions::query(
			array(
				'from'          => MZK_Utils::today(),
				'to'            => gmdate( 'Y-m-t', strtotime( MZK_Utils::today() . " +{$months} months" ) ),
				'class_type_id' => $class_id,
				'status'        => 'open',
				'only_bookable' => true,
			)
		);

		MZK_Shortcodes::ensure_assets();

		echo '<div class="mzk-root mzk-product-picker">';
		echo '<label class="mzk-field"><span class="mzk-field__label">' . esc_html__( 'Choose your session', 'mizuki-booking' ) . ' *</span>';

		if ( ! $sessions ) {
			echo '</label><p class="mzk-notice mzk-notice--info">'
				. esc_html__( 'No dates are open for booking at the moment. Please contact the studio.', 'mizuki-booking' )
				. '</p></div>';
			return;
		}

		echo '<select name="' . esc_attr( self::CART_SESSION ) . '" class="mzk-input" required>';
		echo '<option value="">' . esc_html__( 'Select a date and time…', 'mizuki-booking' ) . '</option>';

		$current_date = '';
		foreach ( $sessions as $session ) {
			if ( $current_date !== $session->session_date ) {
				if ( '' !== $current_date ) {
					echo '</optgroup>';
				}
				echo '<optgroup label="' . esc_attr( $session->date_label ) . '">';
				$current_date = $session->session_date;
			}
			printf(
				'<option value="%1$d">%2$s</option>',
				(int) $session->id,
				esc_html(
					$session->time_label . ' · ' . $session->duration_label . ' · ' .
					sprintf(
						/* translators: %d: places remaining. */
						_n( '%d place left', '%d places left', (int) $session->seats_available, 'mizuki-booking' ),
						(int) $session->seats_available
					)
				)
			);
		}
		if ( '' !== $current_date ) {
			echo '</optgroup>';
		}
		echo '</select></label>';
		echo '<p class="mzk-note">' . esc_html__( 'Your place is held while you check out and confirmed as soon as payment goes through.', 'mizuki-booking' ) . '</p>';
		echo '</div>';
	}

	/* -------------------------------------------------------------- cart */

	/**
	 * Block adding a booking product without a valid, available session.
	 *
	 * @param bool $passed     Current validation state.
	 * @param int  $product_id Product id.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( 'session' !== self::mode( $product_id ) ) {
			return $passed;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles the add-to-cart nonce.
		$session_id = isset( $_POST[ self::CART_SESSION ] ) ? (int) $_POST[ self::CART_SESSION ] : 0;
		if ( ! $session_id ) {
			wc_add_notice( __( 'Please choose a session date before adding this to your cart.', 'mizuki-booking' ), 'error' );
			return false;
		}

		$session = MZK_Sessions::get( $session_id );
		if ( ! $session || (int) $session->class_type_id !== self::class_id( $product_id ) ) {
			wc_add_notice( __( 'That session is not available for this class.', 'mizuki-booking' ), 'error' );
			return false;
		}

		$gate = MZK_Bookings::check_bookable( $session );
		if ( is_wp_error( $gate ) ) {
			wc_add_notice( $gate->get_error_message(), 'error' );
			return false;
		}

		if ( $session->seats_available < max( 1, (int) $quantity ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %d: places remaining. */
					_n( 'Only %d place is left on that session.', 'Only %d places are left on that session.', (int) $session->seats_available, 'mizuki-booking' ),
					(int) $session->seats_available
				),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Attach the chosen session to the cart item.
	 *
	 * @param array $data       Cart item data.
	 * @param int   $product_id Product id.
	 * @return array
	 */
	public static function add_cart_item_data( $data, $product_id ) {
		if ( 'session' !== self::mode( $product_id ) ) {
			return $data;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles the add-to-cart nonce.
		$session_id = isset( $_POST[ self::CART_SESSION ] ) ? (int) $_POST[ self::CART_SESSION ] : 0;
		if ( $session_id ) {
			$data[ self::CART_SESSION ] = $session_id;
		}
		return $data;
	}

	/**
	 * Show the session on cart and checkout lines.
	 *
	 * @param array $items Existing item meta.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function cart_item_meta( $items, $cart_item ) {
		if ( empty( $cart_item[ self::CART_SESSION ] ) ) {
			return $items;
		}
		$session = MZK_Sessions::get( (int) $cart_item[ self::CART_SESSION ] );
		if ( ! $session ) {
			return $items;
		}
		$items[] = array(
			'key'   => __( 'Session', 'mizuki-booking' ),
			'value' => $session->date_label . ', ' . $session->time_label,
		);
		return $items;
	}

	/**
	 * Re-check every booking line when the cart is viewed, so a session that
	 * filled up meanwhile cannot be checked out.
	 */
	public static function revalidate_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::CART_SESSION ] ) ) {
				continue;
			}
			$session = MZK_Sessions::get( (int) $item[ self::CART_SESSION ] );
			$qty     = max( 1, (int) $item['quantity'] );

			if ( ! $session ) {
				WC()->cart->remove_cart_item( $key );
				wc_add_notice( __( 'A session in your cart is no longer available and has been removed.', 'mizuki-booking' ), 'error' );
				continue;
			}

			$gate = MZK_Bookings::check_bookable( $session );
			if ( is_wp_error( $gate ) || $session->seats_available < $qty ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: class name, 2: date. */
						__( '%1$s on %2$s is no longer available. Please choose another date.', 'mizuki-booking' ),
						$session->class_name,
						$session->date_label
					),
					'error'
				);
			}
		}
	}

	/* ------------------------------------------------------------- order */

	/**
	 * Copy the session onto the order line item.
	 *
	 * @param WC_Order_Item_Product $item      Line item.
	 * @param string                $cart_key  Cart key.
	 * @param array                 $values    Cart item values.
	 * @param WC_Order              $order     Order.
	 */
	public static function order_line_item( $item, $cart_key, $values, $order ) {
		if ( empty( $values[ self::CART_SESSION ] ) ) {
			return;
		}
		$session = MZK_Sessions::get( (int) $values[ self::CART_SESSION ] );
		if ( ! $session ) {
			return;
		}
		$item->add_meta_data( '_mzk_session_id', (int) $session->id, true );
		$item->add_meta_data(
			__( 'Session', 'mizuki-booking' ),
			$session->date_label . ', ' . $session->time_label,
			true
		);
	}

	/**
	 * Create pending bookings the moment an order is placed, so the seats are
	 * held while payment is in flight.
	 *
	 * @param int|WC_Order $order_id Order id or object.
	 */
	public static function hold_seats( $order_id ) {
		$order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_mzk_held' ) ) {
			return;
		}

		$minutes = max( 5, (int) MZK_Install::get_setting( 'woo_hold_minutes', 45 ) );
		$expires = MZK_Utils::now()->modify( "+{$minutes} minutes" )->format( 'Y-m-d H:i:s' );
		$held    = 0;

		foreach ( $order->get_items() as $item_id => $item ) {
			$session_id = (int) $item->get_meta( '_mzk_session_id' );
			if ( ! $session_id ) {
				continue;
			}

			for ( $i = 0; $i < max( 1, (int) $item->get_quantity() ); $i++ ) {
				$booking_id = MZK_Bookings::create(
					array(
						'session_id'      => $session_id,
						'student_name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
						'email'           => $order->get_billing_email(),
						'phone'           => $order->get_billing_phone(),
						'user_id'         => (int) $order->get_customer_id(),
						'source'          => 'web',
						'status'          => 'pending',
						'hold_expires_at' => $expires,
						'order_id'        => (int) $order->get_id(),
						'order_item_id'   => (int) $item_id,
						'product_id'      => (int) $item->get_product_id(),
						'allow_duplicate' => true,
						'skip_emails'     => true,
					)
				);

				if ( is_wp_error( $booking_id ) ) {
					$order->add_order_note(
						sprintf(
							/* translators: %s: reason. */
							__( 'Mizuki Booking: seat could not be held — %s', 'mizuki-booking' ),
							$booking_id->get_error_message()
						)
					);
					continue;
				}
				++$held;
			}
		}

		if ( $held ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: number of seats, 2: minutes. */
					_n( 'Mizuki Booking: %1$d place held for %2$d minutes, pending payment.', 'Mizuki Booking: %1$d places held for %2$d minutes, pending payment.', $held, 'mizuki-booking' ),
					$held,
					$minutes
				)
			);
		}

		$order->update_meta_data( '_mzk_held', 1 );
		$order->save();
	}

	/**
	 * Confirm bookings and grant course packages once the order is paid.
	 *
	 * @param int $order_id Order id.
	 */
	public static function maybe_confirm( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$confirm_on = MZK_Install::get_setting( 'woo_confirm_on', 'processing' );
		if ( 'completed' === $confirm_on && ! $order->has_status( 'completed' ) ) {
			return;
		}
		if ( $order->get_meta( '_mzk_confirmed' ) ) {
			return;
		}

		// A gateway can jump straight to paid without the checkout hook firing.
		self::hold_seats( $order );

		$confirmed = self::confirm_bookings( $order );
		$granted   = self::grant_packages( $order );

		if ( $confirmed || $granted ) {
			$notes = array();
			if ( $confirmed ) {
				/* translators: %d: number of bookings. */
				$notes[] = sprintf( _n( '%d booking confirmed', '%d bookings confirmed', $confirmed, 'mizuki-booking' ), $confirmed );
			}
			if ( $granted ) {
				/* translators: %d: number of course packages. */
				$notes[] = sprintf( _n( '%d course package granted', '%d course packages granted', $granted, 'mizuki-booking' ), $granted );
			}
			$order->add_order_note( 'Mizuki Booking: ' . implode( ', ', $notes ) . '.' );
		}

		$order->update_meta_data( '_mzk_confirmed', 1 );
		$order->save();
	}

	/**
	 * Flip this order's held bookings to confirmed and send the confirmations.
	 *
	 * @param WC_Order $order Order.
	 * @return int Bookings confirmed.
	 */
	private static function confirm_bookings( $order ) {
		global $wpdb;

		$rows = MZK_Bookings::query(
			array(
				'order_id' => (int) $order->get_id(),
				'status'   => 'pending',
			)
		);

		$count = 0;
		foreach ( $rows as $booking ) {
			// The account is created now, not when the seat was held, so an
			// abandoned checkout never leaves an orphan student behind.
			$user_id = (int) $booking->user_id;
			if ( ! $user_id && class_exists( 'MZK_Students' ) ) {
				$user_id = MZK_Students::ensure_account( $booking->email, $booking->student_name, $booking->phone );
			}

			$wpdb->update( // phpcs:ignore WordPress.DB
				MZK_DB::bookings(),
				array(
					'status'          => 'confirmed',
					'hold_expires_at' => null,
					'user_id'         => $user_id,
					'updated_at'      => current_time( 'mysql' ),
				),
				array( 'id' => (int) $booking->id )
			);

			// A session the student paid for must NOT also come out of their course
			// package — that would charge them twice. Only a zero-priced line (the
			// "book a session from my package" product) draws down the balance.
			if ( self::line_is_free( $order, (int) $booking->order_item_id ) ) {
				MZK_Bookings::attach_enrollment( (int) $booking->id );
			}

			MZK_Mailer::send_confirmation( (int) $booking->id );
			if ( MZK_Install::get_setting( 'notify_admin' ) ) {
				MZK_Mailer::notify_admin_new_booking( (int) $booking->id );
			}

			/** Fires when an order payment confirms a booking. */
			do_action( 'mzk_booking_paid', (int) $booking->id, $order );

			++$count;
		}

		return $count;
	}

	/**
	 * Was this order line free? Used to decide whether a booking should draw a
	 * session from the student's course package.
	 *
	 * @param WC_Order $order        Order.
	 * @param int      $order_item_id Line item id.
	 * @return bool
	 */
	private static function line_is_free( $order, $order_item_id ) {
		if ( ! $order_item_id ) {
			return false;
		}
		$item = $order->get_item( $order_item_id );
		if ( ! $item ) {
			return false;
		}
		return (float) $item->get_total() <= 0;
	}

	/**
	 * Create or top up course packages bought on this order.
	 *
	 * @param WC_Order $order Order.
	 * @return int Packages granted.
	 */
	private static function grant_packages( $order ) {
		$count = 0;

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			if ( 'package' !== self::mode( $product_id ) ) {
				continue;
			}

			$class_id = self::class_id( $product_id );
			$sessions = (int) get_post_meta( $product_id, self::META_SESSIONS, true );
			if ( ! $class_id || $sessions < 1 ) {
				continue;
			}

			$quantity = max( 1, (int) $item->get_quantity() );
			$total    = $sessions * $quantity;

			$validity = get_post_meta( $product_id, self::META_VALIDITY, true );
			if ( '' === $validity ) {
				$validity = (int) MZK_Install::get_setting( 'woo_package_validity', 12 );
			}
			$validity = (int) $validity;
			$expiry   = $validity > 0
				? MZK_Utils::now()->modify( "+{$validity} months" )->format( 'Y-m-d' )
				: '';

			$email = $order->get_billing_email();
			$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

			// Top up an existing package rather than creating a second one.
			$existing = MZK_Enrollments::query(
				array(
					'email'         => $email,
					'class_type_id' => $class_id,
					'status'        => 'active',
				)
			);

			if ( $existing ) {
				$target = $existing[0];
				MZK_Enrollments::extend(
					(int) $target->id,
					$total,
					$expiry && ( ! $target->expiry_date || $expiry > $target->expiry_date ) ? $expiry : '',
					sprintf(
						/* translators: %s: order number. */
						__( 'Purchased on order #%s', 'mizuki-booking' ),
						$order->get_order_number()
					)
				);
				++$count;
				continue;
			}

			$result = MZK_Enrollments::save(
				array(
					'class_type_id'  => $class_id,
					'student_name'   => $name ? $name : $email,
					'email'          => $email,
					'phone'          => $order->get_billing_phone(),
					'user_id'        => (int) $order->get_customer_id(),
					'sessions_total' => $total,
					'start_date'     => MZK_Utils::today(),
					'expiry_date'    => $expiry,
					'status'         => 'active',
					'order_id'       => (int) $order->get_id(),
					'product_id'     => $product_id,
					'notes'          => sprintf(
						/* translators: %s: order number. */
						__( 'Created from order #%s', 'mizuki-booking' ),
						$order->get_order_number()
					),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				/** Fires when a paid order grants a course package. */
				do_action( 'mzk_package_granted', (int) $result, $order );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Release held or confirmed seats when an order is cancelled, fails or is refunded.
	 *
	 * @param int $order_id Order id.
	 */
	public static function release( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$rows = MZK_Bookings::query(
			array(
				'order_id' => (int) $order->get_id(),
				'statuses' => array( 'pending', 'confirmed' ),
			)
		);
		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $booking ) {
			MZK_Bookings::cancel(
				(int) $booking->id,
				array(
					'by_admin'    => true,
					// A student who never paid should not get a cancellation e-mail.
					'skip_emails' => 'pending' === $booking->status,
					'reason'      => sprintf(
						/* translators: %s: order status. */
						__( 'Order %s', 'mizuki-booking' ),
						$order->get_status()
					),
				)
			);
		}

		$order->add_order_note(
			sprintf(
				/* translators: %d: number of places. */
				_n( 'Mizuki Booking: %d place released.', 'Mizuki Booking: %d places released.', count( $rows ), 'mizuki-booking' ),
				count( $rows )
			)
		);
	}

	/**
	 * Drop seat holds whose orders were never paid. Runs from cron, and from the
	 * calendar endpoint in throttled mode.
	 *
	 * @param bool $throttle Skip if another request swept within the last minute.
	 * @return int Holds released.
	 */
	public static function expire_holds( $throttle = false ) {
		// The calendar endpoint calls this on every load so availability stays
		// honest between cron runs. Throttling keeps that to one write a minute
		// instead of one per visitor.
		if ( $throttle && get_transient( 'mzk_holds_swept' ) ) {
			return 0;
		}
		if ( $throttle ) {
			set_transient( 'mzk_holds_swept', 1, MINUTE_IN_SECONDS );
		}

		global $wpdb;
		$table = MZK_DB::bookings();
		$now   = current_time( 'mysql' );

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'pending' AND hold_expires_at IS NOT NULL AND hold_expires_at < %s", // phpcs:ignore WordPress.DB
				$now
			)
		);
		if ( ! $ids ) {
			return 0;
		}

		foreach ( $ids as $id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				array(
					'status'     => 'expired',
					'updated_at' => $now,
				),
				array( 'id' => (int) $id )
			);
		}

		return count( $ids );
	}

	/**
	 * Show the linked bookings on the order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_order_panel( $order ) {
		$rows = MZK_Bookings::query( array( 'order_id' => (int) $order->get_id() ) );
		if ( ! $rows ) {
			return;
		}

		echo '<div class="mzk-order-bookings"><h3>' . esc_html__( 'Mizuki bookings', 'mizuki-booking' ) . '</h3><ul>';
		foreach ( $rows as $booking ) {
			printf(
				'<li><a href="%1$s">%2$s</a> — %3$s</li>',
				esc_url( admin_url( 'admin.php?page=mzk-bookings&s=' . rawurlencode( $booking->email ) ) ),
				esc_html( $booking->class_name . ' · ' . $booking->date_label . ' · ' . $booking->time_label ),
				esc_html( $booking->status_label )
			);
		}
		echo '</ul></div>';
	}
}
