<?php
/**
 * Admin blocked dates: studio closures that hide sessions from students.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$current = $edit_id ? MZK_Blackouts::get( $edit_id ) : null;
$types   = MZK_Class_Types::all();
$rows    = MZK_Blackouts::all();
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Blocked Dates', 'mizuki-booking' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Block the days the studio is closed. Sessions on those days are hidden from the booking calendar straight away and are skipped when the schedule is generated.', 'mizuki-booking' ); ?>
	</p>

	<div class="mzk-columns">
		<div class="mzk-card">
			<h2><?php echo $current ? esc_html__( 'Edit blocked dates', 'mizuki-booking' ) : esc_html__( 'Block dates', 'mizuki-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'save_blackout' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $current ? $current->id : 0 ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mzk-bo-start"><?php esc_html_e( 'From', 'mizuki-booking' ); ?></label></th>
						<td><input type="date" id="mzk-bo-start" name="start_date" required value="<?php echo esc_attr( $current ? $current->start_date : MZK_Utils::today() ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mzk-bo-end"><?php esc_html_e( 'To', 'mizuki-booking' ); ?></label></th>
						<td>
							<input type="date" id="mzk-bo-end" name="end_date" value="<?php echo esc_attr( $current ? $current->end_date : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty to block a single day.', 'mizuki-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mzk-bo-class"><?php esc_html_e( 'Applies to', 'mizuki-booking' ); ?></label></th>
						<td>
							<select name="class_type_id" id="mzk-bo-class">
								<option value="0"><?php esc_html_e( 'All classes (studio closed)', 'mizuki-booking' ); ?></option>
								<?php foreach ( $types as $type ) : ?>
									<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $current ? (int) $current->class_type_id : 0, (int) $type->id ); ?>>
										<?php echo esc_html( $type->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mzk-bo-reason"><?php esc_html_e( 'Reason', 'mizuki-booking' ); ?></label></th>
						<td>
							<input type="text" id="mzk-bo-reason" name="reason" class="regular-text"
								value="<?php echo esc_attr( $current ? $current->reason : '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. Chinese New Year, studio holiday', 'mizuki-booking' ); ?>" />
							<p class="description"><?php esc_html_e( 'Shown on the calendar when a student hovers the date.', 'mizuki-booking' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save blocked dates', 'mizuki-booking' ); ?></button>
					<?php if ( $current ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-blackouts' ) ); ?>"><?php esc_html_e( 'Add another', 'mizuki-booking' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<div class="mzk-card">
			<h2><?php esc_html_e( 'Blocked periods', 'mizuki-booking' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Dates', 'mizuki-booking' ); ?></th>
						<th><?php esc_html_e( 'Applies to', 'mizuki-booking' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'mizuki-booking' ); ?></th>
						<th><?php esc_html_e( 'Bookings affected', 'mizuki-booking' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No blocked dates yet.', 'mizuki-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $affected = MZK_Blackouts::affected_bookings( $row->id ); ?>
					<tr>
						<td>
							<?php echo esc_html( MZK_Utils::format_date( $row->start_date ) ); ?>
							<?php if ( $row->end_date !== $row->start_date ) : ?>
								&ndash; <?php echo esc_html( MZK_Utils::format_date( $row->end_date ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( (int) $row->class_type_id ) {
								$type = MZK_Class_Types::get( $row->class_type_id );
								echo esc_html( $type ? $type->name : '—' );
							} else {
								esc_html_e( 'All classes', 'mizuki-booking' );
							}
							?>
						</td>
						<td><?php echo esc_html( $row->reason ); ?></td>
						<td>
							<?php if ( $affected ) : ?>
								<a class="mzk-danger" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-bookings&from=' . $row->start_date . '&to=' . $row->end_date ) ); ?>">
									<?php echo esc_html( count( $affected ) ); ?>
								</a>
							<?php else : ?>
								<span class="mzk-muted">0</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-blackouts&edit=' . $row->id ) ); ?>"><?php esc_html_e( 'Edit', 'mizuki-booking' ); ?></a>
							|
							<a class="mzk-danger" data-mzk-confirm href="<?php echo esc_url( MZK_Admin::action_url( 'delete_blackout', array( 'id' => $row->id ) ) ); ?>">
								<?php esc_html_e( 'Remove', 'mizuki-booking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
