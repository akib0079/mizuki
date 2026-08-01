<?php
/**
 * Front-end studio manager.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

$tab      = MZK_Manage::tab();
$notices  = MZK_Admin::take_notices();
$types    = MZK_Class_Types::all( true );
$statuses = MZK_Utils::session_statuses();
$weekdays = MZK_Utils::weekdays();
?>
<div class="mzk-manage mzk-studio">

	<?php foreach ( $notices as $notice ) : ?>
		<?php if ( empty( $notice['message'] ) ) { continue; } ?>
		<div class="mzk-notice mzk-notice--<?php echo 'error' === $notice['type'] ? 'error' : 'info'; ?>">
			<?php echo esc_html( $notice['message'] ); ?>
		</div>
	<?php endforeach; ?>

	<nav class="mzk-studio__tabs">
		<?php foreach ( MZK_Manage::tabs() as $slug => $label ) : ?>
			<a class="mzk-studio__tab <?php echo $tab === $slug ? 'is-active' : ''; ?>"
				href="<?php echo esc_url( MZK_Manage::tab_url( $slug ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'approvals' === $tab ) : ?>

		<?php $pending = MZK_Students::pending(); ?>
		<h4 class="mzk-dash__title"><?php esc_html_e( 'Registrations waiting for you', 'mizuki-booking' ); ?></h4>

		<?php if ( ! $pending ) : ?>
			<div class="mzk-notice mzk-notice--info"><?php esc_html_e( 'Nothing waiting — you are all caught up.', 'mizuki-booking' ); ?></div>
		<?php else : ?>
			<?php foreach ( $pending as $booking ) : ?>
				<div class="mzk-booking mzk-booking--waiting">
					<h3 class="mzk-booking__title"><?php echo esc_html( $booking->student_name ); ?></h3>
					<div class="mzk-booking__meta">
						<span><?php echo esc_html( $booking->class_name ); ?></span>
						<span><?php echo esc_html( $booking->date_label ); ?></span>
						<span><?php echo esc_html( $booking->time_label ); ?></span>
					</div>
					<p class="mzk-note">
						<a href="mailto:<?php echo esc_attr( $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a>
						<?php echo $booking->phone ? ' · ' . esc_html( $booking->phone ) : ''; ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-inline-form">
						<?php MZK_Manage::form_fields( 'decline_booking' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
						<input type="text" name="reason" class="mzk-input"
							placeholder="<?php esc_attr_e( 'Reason (optional, shown to the student)', 'mizuki-booking' ); ?>" />
						<button type="submit" class="mzk-btn mzk-btn--ghost"><?php esc_html_e( 'Decline', 'mizuki-booking' ); ?></button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php MZK_Manage::form_fields( 'approve_booking' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
						<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Approve this place', 'mizuki-booking' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

	<?php elseif ( 'sessions' === $tab ) : ?>

		<h4 class="mzk-dash__title"><?php esc_html_e( 'Add a session', 'mizuki-booking' ); ?></h4>

		<div class="mzk-booking">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form mzk-form--grid">
				<?php MZK_Manage::form_fields( 'save_session' ); ?>
				<input type="hidden" name="id" value="0" />

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></span>
					<select name="class_type_id" class="mzk-input" required>
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( $type->id ); ?>"><?php echo esc_html( $type->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Date', 'mizuki-booking' ); ?></span>
					<input type="date" name="session_date" class="mzk-input" required value="<?php echo esc_attr( MZK_Utils::today() ); ?>" />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Start time', 'mizuki-booking' ); ?></span>
					<input type="time" name="start_time" class="mzk-input" required value="10:00" />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Minutes', 'mizuki-booking' ); ?></span>
					<input type="number" name="duration_minutes" class="mzk-input" min="15" step="15" value="120" required />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Places', 'mizuki-booking' ); ?></span>
					<input type="number" name="capacity" class="mzk-input" min="1" value="6" required />
				</label>

				<div class="mzk-form__actions">
					<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Add session', 'mizuki-booking' ); ?></button>
				</div>
			</form>
		</div>

		<h4 class="mzk-dash__title"><?php esc_html_e( 'Next 6 weeks', 'mizuki-booking' ); ?></h4>

		<?php
		$sessions = MZK_Sessions::query(
			array(
				'from'  => MZK_Utils::today(),
				'to'    => MZK_Utils::now()->modify( '+6 weeks' )->format( 'Y-m-d' ),
				'limit' => 200,
			)
		);
		?>

		<?php if ( ! $sessions ) : ?>
			<div class="mzk-notice mzk-notice--info"><?php esc_html_e( 'No sessions yet. Add one above, or set a weekly pattern in the admin.', 'mizuki-booking' ); ?></div>
		<?php else : ?>
			<ul class="mzk-sessions">
				<?php foreach ( $sessions as $session ) : ?>
					<li class="mzk-session" style="--mzk-class: <?php echo esc_attr( $session->class_colour ); ?>">
						<div class="mzk-session__head">
							<span class="mzk-session__class"><?php echo esc_html( $session->class_name ); ?></span>
							<span class="mzk-session__time"><?php echo esc_html( $session->time_label ); ?></span>
						</div>
						<div class="mzk-session__meta">
							<span><?php echo esc_html( $session->date_label ); ?></span>
							<span>
								<strong><?php echo esc_html( $session->seats_taken ); ?></strong>
								/ <?php echo esc_html( $session->effective_capacity ); ?>
								<?php esc_html_e( 'booked', 'mizuki-booking' ); ?>
							</span>
							<?php if ( 'open' !== $session->status ) : ?>
								<span class="mzk-badge"><?php echo esc_html( $statuses[ $session->status ] ); ?></span>
							<?php endif; ?>
							<?php if ( $session->is_blacked_out ) : ?>
								<span class="mzk-badge"><?php esc_html_e( 'Studio closed', 'mizuki-booking' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="mzk-session__tools">
							<a class="mzk-mini" title="<?php esc_attr_e( 'Offer one place fewer', 'mizuki-booking' ); ?>"
								href="<?php echo esc_url( MZK_Manage::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => -1 ) ) ); ?>">&minus;</a>
							<a class="mzk-mini" title="<?php esc_attr_e( 'Offer one more place', 'mizuki-booking' ); ?>"
								href="<?php echo esc_url( MZK_Manage::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => 1 ) ) ); ?>">+</a>
							<?php if ( 'open' === $session->status ) : ?>
								<a class="mzk-mini" href="<?php echo esc_url( MZK_Manage::action_url( 'session_status', array( 'id' => $session->id, 'status' => 'closed' ) ) ); ?>">
									<?php esc_html_e( 'Hide', 'mizuki-booking' ); ?>
								</a>
							<?php else : ?>
								<a class="mzk-mini" href="<?php echo esc_url( MZK_Manage::action_url( 'session_status', array( 'id' => $session->id, 'status' => 'open' ) ) ); ?>">
									<?php esc_html_e( 'Show', 'mizuki-booking' ); ?>
								</a>
							<?php endif; ?>
							<a class="mzk-mini" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=add&id=' . $session->id ) ); ?>">
								<?php esc_html_e( 'Edit', 'mizuki-booking' ); ?>
							</a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

	<?php elseif ( 'closures' === $tab ) : ?>

		<h4 class="mzk-dash__title"><?php esc_html_e( 'Close the studio', 'mizuki-booking' ); ?></h4>

		<div class="mzk-booking">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-form mzk-form--grid">
				<?php MZK_Manage::form_fields( 'save_blackout' ); ?>
				<input type="hidden" name="id" value="0" />

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'From', 'mizuki-booking' ); ?></span>
					<input type="date" name="start_date" class="mzk-input" required value="<?php echo esc_attr( MZK_Utils::today() ); ?>" />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'To (optional)', 'mizuki-booking' ); ?></span>
					<input type="date" name="end_date" class="mzk-input" />
				</label>

				<label class="mzk-field">
					<span class="mzk-field__label"><?php esc_html_e( 'Reason', 'mizuki-booking' ); ?></span>
					<input type="text" name="reason" class="mzk-input"
						placeholder="<?php esc_attr_e( 'e.g. Chinese New Year', 'mizuki-booking' ); ?>" />
				</label>

				<div class="mzk-form__actions">
					<button type="submit" class="mzk-btn mzk-btn--primary"><?php esc_html_e( 'Block these dates', 'mizuki-booking' ); ?></button>
				</div>
			</form>
		</div>

		<?php $closures = MZK_Blackouts::all( array( 'from' => MZK_Utils::today() ) ); ?>
		<?php if ( $closures ) : ?>
			<h4 class="mzk-dash__title"><?php esc_html_e( 'Coming up', 'mizuki-booking' ); ?></h4>
			<ul class="mzk-history">
				<?php foreach ( $closures as $closure ) : ?>
					<li>
						<span class="mzk-history__date">
							<?php echo esc_html( MZK_Utils::format_date( $closure->start_date ) ); ?>
							<?php if ( $closure->end_date !== $closure->start_date ) : ?>
								&ndash; <?php echo esc_html( MZK_Utils::format_date( $closure->end_date ) ); ?>
							<?php endif; ?>
						</span>
						<span class="mzk-history__class"><?php echo esc_html( $closure->reason ); ?></span>
						<a class="mzk-mini" href="<?php echo esc_url( MZK_Manage::action_url( 'delete_blackout', array( 'id' => $closure->id ) ) ); ?>">
							<?php esc_html_e( 'Remove', 'mizuki-booking' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

	<?php else : ?>

		<?php
		$next = MZK_Sessions::query(
			array(
				'from'   => MZK_Utils::today(),
				'to'     => MZK_Utils::now()->modify( '+14 days' )->format( 'Y-m-d' ),
				'status' => 'open',
				'limit'  => 40,
			)
		);
		?>

		<h4 class="mzk-dash__title"><?php esc_html_e( 'The next two weeks', 'mizuki-booking' ); ?></h4>

		<?php if ( ! $next ) : ?>
			<div class="mzk-notice mzk-notice--info"><?php esc_html_e( 'No sessions in the next two weeks.', 'mizuki-booking' ); ?></div>
		<?php else : ?>
			<?php foreach ( $next as $session ) : ?>
				<?php
				$students = MZK_Bookings::query(
					array(
						'session_id' => $session->id,
						'statuses'   => MZK_Utils::occupying_statuses(),
					)
				);
				?>
				<div class="mzk-booking" style="--mzk-class: <?php echo esc_attr( $session->class_colour ); ?>">
					<h3 class="mzk-booking__title"><?php echo esc_html( $session->class_name ); ?></h3>
					<div class="mzk-booking__meta">
						<span><?php echo esc_html( $session->date_label ); ?></span>
						<span><?php echo esc_html( $session->time_label ); ?></span>
						<span class="mzk-badge">
							<?php
							printf(
								/* translators: 1: booked, 2: places. */
								esc_html__( '%1$d of %2$d', 'mizuki-booking' ),
								(int) $session->seats_taken,
								(int) $session->effective_capacity
							);
							?>
						</span>
					</div>

					<?php if ( $students ) : ?>
						<ul class="mzk-history">
							<?php foreach ( $students as $student ) : ?>
								<li>
									<span class="mzk-history__class"><?php echo esc_html( $student->student_name ); ?></span>
									<span class="mzk-note"><?php echo esc_html( $student->phone ? $student->phone : $student->email ); ?></span>
									<span class="mzk-badge"><?php echo esc_html( $student->status_label ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="mzk-note"><?php esc_html_e( 'Nobody booked yet.', 'mizuki-booking' ); ?></p>
					<?php endif; ?>

					<div class="mzk-session__tools">
						<a class="mzk-mini" href="<?php echo esc_url( MZK_Manage::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => -1 ) ) ); ?>">&minus;</a>
						<a class="mzk-mini" href="<?php echo esc_url( MZK_Manage::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => 1 ) ) ); ?>">+</a>
						<a class="mzk-mini" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-bookings&session_id=' . $session->id ) ); ?>">
							<?php esc_html_e( 'Full details', 'mizuki-booking' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

	<?php endif; ?>
</div>
