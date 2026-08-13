<?php
/**
 * Course student portal: signed in, but no course found under this address.
 *
 * Usually the student registered with a different e-mail from the one the
 * studio recorded, so the page names the address it looked under — that is the
 * one piece of information that lets them fix it themselves.
 *
 * Expects $user, $course, $course_name from MZK_Portal.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_User $user */
/** @var string $course_name */

$studio_email = MZK_Install::get_setting( 'admin_email' );
?>
<div class="mzk-root mzk-manage mzk-portal">

	<section class="mzk-band mzk-band--quiet">
		<p class="mzk-band__eyebrow"><?php esc_html_e( 'Student area', 'mizuki-booking' ); ?></p>
		<h3 class="mzk-band__title">
			<?php
			printf(
				/* translators: %s: student's name. */
				esc_html__( 'Hello, %s', 'mizuki-booking' ),
				esc_html( $user->display_name )
			);
			?>
		</h3>
		<p class="mzk-band__lead">
			<?php
			printf(
				/* translators: 1: course name, 2: the e-mail they signed in with. */
				esc_html__( 'We cannot see a %1$s course under %2$s yet.', 'mizuki-booking' ),
				esc_html( $course_name ),
				esc_html( $user->user_email )
			);
			?>
		</p>
	</section>

	<div class="mzk-empty">
		<p class="mzk-empty__title"><?php esc_html_e( 'What to do next', 'mizuki-booking' ); ?></p>
		<p class="mzk-note">
			<?php esc_html_e( 'If you have already joined the course, the studio may have your sessions recorded under a different e-mail address. Let them know which address to use and your sessions will appear here straight away.', 'mizuki-booking' ); ?>
		</p>

		<?php if ( is_email( $studio_email ) ) : ?>
			<p class="mzk-empty__actions">
				<a class="mzk-btn mzk-btn--primary" href="<?php echo esc_url( 'mailto:' . $studio_email . '?subject=' . rawurlencode( __( 'My course sessions', 'mizuki-booking' ) ) ); ?>">
					<?php esc_html_e( 'E-mail the studio', 'mizuki-booking' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<p class="mzk-portal__foot">
		<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log out', 'mizuki-booking' ); ?></a>
	</p>
</div>
