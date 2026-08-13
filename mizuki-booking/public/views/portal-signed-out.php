<?php
/**
 * Course student portal, signed out.
 *
 * Students arrive here having already paid, so the page reassures before it
 * asks: this is your course area, here is what is inside, now sign in.
 *
 * Expects $course, $course_name, $error from MZK_Portal.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

/** @var object|null $course */
/** @var string $course_name */
/** @var string $error */

$messages = array(
	'login'   => __( 'That e-mail address or password was not recognised.', 'mizuki-booking' ),
	'exists'  => __( 'There is already a log-in for that e-mail address. Please sign in instead.', 'mizuki-booking' ),
	'invalid' => __( 'Please check the form — a name, a valid e-mail address and a password of at least 8 characters are needed.', 'mizuki-booking' ),
	'failed'  => __( 'Sorry, that could not be saved. Please try again.', 'mizuki-booking' ),
);
?>
<div class="mzk-root mzk-manage mzk-portal mzk-portal--out">

	<section class="mzk-band mzk-band--intro"
		<?php if ( $course ) : ?>style="--mzk-class: <?php echo esc_attr( $course->colour ); ?>"<?php endif; ?>>
		<p class="mzk-band__eyebrow"><?php esc_html_e( 'Student area', 'mizuki-booking' ); ?></p>
		<h3 class="mzk-band__title"><?php echo esc_html( $course_name ); ?></h3>
		<p class="mzk-band__lead">
			<?php esc_html_e( 'Your course is paid for in full. Sign in to book your lessons on the dates that suit you — there is nothing more to pay.', 'mizuki-booking' ); ?>
		</p>

		<ul class="mzk-band__list">
			<li><?php esc_html_e( 'See how many sessions you have left', 'mizuki-booking' ); ?></li>
			<li><?php esc_html_e( 'Book a date in a couple of clicks', 'mizuki-booking' ); ?></li>
			<li><?php esc_html_e( 'Change it later if your plans change', 'mizuki-booking' ); ?></li>
		</ul>
	</section>

	<?php if ( $error && isset( $messages[ $error ] ) ) : ?>
		<div class="mzk-notice mzk-notice--error"><?php echo esc_html( $messages[ $error ] ); ?></div>
	<?php endif; ?>

	<div class="mzk-auth__grid">

		<div class="mzk-booking">
			<h3 class="mzk-booking__title"><?php esc_html_e( 'Sign in', 'mizuki-booking' ); ?></h3>
			<p class="mzk-note">
				<?php esc_html_e( 'Use the e-mail address you gave the studio when you joined.', 'mizuki-booking' ); ?>
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
					<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Sign in', 'mizuki-booking' ); ?></button>
					<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
						<?php esc_html_e( 'Forgot password', 'mizuki-booking' ); ?>
					</a>
				</div>
			</form>
		</div>

		<div class="mzk-booking">
			<h3 class="mzk-booking__title"><?php esc_html_e( 'First time here', 'mizuki-booking' ); ?></h3>
			<p class="mzk-note">
				<?php
				printf(
					/* translators: %s: course name. */
					esc_html__( 'Already joined the %s course but not set up a log-in yet? Create one with the same e-mail address you gave the studio, and your sessions will be waiting.', 'mizuki-booking' ),
					esc_html( $course_name )
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
