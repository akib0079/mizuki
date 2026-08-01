<?php
/**
 * Student login + registration, side by side.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error = isset( $_GET['mzk_error'] ) ? sanitize_key( wp_unslash( $_GET['mzk_error'] ) ) : '';

$messages = array(
	'invalid' => __( 'Please check the form — a name, a valid e-mail and a password of at least 8 characters are needed.', 'mizuki-booking' ),
	'exists'  => __( 'There is already an account with that e-mail address. Please log in instead.', 'mizuki-booking' ),
	'failed'  => __( 'Sorry, the account could not be created. Please try again.', 'mizuki-booking' ),
	'login'   => __( 'That username or password was not recognised.', 'mizuki-booking' ),
);
?>
<div class="mzk-manage mzk-auth">

	<?php if ( $error && isset( $messages[ $error ] ) ) : ?>
		<div class="mzk-notice mzk-notice--error"><?php echo esc_html( $messages[ $error ] ); ?></div>
	<?php endif; ?>

	<div class="mzk-auth__grid">

		<div class="mzk-booking">
			<h3 class="mzk-booking__title"><?php esc_html_e( 'Log in', 'mizuki-booking' ); ?></h3>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form">
				<input type="hidden" name="action" value="mzk_login" />
				<?php wp_nonce_field( 'mzk_login' ); ?>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'E-mail or username', 'mizuki-booking' ); ?></span>
					<input type="text" name="username" class="mzk-input" autocomplete="username" required />
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
			<h3 class="mzk-booking__title"><?php esc_html_e( 'New student', 'mizuki-booking' ); ?></h3>
			<p class="mzk-note">
				<?php esc_html_e( 'Create an account to book classes, reschedule them and follow your course progress in one place.', 'mizuki-booking' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form">
				<input type="hidden" name="action" value="mzk_register" />
				<?php wp_nonce_field( 'mzk_register' ); ?>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Full name', 'mizuki-booking' ); ?> *</span>
					<input type="text" name="student_name" class="mzk-input" autocomplete="name" required />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'E-mail', 'mizuki-booking' ); ?> *</span>
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
					<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Create my account', 'mizuki-booking' ); ?></button>
				</div>
			</form>
		</div>

	</div>

	<?php
	$booking_page = (int) MZK_Install::get_setting( 'booking_page_id' );
	if ( $booking_page ) :
		?>
		<p class="mzk-note mzk-auth__foot">
			<?php esc_html_e( 'You do not need an account to book — you can go straight to the calendar and we will set one up for you.', 'mizuki-booking' ); ?>
			<a href="<?php echo esc_url( get_permalink( $booking_page ) ); ?>"><?php esc_html_e( 'See available classes', 'mizuki-booking' ); ?></a>
		</p>
	<?php endif; ?>
</div>
