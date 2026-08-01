<?php
/**
 * Admin bookings: filterable list, manual booking entry, move/cancel actions.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$f_status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$f_class   = isset( $_GET['class_type'] ) ? (int) $_GET['class_type'] : 0;
$f_from    = isset( $_GET['from'] ) ? MZK_Utils::sanitize_date( wp_unslash( $_GET['from'] ) ) : '';
$f_to      = isset( $_GET['to'] ) ? MZK_Utils::sanitize_date( wp_unslash( $_GET['to'] ) ) : '';
$f_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$f_session = isset( $_GET['session_id'] ) ? (int) $_GET['session_id'] : 0;
$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$per_page  = 40;
$types     = MZK_Class_Types::all();
$statuses  = MZK_Utils::booking_statuses();

$args = array(
	'status'        => $f_status,
	'class_type_id' => $f_class,
	'from'          => $f_from,
	'to'            => $f_to,
	'search'        => $f_search,
	'session_id'    => $f_session,
	'limit'         => $per_page,
	'offset'        => ( $paged - 1 ) * $per_page,
	'orderby'       => 's.session_date DESC, s.start_time ASC',
);
if ( ! $f_from && ! $f_to && ! $f_session && ! $f_search ) {
	$args['upcoming'] = true;
	$args['orderby']  = 's.session_date ASC, s.start_time ASC';
}

$bookings = MZK_Bookings::query( $args );

// Upcoming sessions grouped per class, for the move + manual booking pickers.
$upcoming_sessions = MZK_Sessions::query(
	array(
		'from'  => MZK_Utils::today(),
		'to'    => gmdate( 'Y-m-d', strtotime( MZK_Utils::today() . ' +6 months' ) ),
		'limit' => 600,
	)
);
$by_class = array();
foreach ( $upcoming_sessions as $session ) {
	$by_class[ (int) $session->class_type_id ][] = $session;
}

/**
 * Render a <select> of sessions for one class.
 *
 * @param array $sessions Session rows.
 * @param int   $exclude  Session id to skip.
 */
$session_options = static function ( $sessions, $exclude = 0 ) {
	foreach ( (array) $sessions as $session ) {
		if ( (int) $session->id === (int) $exclude ) {
			continue;
		}
		printf(
			'<option value="%1$d">%2$s</option>',
			(int) $session->id,
			esc_html(
				$session->date_label . ' · ' . $session->time_label . ' · ' .
				sprintf(
					/* translators: 1: seats taken, 2: capacity. */
					__( '%1$d/%2$d booked', 'mizuki-booking' ),
					(int) $session->seats_taken,
					(int) $session->effective_capacity
				)
			)
		);
	}
};
?>
<div class="wrap mzk-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'mizuki-booking' ); ?></h1>
	<a href="#mzk-add-booking" class="page-title-action"><?php esc_html_e( 'Add booking', 'mizuki-booking' ); ?></a>
	<hr class="wp-header-end" />

	<form method="get" class="mzk-toolbar">
		<input type="hidden" name="page" value="mzk-bookings" />
		<input type="search" name="s" value="<?php echo esc_attr( $f_search ); ?>" placeholder="<?php esc_attr_e( 'Name, e-mail or phone', 'mizuki-booking' ); ?>" />
		<select name="status">
			<option value=""><?php esc_html_e( 'Any status', 'mizuki-booking' ); ?></option>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="class_type">
			<option value="0"><?php esc_html_e( 'All classes', 'mizuki-booking' ); ?></option>
			<?php foreach ( $types as $type ) : ?>
				<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $f_class, (int) $type->id ); ?>><?php echo esc_html( $type->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<label><?php esc_html_e( 'From', 'mizuki-booking' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $f_from ); ?>" /></label>
		<label><?php esc_html_e( 'To', 'mizuki-booking' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $f_to ); ?>" /></label>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mizuki-booking' ); ?></button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-bookings' ) ); ?>"><?php esc_html_e( 'Reset', 'mizuki-booking' ); ?></a>
	</form>

	<table class="widefat striped mzk-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Session', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Student', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Package', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'mizuki-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $bookings ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No bookings found.', 'mizuki-booking' ); ?></td></tr>
		<?php endif; ?>

		<?php foreach ( $bookings as $booking ) : ?>
			<tr>
				<td>
					<span class="mzk-swatch" style="background: <?php echo esc_attr( $booking->class_colour ); ?>"></span>
					<strong><?php echo esc_html( $booking->class_name ); ?></strong><br />
					<?php echo esc_html( $booking->date_label ); ?> · <?php echo esc_html( $booking->time_label ); ?>
					<?php if ( $booking->reschedule_count ) : ?>
						<br /><span class="mzk-muted">
							<?php
							printf(
								/* translators: %d: number of times rescheduled. */
								esc_html__( 'Rescheduled %d×', 'mizuki-booking' ),
								(int) $booking->reschedule_count
							);
							?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<strong><?php echo esc_html( $booking->student_name ); ?></strong><br />
					<a href="mailto:<?php echo esc_attr( $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a>
					<?php if ( $booking->phone ) : ?><br /><?php echo esc_html( $booking->phone ); ?><?php endif; ?>
					<br /><span class="mzk-muted"><?php echo esc_html( $booking->source ); ?></span>
					<?php if ( ! empty( $booking->order_id ) && function_exists( 'wc_get_order' ) ) : ?>
						<?php $order = wc_get_order( (int) $booking->order_id ); ?>
						<?php if ( $order ) : ?>
							<br />
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
								<?php
								printf(
									/* translators: 1: order number, 2: order status. */
									esc_html__( 'Order #%1$s (%2$s)', 'mizuki-booking' ),
									esc_html( $order->get_order_number() ),
									esc_html( wc_get_order_status_name( $order->get_status() ) )
								);
								?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $booking->enrollment_id ) : ?>
						<?php $enrollment = MZK_Enrollments::get( $booking->enrollment_id ); ?>
						<?php if ( $enrollment ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-enrollments&edit=' . $enrollment->id ) ); ?>">
								<?php
								printf(
									/* translators: 1: used, 2: total. */
									esc_html__( '%1$d of %2$d used', 'mizuki-booking' ),
									(int) $enrollment->sessions_used,
									(int) $enrollment->sessions_total
								);
								?>
							</a>
						<?php endif; ?>
					<?php else : ?>
						<span class="mzk-muted">&mdash;</span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $booking->status_label ); ?></td>
				<td class="mzk-actions">
					<?php if ( 'confirmed' === $booking->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-inline">
							<?php MZK_Admin::form_fields( 'move_booking' ); ?>
							<input type="hidden" name="id" value="<?php echo esc_attr( $booking->id ); ?>" />
							<select name="session_id" required>
								<option value=""><?php esc_html_e( 'Move to…', 'mizuki-booking' ); ?></option>
								<?php $session_options( $by_class[ (int) $booking->class_type_id ] ?? array(), $booking->session_id ); ?>
							</select>
							<label class="mzk-muted"><input type="checkbox" name="skip_emails" value="1" /> <?php esc_html_e( 'no e-mail', 'mizuki-booking' ); ?></label>
							<button type="submit" class="button button-small"><?php esc_html_e( 'Move', 'mizuki-booking' ); ?></button>
						</form>

						<a class="button button-small" data-mzk-confirm-cancel
							href="<?php echo esc_url( MZK_Admin::action_url( 'cancel_booking', array( 'id' => $booking->id ) ) ); ?>">
							<?php esc_html_e( 'Cancel', 'mizuki-booking' ); ?>
						</a>
						<a class="button button-small"
							href="<?php echo esc_url( MZK_Admin::action_url( 'booking_status', array( 'id' => $booking->id, 'status' => 'attended' ) ) ); ?>">
							<?php esc_html_e( 'Attended', 'mizuki-booking' ); ?>
						</a>
						<a class="button button-small"
							href="<?php echo esc_url( MZK_Admin::action_url( 'booking_status', array( 'id' => $booking->id, 'status' => 'no_show' ) ) ); ?>">
							<?php esc_html_e( 'No show', 'mizuki-booking' ); ?>
						</a>
					<?php else : ?>
						<a class="button button-small"
							href="<?php echo esc_url( MZK_Admin::action_url( 'booking_status', array( 'id' => $booking->id, 'status' => 'confirmed' ) ) ); ?>">
							<?php esc_html_e( 'Restore', 'mizuki-booking' ); ?>
						</a>
					<?php endif; ?>

					<a class="button button-small"
						href="<?php echo esc_url( MZK_Admin::action_url( 'resend_confirmation', array( 'id' => $booking->id ) ) ); ?>">
						<?php esc_html_e( 'Resend e-mail', 'mizuki-booking' ); ?>
					</a>
					<a class="mzk-danger" data-mzk-confirm
						href="<?php echo esc_url( MZK_Admin::action_url( 'delete_booking', array( 'id' => $booking->id ) ) ); ?>">
						<?php esc_html_e( 'Delete', 'mizuki-booking' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( count( $bookings ) >= $per_page || $paged > 1 ) : ?>
		<p class="mzk-pager">
			<?php if ( $paged > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>">&laquo; <?php esc_html_e( 'Previous', 'mizuki-booking' ); ?></a>
			<?php endif; ?>
			<?php if ( count( $bookings ) >= $per_page ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>"><?php esc_html_e( 'Next', 'mizuki-booking' ); ?> &raquo;</a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<div class="mzk-card" id="mzk-add-booking">
		<h2><?php esc_html_e( 'Add a booking manually', 'mizuki-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'For students who signed up over chat or in person. This takes a place on the session just like an online booking.', 'mizuki-booking' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php MZK_Admin::form_fields( 'save_booking' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mzk-b-session"><?php esc_html_e( 'Session', 'mizuki-booking' ); ?></label></th>
					<td>
						<select name="session_id" id="mzk-b-session" required>
							<option value=""><?php esc_html_e( 'Choose a session…', 'mizuki-booking' ); ?></option>
							<?php foreach ( $types as $type ) : ?>
								<?php if ( empty( $by_class[ (int) $type->id ] ) ) { continue; } ?>
								<optgroup label="<?php echo esc_attr( $type->name ); ?>">
									<?php $session_options( $by_class[ (int) $type->id ] ); ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-b-name"><?php esc_html_e( 'Student name', 'mizuki-booking' ); ?></label></th>
					<td><input type="text" id="mzk-b-name" name="student_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-b-email"><?php esc_html_e( 'E-mail', 'mizuki-booking' ); ?></label></th>
					<td><input type="email" id="mzk-b-email" name="email" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-b-phone"><?php esc_html_e( 'Phone', 'mizuki-booking' ); ?></label></th>
					<td><input type="text" id="mzk-b-phone" name="phone" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-b-source"><?php esc_html_e( 'Source', 'mizuki-booking' ); ?></label></th>
					<td>
						<select name="source" id="mzk-b-source">
							<option value="chat"><?php esc_html_e( 'Chat', 'mizuki-booking' ); ?></option>
							<option value="phone"><?php esc_html_e( 'Phone', 'mizuki-booking' ); ?></option>
							<option value="admin"><?php esc_html_e( 'Studio', 'mizuki-booking' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'mizuki-booking' ); ?></th>
					<td>
						<label><input type="checkbox" name="allow_overbook" value="1" /> <?php esc_html_e( 'Allow going over the participant limit', 'mizuki-booking' ); ?></label><br />
						<label><input type="checkbox" name="skip_emails" value="1" /> <?php esc_html_e( 'Do not send the confirmation e-mail', 'mizuki-booking' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-b-notes"><?php esc_html_e( 'Notes', 'mizuki-booking' ); ?></label></th>
					<td><textarea id="mzk-b-notes" name="notes" rows="2" class="large-text"></textarea></td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Add booking', 'mizuki-booking' ); ?></button></p>
		</form>
	</div>
</div>
