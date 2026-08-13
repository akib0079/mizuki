<?php
/**
 * Course student portal, signed in.
 *
 * The page answers three questions in the order a student asks them:
 *   How many sessions have I got left, and by when?   → the navy band
 *   When is my next class?                            → the card beneath it
 *   What else have I booked, and what have I done?    → the lists below
 *
 * Expects $packages, $next, $rest, $past, $user from MZK_Portal.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

/** @var object[] $packages */
/** @var object|null $next */
/** @var object[] $rest */
/** @var object[] $past */
/** @var WP_User $user */
?>
<div class="mzk-root mzk-manage mzk-portal">

	<?php foreach ( $packages as $package ) : ?>
		<?php
		$percent = $package->sessions_total
			? min( 100, round( 100 * $package->sessions_used / $package->sessions_total ) )
			: 0;

		$days_left = 0;
		if ( $package->expiry_date ) {
			$expiry    = MZK_Utils::make_datetime( $package->expiry_date );
			$days_left = $expiry ? (int) floor( ( $expiry->getTimestamp() - time() ) / DAY_IN_SECONDS ) : 0;
		}
		?>

		<section class="mzk-band" style="--mzk-class: <?php echo esc_attr( $package->class_colour ); ?>">
			<div class="mzk-band__top">
				<div>
					<p class="mzk-band__eyebrow"><?php esc_html_e( 'My course', 'mizuki-booking' ); ?></p>
					<h3 class="mzk-band__title"><?php echo esc_html( $package->class_name ); ?></h3>
				</div>
				<p class="mzk-band__who">
					<?php echo esc_html( $user->display_name ); ?>
					<span><?php echo esc_html( $user->user_email ); ?></span>
				</p>
			</div>

			<div class="mzk-band__figures">
				<div class="mzk-figure">
					<span class="mzk-figure__num"><?php echo esc_html( $package->sessions_left ); ?></span>
					<span class="mzk-figure__label">
						<?php
						echo esc_html(
							_n( 'session left', 'sessions left', (int) $package->sessions_left, 'mizuki-booking' )
						);
						?>
					</span>
				</div>

				<div class="mzk-figure">
					<span class="mzk-figure__num"><?php echo esc_html( $package->sessions_used ); ?></span>
					<span class="mzk-figure__label">
						<?php
						printf(
							/* translators: %d: sessions in the course. */
							esc_html__( 'used of %d', 'mizuki-booking' ),
							(int) $package->sessions_total
						);
						?>
					</span>
				</div>

				<div class="mzk-figure mzk-figure--date">
					<span class="mzk-figure__when"><?php echo esc_html( $package->expiry_label ); ?></span>
					<span class="mzk-figure__label">
						<?php if ( $package->expiry_date && $days_left > 0 ) : ?>
							<?php
							printf(
								/* translators: %d: days remaining. */
								esc_html( _n( 'to finish — %d day left', 'to finish — %d days left', $days_left, 'mizuki-booking' ) ),
								$days_left
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'to finish by', 'mizuki-booking' ); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<div class="mzk-band__bar" role="img"
				aria-label="<?php echo esc_attr( sprintf( '%d%%', $percent ) ); ?>">
				<span style="width: <?php echo esc_attr( $percent ); ?>%"></span>
			</div>

			<?php if ( $package->is_expired ) : ?>
				<p class="mzk-band__note mzk-band__note--warn">
					<?php esc_html_e( 'Your course period has ended. Contact the studio and we can extend it for you.', 'mizuki-booking' ); ?>
				</p>
			<?php elseif ( ! $package->has_balance ) : ?>
				<p class="mzk-band__note">
					<?php esc_html_e( 'You have used every session in your course. Contact the studio if you would like to add more.', 'mizuki-booking' ); ?>
				</p>
			<?php else : ?>
				<div class="mzk-band__actions">
					<button type="button" class="mzk-btn mzk-btn--onband"
						data-mzk-book="<?php echo esc_attr( $package->class_slug ); ?>"
						data-mzk-class-name="<?php echo esc_attr( $package->class_name ); ?>"
						data-mzk-enrolled="1">
						<?php esc_html_e( 'Book a session', 'mizuki-booking' ); ?>
					</button>
					<p class="mzk-band__note">
						<?php esc_html_e( 'Nothing to pay — one session comes off your balance when you book.', 'mizuki-booking' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>

	<?php if ( $next ) : ?>
		<h4 class="mzk-dash__title"><?php esc_html_e( 'Your next class', 'mizuki-booking' ); ?></h4>

		<article class="mzk-next" style="--mzk-class: <?php echo esc_attr( $next->class_colour ); ?>">
			<div class="mzk-next__when">
				<span class="mzk-next__day"><?php echo esc_html( wp_date( 'j', MZK_Utils::session_start( $next )->getTimestamp() ) ); ?></span>
				<span class="mzk-next__month"><?php echo esc_html( wp_date( 'M', MZK_Utils::session_start( $next )->getTimestamp() ) ); ?></span>
			</div>

			<div class="mzk-next__body">
				<h3 class="mzk-next__title"><?php echo esc_html( $next->class_name ); ?></h3>
				<p class="mzk-next__meta">
					<span><?php echo esc_html( wp_date( 'l', MZK_Utils::session_start( $next )->getTimestamp() ) ); ?></span>
					<span><?php echo esc_html( $next->time_label ); ?></span>
					<span><?php echo esc_html( $next->duration_label ); ?></span>
				</p>

				<?php if ( 'awaiting_approval' === $next->status ) : ?>
					<p class="mzk-note"><?php esc_html_e( 'Waiting for the studio to confirm. Your place is held.', 'mizuki-booking' ); ?></p>
				<?php elseif ( $next->can_reschedule ) : ?>
					<p class="mzk-note"><?php esc_html_e( 'Need to change it? Use “My booked sessions” below.', 'mizuki-booking' ); ?></p>
				<?php elseif ( $next->reschedule_block_note ) : ?>
					<p class="mzk-note"><?php echo esc_html( $next->reschedule_block_note ); ?></p>
				<?php endif; ?>
			</div>

			<span class="mzk-badge mzk-next__badge"><?php echo esc_html( $next->status_label ); ?></span>
		</article>
	<?php endif; ?>

	<h4 class="mzk-dash__title"><?php esc_html_e( 'My booked sessions', 'mizuki-booking' ); ?></h4>

	<?php if ( ! $next ) : ?>
		<div class="mzk-empty">
			<p class="mzk-empty__title"><?php esc_html_e( 'Nothing booked yet', 'mizuki-booking' ); ?></p>
			<p class="mzk-note"><?php esc_html_e( 'Use “Book a session” above to choose a date that suits you.', 'mizuki-booking' ); ?></p>
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

	<p class="mzk-portal__foot">
		<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log out', 'mizuki-booking' ); ?></a>
	</p>
</div>
