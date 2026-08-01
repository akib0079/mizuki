<?php
/**
 * Admin course packages: balances, editing, and session extensions.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$f_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$f_class  = isset( $_GET['class_type'] ) ? (int) $_GET['class_type'] : 0;
$f_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$course_types = array_filter(
	MZK_Class_Types::all(),
	static function ( $type ) {
		return ! empty( $type->course_based );
	}
);

$current = $edit_id ? MZK_Enrollments::get( $edit_id ) : null;

$rows = MZK_Enrollments::query(
	array(
		'status'        => $f_status,
		'class_type_id' => $f_class,
		'search'        => $f_search,
		'limit'         => 200,
	)
);

$status_labels = array(
	'active'    => __( 'Active', 'mizuki-booking' ),
	'completed' => __( 'Completed', 'mizuki-booking' ),
	'paused'    => __( 'Paused', 'mizuki-booking' ),
	'cancelled' => __( 'Cancelled', 'mizuki-booking' ),
);
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Course Packages', 'mizuki-booking' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'IFDA and Preserved Flower students buy a fixed number of sessions. Each confirmed booking uses one; extend the package whenever a student needs more time.', 'mizuki-booking' ); ?>
	</p>

	<?php if ( ! $course_types ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: %s: link to the classes screen. */
					esc_html__( 'No course-based classes yet. Mark a class as course-based on the %s screen first.', 'mizuki-booking' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=mzk-classes' ) ) . '">' . esc_html__( 'Classes & Rules', 'mizuki-booking' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" class="mzk-toolbar">
		<input type="hidden" name="page" value="mzk-enrollments" />
		<input type="search" name="s" value="<?php echo esc_attr( $f_search ); ?>" placeholder="<?php esc_attr_e( 'Name, e-mail or phone', 'mizuki-booking' ); ?>" />
		<select name="status">
			<option value=""><?php esc_html_e( 'Any status', 'mizuki-booking' ); ?></option>
			<?php foreach ( $status_labels as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="class_type">
			<option value="0"><?php esc_html_e( 'All courses', 'mizuki-booking' ); ?></option>
			<?php foreach ( $course_types as $type ) : ?>
				<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $f_class, (int) $type->id ); ?>><?php echo esc_html( $type->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mizuki-booking' ); ?></button>
	</form>

	<table class="widefat striped mzk-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Course', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Sessions', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No course packages yet.', 'mizuki-booking' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $row->student_name ); ?></strong><br />
					<a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a>
				</td>
				<td>
					<span class="mzk-swatch" style="background: <?php echo esc_attr( $row->class_colour ); ?>"></span>
					<?php echo esc_html( $row->class_name ); ?>
				</td>
				<td>
					<strong><?php echo esc_html( $row->sessions_used ); ?></strong> / <?php echo esc_html( $row->sessions_total ); ?>
					<div class="mzk-bar">
						<span style="width: <?php echo esc_attr( $row->sessions_total ? min( 100, round( 100 * $row->sessions_used / $row->sessions_total ) ) : 0 ); ?>%"></span>
					</div>
					<span class="mzk-muted">
						<?php
						printf(
							/* translators: %d: sessions remaining. */
							esc_html__( '%d left', 'mizuki-booking' ),
							(int) $row->sessions_left
						);
						?>
					</span>
				</td>
				<td>
					<?php echo esc_html( $row->expiry_label ); ?>
					<?php if ( $row->is_expired ) : ?>
						<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'Expired', 'mizuki-booking' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $status_labels[ $row->status ] ?? $row->status ); ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-enrollments&edit=' . $row->id ) ); ?>"><?php esc_html_e( 'Manage', 'mizuki-booking' ); ?></a>
					|
					<a class="mzk-danger" data-mzk-confirm href="<?php echo esc_url( MZK_Admin::action_url( 'delete_enrollment', array( 'id' => $row->id ) ) ); ?>">
						<?php esc_html_e( 'Delete', 'mizuki-booking' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="mzk-columns">
		<div class="mzk-card">
			<h2><?php echo $current ? esc_html__( 'Edit package', 'mizuki-booking' ) : esc_html__( 'Add a course package', 'mizuki-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'save_enrollment' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $current ? $current->id : 0 ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Course', 'mizuki-booking' ); ?></th>
						<td>
							<select name="class_type_id" required>
								<?php foreach ( $course_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $current ? (int) $current->class_type_id : 0, (int) $type->id ); ?>>
										<?php echo esc_html( $type->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Student name', 'mizuki-booking' ); ?></th>
						<td><input type="text" name="student_name" class="regular-text" required value="<?php echo esc_attr( $current ? $current->student_name : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'E-mail', 'mizuki-booking' ); ?></th>
						<td>
							<input type="email" name="email" class="regular-text" required value="<?php echo esc_attr( $current ? $current->email : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Bookings made with this address will draw from the package automatically.', 'mizuki-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone', 'mizuki-booking' ); ?></th>
						<td><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr( $current ? $current->phone : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sessions purchased', 'mizuki-booking' ); ?></th>
						<td><input type="number" name="sessions_total" min="1" required value="<?php echo esc_attr( $current ? $current->sessions_total : 25 ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Start date', 'mizuki-booking' ); ?></th>
						<td><input type="date" name="start_date" value="<?php echo esc_attr( $current ? $current->start_date : MZK_Utils::today() ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Expiry date', 'mizuki-booking' ); ?></th>
						<td>
							<input type="date" name="expiry_date" value="<?php echo esc_attr( $current ? $current->expiry_date : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty for no expiry.', 'mizuki-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></th>
						<td>
							<select name="status">
								<?php foreach ( $status_labels as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current ? $current->status : 'active', $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notes', 'mizuki-booking' ); ?></th>
						<td><textarea name="notes" rows="3" class="large-text"><?php echo esc_textarea( $current ? $current->notes : '' ); ?></textarea></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save package', 'mizuki-booking' ); ?></button>
					<?php if ( $current ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-enrollments' ) ); ?>"><?php esc_html_e( 'New package', 'mizuki-booking' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<?php if ( $current ) : ?>
			<div class="mzk-card">
				<h2><?php esc_html_e( 'Extend this package', 'mizuki-booking' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: 1: used, 2: total, 3: expiry. */
						esc_html__( '%1$d of %2$d sessions used. Expires: %3$s.', 'mizuki-booking' ),
						(int) $current->sessions_used,
						(int) $current->sessions_total,
						esc_html( $current->expiry_label )
					);
					?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php MZK_Admin::form_fields( 'extend_enrollment' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( $current->id ); ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Add sessions', 'mizuki-booking' ); ?></th>
							<td>
								<input type="number" name="add_sessions" step="1" value="0" />
								<p class="description"><?php esc_html_e( 'Use a negative number to correct a mistake.', 'mizuki-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'New expiry date', 'mizuki-booking' ); ?></th>
							<td><input type="date" name="new_expiry" value="" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Reason', 'mizuki-booking' ); ?></th>
							<td><input type="text" name="reason" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. medical leave, travel', 'mizuki-booking' ); ?>" /></td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Apply extension', 'mizuki-booking' ); ?></button></p>
				</form>

				<h3><?php esc_html_e( 'Extension history', 'mizuki-booking' ); ?></h3>
				<?php $log = MZK_Enrollments::log( $current->id ); ?>
				<?php if ( ! $log ) : ?>
					<p><?php esc_html_e( 'No extensions yet.', 'mizuki-booking' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'When', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Sessions', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Expiry', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Reason', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'By', 'mizuki-booking' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $log as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $entry->created_at ) ); ?></td>
								<td><?php echo esc_html( ( $entry->delta_sessions > 0 ? '+' : '' ) . $entry->delta_sessions ); ?></td>
								<td><?php echo esc_html( $entry->new_expiry ? MZK_Utils::format_date( $entry->new_expiry ) : '—' ); ?></td>
								<td><?php echo esc_html( $entry->reason ); ?></td>
								<td>
									<?php
									$actor = $entry->actor_id ? get_userdata( $entry->actor_id ) : null;
									echo esc_html( $actor ? $actor->display_name : '—' );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Sessions used', 'mizuki-booking' ); ?></h3>
				<?php $used = MZK_Bookings::query( array( 'enrollment_id' => $current->id ) ); ?>
				<?php if ( ! $used ) : ?>
					<p><?php esc_html_e( 'No bookings against this package yet.', 'mizuki-booking' ); ?></p>
				<?php else : ?>
					<ul class="mzk-list-plain">
						<?php foreach ( $used as $booking ) : ?>
							<li>
								<?php echo esc_html( $booking->date_label . ' · ' . $booking->time_label ); ?>
								<span class="mzk-tag"><?php echo esc_html( $booking->status_label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
