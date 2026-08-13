<?php
/**
 * Course student portal — the IFDA / Preserved Flower side of the site.
 *
 * These students paid for their course up front, so their booking journey has
 * no shop and no checkout in it. They sign in, see how many sessions they have
 * left and how long they have to use them, pick a date, and it is confirmed on
 * the spot with one session deducted.
 *
 * [mizuki_course_portal]              — every course the student holds
 * [mizuki_course_portal course="ifda"] — one course only
 *
 * Everything it shows comes from the same tables as the public calendar, so the
 * studio manages both from one admin area.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Portal {

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( 'mizuki_course_portal', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [mizuki_course_portal]
	 *
	 * @param array $atts course.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'course' => '' ), $atts, 'mizuki_course_portal' );

		MZK_Shortcodes::ensure_assets();

		$course = $atts['course'] ? MZK_Class_Types::resolve( $atts['course'] ) : null;

		ob_start();

		if ( ! is_user_logged_in() ) {
			self::render_signed_out( $course );
		} else {
			self::render_portal( $course );
		}

		return ob_get_clean();
	}

	/**
	 * Course packages the signed-in student holds.
	 *
	 * @param object|null $course Restrict to one course.
	 * @return object[]
	 */
	public static function packages( $course = null ) {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return array();
		}

		$rows = MZK_Enrollments::query( array( 'email' => $user->user_email ) );
		if ( ! $rows ) {
			$rows = MZK_Enrollments::query( array( 'user_id' => (int) $user->ID ) );
		}

		if ( $course ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $course ) {
						return (int) $row->class_type_id === (int) $course->id;
					}
				)
			);
		}

		return $rows;
	}

	/**
	 * Signed-out view: log in, or register interest.
	 *
	 * @param object|null $course Course this page is for.
	 */
	private static function render_signed_out( $course ) {
		$name = $course ? $course->name : __( 'course', 'mizuki-booking' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['mzk_error'] ) ? sanitize_key( wp_unslash( $_GET['mzk_error'] ) ) : '';
		?>
		<div class="mzk-root mzk-manage mzk-portal mzk-auth">

			<?php if ( 'login' === $error ) : ?>
				<div class="mzk-notice mzk-notice--error">
					<?php esc_html_e( 'That e-mail address or password was not recognised.', 'mizuki-booking' ); ?>
				</div>
			<?php elseif ( 'exists' === $error ) : ?>
				<div class="mzk-notice mzk-notice--error">
					<?php esc_html_e( 'There is already an account with that e-mail address. Please log in instead.', 'mizuki-booking' ); ?>
				</div>
			<?php elseif ( $error ) : ?>
				<div class="mzk-notice mzk-notice--error">
					<?php esc_html_e( 'Please check the form and try again.', 'mizuki-booking' ); ?>
				</div>
			<?php endif; ?>

			<div class="mzk-auth__grid">
				<div class="mzk-booking">
					<h3 class="mzk-booking__title"><?php esc_html_e( 'Student log in', 'mizuki-booking' ); ?></h3>
					<p class="mzk-note">
						<?php
						printf(
							/* translators: %s: course name. */
							esc_html__( 'Sign in with the e-mail address you gave us when you joined the %s course.', 'mizuki-booking' ),
							esc_html( $name )
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form">
						<input type="hidden" name="action" value="mzk_login" />
						<?php wp_nonce_field( 'mzk_login' ); ?>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'E-mail address', 'mizuki-booking' ); ?></span>
							<input type="email" name="username" class="mzk-input" autocomplete="email" required />
						</label>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'Password', 'mizuki-booking' ); ?></span>
							<input type="password" name="password" class="mzk-input" autocomplete="current-password" required />
						</label>

						<div class="mzk-form__actions">
							<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Log in', 'mizuki-booking' ); ?></button>
							<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
								<?php esc_html_e( 'Forgot password', 'mizuki-booking' ); ?>
							</a>
						</div>
					</form>
				</div>

				<div class="mzk-booking">
					<h3 class="mzk-booking__title"><?php esc_html_e( 'First time here?', 'mizuki-booking' ); ?></h3>
					<p class="mzk-note">
						<?php
						printf(
							/* translators: %s: course name. */
							esc_html__( 'If you have joined the %s course but have not set up your log-in yet, create it here with the same e-mail address you gave the studio. Your sessions will be waiting for you.', 'mizuki-booking' ),
							esc_html( $name )
						);
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form">
						<input type="hidden" name="action" value="mzk_register" />
						<?php wp_nonce_field( 'mzk_register' ); ?>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'Full name', 'mizuki-booking' ); ?> *</span>
							<input type="text" name="student_name" class="mzk-input" autocomplete="name" required />
						</label>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'E-mail address', 'mizuki-booking' ); ?> *</span>
							<input type="email" name="email" class="mzk-input" autocomplete="email" required />
						</label>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'Contact number', 'mizuki-booking' ); ?></span>
							<input type="tel" name="phone" class="mzk-input" autocomplete="tel" />
						</label>

						<label class="mzk-field">
							<span class="mzk-field__label"><?php esc_html_e( 'Choose a password', 'mizuki-booking' ); ?> *</span>
							<input type="password" name="password" class="mzk-input" autocomplete="new-password" minlength="8" required />
						</label>

						<div class="mzk-form__actions">
							<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Create my log-in', 'mizuki-booking' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Signed-in view: balance, book, upcoming, history.
	 *
	 * @param object|null $course Restrict to one course.
	 */
	private static function render_portal( $course ) {
		$user     = wp_get_current_user();
		$packages = self::packages( $course );

		if ( ! $packages ) {
			self::render_no_package( $course, $user );
			return;
		}

		$bookings = MZK_Bookings::for_student( $user->user_email, (int) $user->ID );
		$today    = MZK_Utils::today();
		$ids      = wp_list_pluck( $packages, 'class_type_id' );

		$upcoming = array();
		$past     = array();

		foreach ( $bookings as $booking ) {
			if ( ! in_array( (int) $booking->class_type_id, array_map( 'intval', $ids ), true ) ) {
				continue;
			}
			if ( in_array( $booking->status, array( 'confirmed', 'awaiting_approval' ), true ) && $booking->session_date >= $today ) {
				$upcoming[] = $booking;
			} else {
				$past[] = $booking;
			}
		}
		?>
		<div class="mzk-root mzk-manage mzk-portal">

			<div class="mzk-dash__head">
				<div>
					<h3 class="mzk-dash__hello">
						<?php
						printf(
							/* translators: %s: student's name. */
							esc_html__( 'Hello, %s', 'mizuki-booking' ),
							esc_html( $user->display_name )
						);
						?>
					</h3>
					<p class="mzk-note"><?php echo esc_html( $user->user_email ); ?></p>
				</div>
				<div class="mzk-dash__actions">
					<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
						<?php esc_html_e( 'Log out', 'mizuki-booking' ); ?>
					</a>
				</div>
			</div>

			<?php foreach ( $packages as $package ) : ?>
				<?php
				$percent = $package->sessions_total
					? min( 100, round( 100 * $package->sessions_used / $package->sessions_total ) )
					: 0;
				?>
				<div class="mzk-portal__course">
					<h4 class="mzk-dash__title"><?php echo esc_html( $package->class_name ); ?></h4>

					<div class="mzk-portal__figures">
						<div class="mzk-figure">
							<span class="mzk-figure__num"><?php echo esc_html( $package->sessions_left ); ?></span>
							<span class="mzk-figure__label"><?php esc_html_e( 'sessions left', 'mizuki-booking' ); ?></span>
						</div>
						<div class="mzk-figure">
							<span class="mzk-figure__num"><?php echo esc_html( $package->sessions_used ); ?></span>
							<span class="mzk-figure__label">
								<?php
								printf(
									/* translators: %d: sessions purchased. */
									esc_html__( 'used of %d', 'mizuki-booking' ),
									(int) $package->sessions_total
								);
								?>
							</span>
						</div>
						<div class="mzk-figure">
							<span class="mzk-figure__num mzk-figure__num--sm"><?php echo esc_html( $package->expiry_label ); ?></span>
							<span class="mzk-figure__label"><?php esc_html_e( 'complete by', 'mizuki-booking' ); ?></span>
						</div>
					</div>

					<div class="mzk-progress"><span style="width: <?php echo esc_attr( $percent ); ?>%"></span></div>

					<?php if ( $package->is_expired ) : ?>
						<div class="mzk-notice mzk-notice--error">
							<?php esc_html_e( 'Your course period has ended. Contact the studio and we can extend it for you.', 'mizuki-booking' ); ?>
						</div>
					<?php elseif ( ! $package->has_balance ) : ?>
						<div class="mzk-notice mzk-notice--info">
							<?php esc_html_e( 'You have used every session in your course. Contact the studio if you would like to add more.', 'mizuki-booking' ); ?>
						</div>
					<?php else : ?>
						<p class="mzk-portal__cta">
							<button type="button" class="mzk-btn mzk-btn--primary"
								data-mzk-book="<?php echo esc_attr( $package->class_slug ); ?>"
								data-mzk-class-name="<?php echo esc_attr( $package->class_name ); ?>"
								data-mzk-enrolled="1">
								<?php esc_html_e( 'Book a session', 'mizuki-booking' ); ?>
							</button>
							<span class="mzk-note"><?php esc_html_e( 'No payment — one session comes off your balance when you book.', 'mizuki-booking' ); ?></span>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<h4 class="mzk-dash__title"><?php esc_html_e( 'My booked sessions', 'mizuki-booking' ); ?></h4>

			<?php if ( ! $upcoming ) : ?>
				<div class="mzk-notice mzk-notice--info">
					<?php esc_html_e( 'Nothing booked yet. Use “Book a session” above to choose a date.', 'mizuki-booking' ); ?>
				</div>
			<?php else : ?>
				<div data-mzk-manage data-booking="0" data-token="" data-logged-in="1"></div>
			<?php endif; ?>

			<?php if ( $past ) : ?>
				<h4 class="mzk-dash__title"><?php esc_html_e( 'My history', 'mizuki-booking' ); ?></h4>
				<ul class="mzk-history">
					<?php foreach ( array_slice( $past, 0, 40 ) as $booking ) : ?>
						<li>
							<span class="mzk-history__date"><?php echo esc_html( $booking->date_label ); ?></span>
							<span class="mzk-history__class"><?php echo esc_html( $booking->class_name . ' · ' . $booking->time_label ); ?></span>
							<span class="mzk-badge"><?php echo esc_html( $booking->status_label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Signed in, but the studio has not set their course up yet.
	 *
	 * @param object|null $course Course this page is for.
	 * @param WP_User     $user   Current user.
	 */
	private static function render_no_package( $course, $user ) {
		$name = $course ? $course->name : __( 'course', 'mizuki-booking' );
		?>
		<div class="mzk-root mzk-manage mzk-portal">
			<div class="mzk-dash__head">
				<div>
					<h3 class="mzk-dash__hello">
						<?php
						printf(
							/* translators: %s: student's name. */
							esc_html__( 'Hello, %s', 'mizuki-booking' ),
							esc_html( $user->display_name )
						);
						?>
					</h3>
					<p class="mzk-note"><?php echo esc_html( $user->user_email ); ?></p>
				</div>
				<div class="mzk-dash__actions">
					<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
						<?php esc_html_e( 'Log out', 'mizuki-booking' ); ?>
					</a>
				</div>
			</div>

			<div class="mzk-notice mzk-notice--info">
				<?php
				printf(
					/* translators: 1: course name, 2: the student's e-mail. */
					esc_html__( 'We cannot see a %1$s course under %2$s yet. If you have already joined, let the studio know which e-mail address to use and your sessions will appear here.', 'mizuki-booking' ),
					esc_html( $name ),
					esc_html( $user->user_email )
				);
				?>
			</div>
		</div>
		<?php
	}
}
