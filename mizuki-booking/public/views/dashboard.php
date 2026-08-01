<?php
/**
 * Student dashboard: everything about them in one place.
 *
 * Expects $user (WP_User) from MZK_Students::dashboard_shortcode().
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_User $user */
$bookings = MZK_Bookings::for_student( $user->user_email, (int) $user->ID );

$upcoming = array();
$waiting  = array();
$past     = array();
$today    = MZK_Utils::today();

foreach ( $bookings as $booking ) {
	if ( 'awaiting_approval' === $booking->status ) {
		$waiting[] = $booking;
	} elseif ( in_array( $booking->status, array( 'confirmed', 'pending' ), true ) && $booking->session_date >= $today ) {
		$upcoming[] = $booking;
	} else {
		$past[] = $booking;
	}
}

$enrollments = MZK_Enrollments::query( array( 'email' => $user->user_email ) );
$booking_page = (int) MZK_Install::get_setting( 'booking_page_id' );
$phone        = get_user_meta( $user->ID, 'mzk_phone', true );
?>
<div class="mzk-manage mzk-dash">

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
			<p class="mzk-note"><?php echo esc_html( $user->user_email ); ?><?php echo $phone ? ' · ' . esc_html( $phone ) : ''; ?></p>
		</div>
		<div class="mzk-dash__actions">
			<?php if ( $booking_page ) : ?>
				<a class="mzk-btn mzk-btn--primary" href="<?php echo esc_url( get_permalink( $booking_page ) ); ?>">
					<?php esc_html_e( 'Book a class', 'mizuki-booking' ); ?>
				</a>
			<?php endif; ?>
			<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( wp_logout_url( MZK_Students::login_url() ) ); ?>">
				<?php esc_html_e( 'Log out', 'mizuki-booking' ); ?>
			</a>
		</div>
	</div>

	<?php if ( $enrollments ) : ?>
		<h4 class="mzk-dash__title"><?php esc_html_e( 'My courses', 'mizuki-booking' ); ?></h4>
		<?php foreach ( $enrollments as $enrollment ) : ?>
			<?php $percent = $enrollment->sessions_total ? min( 100, round( 100 * $enrollment->sessions_used / $enrollment->sessions_total ) ) : 0; ?>
			<div class="mzk-booking">
				<h3 class="mzk-booking__title"><?php echo esc_html( $enrollment->class_name ); ?></h3>
				<div class="mzk-booking__meta">
					<span>
						<?php
						printf(
							/* translators: 1: used, 2: total. */
							esc_html__( '%1$d of %2$d sessions used', 'mizuki-booking' ),
							(int) $enrollment->sessions_used,
							(int) $enrollment->sessions_total
						);
						?>
					</span>
					<span><?php printf( /* translators: %s: date. */ esc_html__( 'Valid until %s', 'mizuki-booking' ), esc_html( $enrollment->expiry_label ) ); ?></span>
					<span class="mzk-badge">
						<?php
						printf(
							/* translators: %d: sessions left. */
							esc_html__( '%d left', 'mizuki-booking' ),
							(int) $enrollment->sessions_left
						);
						?>
					</span>
				</div>
				<div class="mzk-progress"><span style="width: <?php echo esc_attr( $percent ); ?>%"></span></div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( $waiting ) : ?>
		<h4 class="mzk-dash__title"><?php esc_html_e( 'Waiting for confirmation', 'mizuki-booking' ); ?></h4>
		<?php foreach ( $waiting as $booking ) : ?>
			<div class="mzk-booking mzk-booking--waiting">
				<h3 class="mzk-booking__title"><?php echo esc_html( $booking->class_name ); ?></h3>
				<div class="mzk-booking__meta">
					<span><?php echo esc_html( $booking->date_label ); ?></span>
					<span><?php echo esc_html( $booking->time_label ); ?></span>
					<span class="mzk-badge"><?php esc_html_e( 'Awaiting approval', 'mizuki-booking' ); ?></span>
				</div>
				<p class="mzk-note"><?php esc_html_e( 'We have your registration and will confirm your place shortly. Your place is held in the meantime.', 'mizuki-booking' ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<h4 class="mzk-dash__title"><?php esc_html_e( 'My upcoming classes', 'mizuki-booking' ); ?></h4>

	<?php if ( ! $upcoming ) : ?>
		<div class="mzk-notice mzk-notice--info">
			<?php esc_html_e( 'You have no classes booked yet.', 'mizuki-booking' ); ?>
		</div>
	<?php else : ?>
		<div data-mzk-manage data-booking="0" data-token="" data-logged-in="1"></div>
	<?php endif; ?>

	<?php if ( $past ) : ?>
		<h4 class="mzk-dash__title"><?php esc_html_e( 'Past classes', 'mizuki-booking' ); ?></h4>
		<ul class="mzk-history">
			<?php foreach ( array_slice( $past, 0, 20 ) as $booking ) : ?>
				<li>
					<span class="mzk-history__date"><?php echo esc_html( $booking->date_label ); ?></span>
					<span class="mzk-history__class"><?php echo esc_html( $booking->class_name ); ?></span>
					<span class="mzk-badge"><?php echo esc_html( $booking->status_label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
