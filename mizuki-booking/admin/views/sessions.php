<?php
/**
 * Admin sessions: list, single session editor, weekly patterns + generator.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$tab        = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list';
$edit_id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$preset_date = isset( $_GET['date'] ) ? MZK_Utils::sanitize_date( wp_unslash( $_GET['date'] ) ) : '';
$filter_from = isset( $_GET['from'] ) ? MZK_Utils::sanitize_date( wp_unslash( $_GET['from'] ) ) : '';
$filter_to   = isset( $_GET['to'] ) ? MZK_Utils::sanitize_date( wp_unslash( $_GET['to'] ) ) : '';
$filter_type = isset( $_GET['class_type'] ) ? (int) $_GET['class_type'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$types    = MZK_Class_Types::all();
$statuses = MZK_Utils::session_statuses();
$weekdays = MZK_Utils::weekdays();

$tabs = array(
	'list'      => __( 'All sessions', 'mizuki-booking' ),
	'add'       => $edit_id ? __( 'Edit session', 'mizuki-booking' ) : __( 'Add session', 'mizuki-booking' ),
	'templates' => __( 'Weekly pattern', 'mizuki-booking' ),
);
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Sessions', 'mizuki-booking' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=' . $slug ) ); ?>"
				class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'add' === $tab ) : ?>

		<?php
		$session = $edit_id ? MZK_Sessions::get( $edit_id ) : null;
		$default_type = $types ? $types[0] : null;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzk-card">
			<?php MZK_Admin::form_fields( 'save_session' ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $session ? $session->id : 0 ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mzk-class"><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></label></th>
					<td>
						<select name="class_type_id" id="mzk-class" required data-mzk-class-defaults>
							<?php foreach ( $types as $type ) : ?>
								<option value="<?php echo esc_attr( $type->id ); ?>"
									data-duration="<?php echo esc_attr( $type->default_duration ); ?>"
									data-capacity="<?php echo esc_attr( $type->default_capacity ); ?>"
									<?php selected( $session ? (int) $session->class_type_id : ( $default_type ? (int) $default_type->id : 0 ), (int) $type->id ); ?>>
									<?php echo esc_html( $type->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-date"><?php esc_html_e( 'Date', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="date" id="mzk-date" name="session_date" required
							value="<?php echo esc_attr( $session ? $session->session_date : ( $preset_date ? $preset_date : MZK_Utils::today() ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-time"><?php esc_html_e( 'Start time', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="time" id="mzk-time" name="start_time" required
							value="<?php echo esc_attr( $session ? substr( $session->start_time, 0, 5 ) : '10:00' ); ?>" />
						<p class="description"><?php esc_html_e( 'Add one row per session — a day can have as many as you need.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-duration"><?php esc_html_e( 'Duration', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="number" id="mzk-duration" name="duration_minutes" min="15" step="15" required
							value="<?php echo esc_attr( $session ? $session->duration_minutes : ( $default_type ? $default_type->default_duration : 120 ) ); ?>" />
						<span><?php esc_html_e( 'minutes', 'mizuki-booking' ); ?></span>
						<p class="description"><?php esc_html_e( 'e.g. 120 for a 2 hour class, 240 for 4 hours.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-capacity"><?php esc_html_e( 'Participant limit', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="number" id="mzk-capacity" name="capacity" min="1" required
							value="<?php echo esc_attr( $session ? $session->capacity : ( $default_type ? $default_type->default_capacity : 6 ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Maximum number of students for this session. You can change it any time.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-adjust"><?php esc_html_e( 'Manual adjustment', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="number" id="mzk-adjust" name="capacity_adjustment" step="1"
							value="<?php echo esc_attr( $session ? $session->capacity_adjustment : 0 ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Negative holds places back for students who booked with you directly; positive opens extra places for this session only.', 'mizuki-booking' ); ?>
							<?php if ( $session ) : ?>
								<br />
								<strong>
									<?php
									printf(
										/* translators: 1: booked, 2: places offered online. */
										esc_html__( 'Currently %1$d booked of %2$d places offered online.', 'mizuki-booking' ),
										(int) $session->seats_taken,
										(int) $session->effective_capacity
									);
									?>
								</strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-title"><?php esc_html_e( 'Session name', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="text" id="mzk-title" name="title" class="regular-text"
							value="<?php echo esc_attr( $session ? $session->title : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional. Shown to students instead of the class name.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-status"><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></label></th>
					<td>
						<select name="status" id="mzk-status">
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $session ? $session->status : 'open', $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-notes"><?php esc_html_e( 'Internal notes', 'mizuki-booking' ); ?></label></th>
					<td><textarea id="mzk-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $session ? $session->notes : '' ); ?></textarea></td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save session', 'mizuki-booking' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions' ) ); ?>"><?php esc_html_e( 'Back to list', 'mizuki-booking' ); ?></a>
			</p>
		</form>

		<?php if ( $session ) : ?>
			<h2><?php esc_html_e( 'Participants', 'mizuki-booking' ); ?></h2>
			<?php
			$participants = MZK_Bookings::query( array( 'session_id' => $session->id ) );
			if ( ! $participants ) {
				echo '<p>' . esc_html__( 'Nobody has booked this session yet.', 'mizuki-booking' ) . '</p>';
			} else {
				echo '<table class="widefat striped"><thead><tr>';
				echo '<th>' . esc_html__( 'Student', 'mizuki-booking' ) . '</th>';
				echo '<th>' . esc_html__( 'Contact', 'mizuki-booking' ) . '</th>';
				echo '<th>' . esc_html__( 'Status', 'mizuki-booking' ) . '</th>';
				echo '<th>' . esc_html__( 'Source', 'mizuki-booking' ) . '</th>';
				echo '</tr></thead><tbody>';
				foreach ( $participants as $booking ) {
					echo '<tr>';
					echo '<td>' . esc_html( $booking->student_name ) . '</td>';
					echo '<td>' . esc_html( $booking->email ) . '<br />' . esc_html( $booking->phone ) . '</td>';
					echo '<td>' . esc_html( $booking->status_label ) . '</td>';
					echo '<td>' . esc_html( $booking->source ) . '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
			?>
		<?php endif; ?>

	<?php elseif ( 'templates' === $tab ) : ?>

		<div class="mzk-columns">
			<div class="mzk-card">
				<h2><?php esc_html_e( 'Weekly pattern', 'mizuki-booking' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Describe the sessions you normally run each week, then generate them across the coming months. Generated sessions can still be edited or cancelled individually.', 'mizuki-booking' ); ?>
				</p>

				<?php
				$templates = MZK_Sessions::templates();
				if ( $templates ) :
					?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Day', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Time', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Limit', 'mizuki-booking' ); ?></th>
								<th><?php esc_html_e( 'Active', 'mizuki-booking' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $templates as $tpl ) : ?>
								<tr>
									<td><?php echo esc_html( $weekdays[ (int) $tpl->weekday ] ); ?></td>
									<td>
										<?php echo esc_html( substr( $tpl->start_time, 0, 5 ) ); ?>
										<span class="mzk-muted">(<?php echo esc_html( MZK_Utils::format_duration( $tpl->duration_minutes ) ); ?>)</span>
									</td>
									<td><?php echo esc_html( $tpl->class_name ); ?></td>
									<td><?php echo esc_html( $tpl->capacity ); ?></td>
									<td><?php echo $tpl->active ? '&#10003;' : '&mdash;'; ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=templates&template=' . $tpl->id ) ); ?>">
											<?php esc_html_e( 'Edit', 'mizuki-booking' ); ?>
										</a>
										|
										<a class="mzk-danger" data-mzk-confirm
											href="<?php echo esc_url( MZK_Admin::action_url( 'delete_template', array( 'id' => $tpl->id ) ) ); ?>">
											<?php esc_html_e( 'Delete', 'mizuki-booking' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No weekly sessions defined yet.', 'mizuki-booking' ); ?></p>
				<?php endif; ?>

				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$tpl_id  = isset( $_GET['template'] ) ? (int) $_GET['template'] : 0;
				$current = $tpl_id ? MZK_Sessions::get_template( $tpl_id ) : null;
				?>
				<h3><?php echo $current ? esc_html__( 'Edit weekly session', 'mizuki-booking' ) : esc_html__( 'Add a weekly session', 'mizuki-booking' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php MZK_Admin::form_fields( 'save_template' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( $current ? $current->id : 0 ); ?>" />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></th>
							<td>
								<select name="class_type_id" required>
									<?php foreach ( $types as $type ) : ?>
										<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $current ? (int) $current->class_type_id : 0, (int) $type->id ); ?>>
											<?php echo esc_html( $type->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Day of week', 'mizuki-booking' ); ?></th>
							<td>
								<select name="weekday">
									<?php foreach ( $weekdays as $index => $label ) : ?>
										<option value="<?php echo esc_attr( $index ); ?>" <?php selected( $current ? (int) $current->weekday : 6, $index ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Start time', 'mizuki-booking' ); ?></th>
							<td><input type="time" name="start_time" required value="<?php echo esc_attr( $current ? substr( $current->start_time, 0, 5 ) : '10:00' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Duration (minutes)', 'mizuki-booking' ); ?></th>
							<td><input type="number" name="duration_minutes" min="15" step="15" value="<?php echo esc_attr( $current ? $current->duration_minutes : 120 ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Participant limit', 'mizuki-booking' ); ?></th>
							<td><input type="number" name="capacity" min="1" value="<?php echo esc_attr( $current ? $current->capacity : 6 ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Runs between', 'mizuki-booking' ); ?></th>
							<td>
								<input type="date" name="valid_from" value="<?php echo esc_attr( $current ? $current->valid_from : '' ); ?>" />
								&ndash;
								<input type="date" name="valid_until" value="<?php echo esc_attr( $current ? $current->valid_until : '' ); ?>" />
								<p class="description"><?php esc_html_e( 'Leave empty for no limit.', 'mizuki-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active', 'mizuki-booking' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="active" value="1" <?php checked( $current ? (int) $current->active : 1, 1 ); ?> />
									<?php esc_html_e( 'Include when generating the schedule', 'mizuki-booking' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save weekly session', 'mizuki-booking' ); ?></button></p>
				</form>
			</div>

			<div class="mzk-card">
				<h2><?php esc_html_e( 'Generate the schedule', 'mizuki-booking' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php MZK_Admin::form_fields( 'generate' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'From', 'mizuki-booking' ); ?></th>
							<td><input type="date" name="from" required value="<?php echo esc_attr( MZK_Utils::today() ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'To', 'mizuki-booking' ); ?></th>
							<td>
								<input type="date" name="to" required
									value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( MZK_Utils::today() . ' +3 months' ) ) ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Pattern', 'mizuki-booking' ); ?></th>
							<td>
								<select name="template_id">
									<option value="0"><?php esc_html_e( 'All active weekly sessions', 'mizuki-booking' ); ?></option>
									<?php foreach ( MZK_Sessions::templates( true ) as $tpl ) : ?>
										<option value="<?php echo esc_attr( $tpl->id ); ?>">
											<?php echo esc_html( $weekdays[ (int) $tpl->weekday ] . ' ' . substr( $tpl->start_time, 0, 5 ) . ' — ' . $tpl->class_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate sessions', 'mizuki-booking' ); ?></button></p>
					<p class="description">
						<?php esc_html_e( 'Existing sessions are never duplicated, and blocked dates are skipped automatically. This also runs once a day so the calendar always shows the months ahead set in Settings.', 'mizuki-booking' ); ?>
					</p>
				</form>
			</div>
		</div>

	<?php else : ?>

		<form method="get" class="mzk-toolbar">
			<input type="hidden" name="page" value="mzk-sessions" />
			<label><?php esc_html_e( 'From', 'mizuki-booking' ); ?>
				<input type="date" name="from" value="<?php echo esc_attr( $filter_from ? $filter_from : MZK_Utils::today() ); ?>" />
			</label>
			<label><?php esc_html_e( 'To', 'mizuki-booking' ); ?>
				<input type="date" name="to" value="<?php echo esc_attr( $filter_to ); ?>" />
			</label>
			<select name="class_type">
				<option value="0"><?php esc_html_e( 'All classes', 'mizuki-booking' ); ?></option>
				<?php foreach ( $types as $type ) : ?>
					<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $filter_type, (int) $type->id ); ?>>
						<?php echo esc_html( $type->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mizuki-booking' ); ?></button>
		</form>

		<?php
		$rows = MZK_Sessions::query(
			array(
				'from'          => $filter_from ? $filter_from : MZK_Utils::today(),
				'to'            => $filter_to,
				'class_type_id' => $filter_type,
				'limit'         => 400,
			)
		);
		?>
		<table class="widefat striped mzk-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Time', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Booked / limit', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'mizuki-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No sessions in this period.', 'mizuki-booking' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $session ) : ?>
				<tr>
					<td>
						<?php echo esc_html( $session->date_label ); ?>
						<?php if ( $session->is_blacked_out ) : ?>
							<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'Blocked', 'mizuki-booking' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $session->time_label ); ?></td>
					<td>
						<span class="mzk-swatch" style="background: <?php echo esc_attr( $session->class_colour ); ?>"></span>
						<?php echo esc_html( $session->class_name ); ?>
					</td>
					<td>
						<strong><?php echo esc_html( $session->seats_taken ); ?></strong> /
						<?php echo esc_html( $session->effective_capacity ); ?>
						<?php if ( $session->capacity_adjustment ) : ?>
							<span class="mzk-muted">(<?php echo esc_html( ( $session->capacity_adjustment > 0 ? '+' : '' ) . $session->capacity_adjustment ); ?>)</span>
						<?php endif; ?>
						<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => -1, 'return_page' => 'mzk-sessions' ) ) ); ?>">&minus;</a>
						<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => 1, 'return_page' => 'mzk-sessions' ) ) ); ?>">+</a>
					</td>
					<td><?php echo esc_html( $statuses[ $session->status ] ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=add&id=' . $session->id ) ); ?>"><?php esc_html_e( 'Edit', 'mizuki-booking' ); ?></a>
						|
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-bookings&session_id=' . $session->id ) ); ?>"><?php esc_html_e( 'Participants', 'mizuki-booking' ); ?></a>
						|
						<a class="mzk-danger" data-mzk-confirm href="<?php echo esc_url( MZK_Admin::action_url( 'delete_session', array( 'id' => $session->id ) ) ); ?>">
							<?php esc_html_e( 'Delete', 'mizuki-booking' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>
</div>
