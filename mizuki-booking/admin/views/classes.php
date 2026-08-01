<?php
/**
 * Admin classes: defaults and the reschedule / cancellation rules per class.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$current = $edit_id ? MZK_Class_Types::get( $edit_id ) : null;
$types   = MZK_Class_Types::all();
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Classes & Rules', 'mizuki-booking' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Each class carries its own defaults and its own rescheduling rule. Fresh Flower and Ikebana are set to close changes 3 days (72 hours) before the class; Preserved Flower and IFDA are more flexible.', 'mizuki-booking' ); ?>
	</p>

	<table class="widefat striped mzk-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Defaults', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Course package', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Rescheduling', 'mizuki-booking' ); ?></th>
				<th><?php esc_html_e( 'Active', 'mizuki-booking' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $types as $type ) : ?>
			<tr>
				<td>
					<span class="mzk-swatch" style="background: <?php echo esc_attr( $type->colour ); ?>"></span>
					<strong><?php echo esc_html( $type->name ); ?></strong><br />
					<code><?php echo esc_html( $type->slug ); ?></code>
				</td>
				<td>
					<?php
					printf(
						/* translators: 1: capacity, 2: duration. */
						esc_html__( '%1$d places · %2$s', 'mizuki-booking' ),
						(int) $type->default_capacity,
						esc_html( MZK_Utils::format_duration( $type->default_duration ) )
					);
					?>
				</td>
				<td>
					<?php if ( $type->course_based ) : ?>
						<?php esc_html_e( 'Yes', 'mizuki-booking' ); ?>
						<?php if ( $type->requires_enrollment ) : ?>
							<br /><span class="mzk-muted"><?php esc_html_e( 'package required to book', 'mizuki-booking' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="mzk-muted"><?php esc_html_e( 'No', 'mizuki-booking' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $type->reschedule_enabled ) : ?>
						<?php
						printf(
							/* translators: %s: cutoff description. */
							esc_html__( 'Allowed up to %s before the class', 'mizuki-booking' ),
							esc_html( MZK_Class_Types::describe_cutoff( (float) $type->reschedule_cutoff_hours ) )
						);
						?>
					<?php else : ?>
						<span class="mzk-muted"><?php esc_html_e( 'Not available online', 'mizuki-booking' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo $type->active ? '&#10003;' : '&mdash;'; ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-classes&edit=' . $type->id ) ); ?>"><?php esc_html_e( 'Edit', 'mizuki-booking' ); ?></a>
					|
					<a class="mzk-danger" data-mzk-confirm href="<?php echo esc_url( MZK_Admin::action_url( 'delete_class', array( 'id' => $type->id ) ) ); ?>">
						<?php esc_html_e( 'Delete', 'mizuki-booking' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="mzk-card">
		<h2><?php echo $current ? esc_html__( 'Edit class', 'mizuki-booking' ) : esc_html__( 'Add a class', 'mizuki-booking' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php MZK_Admin::form_fields( 'save_class' ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $current ? $current->id : 0 ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'mizuki-booking' ); ?></th>
					<td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr( $current ? $current->name : '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Slug', 'mizuki-booking' ); ?></th>
					<td>
						<input type="text" name="slug" class="regular-text" value="<?php echo esc_attr( $current ? $current->slug : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'Used in the shortcode, e.g. [mizuki_calendar class="ikebana"].', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Colour', 'mizuki-booking' ); ?></th>
					<td><input type="color" name="colour" value="<?php echo esc_attr( $current ? $current->colour : '#3f827a' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default participant limit', 'mizuki-booking' ); ?></th>
					<td><input type="number" name="default_capacity" min="1" value="<?php echo esc_attr( $current ? $current->default_capacity : 6 ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default duration (minutes)', 'mizuki-booking' ); ?></th>
					<td><input type="number" name="default_duration" min="15" step="15" value="<?php echo esc_attr( $current ? $current->default_duration : 120 ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Course package', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="course_based" value="1" <?php checked( $current ? (int) $current->course_based : 0, 1 ); ?> />
							<?php esc_html_e( 'Students buy a fixed number of sessions (IFDA, Preserved Flower)', 'mizuki-booking' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="requires_enrollment" value="1" <?php checked( $current ? (int) $current->requires_enrollment : 0, 1 ); ?> />
							<?php esc_html_e( 'Only students with an active package can book online', 'mizuki-booking' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Registrations', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="requires_approval" value="1" <?php checked( $current ? (int) $current->requires_approval : 0, 1 ); ?> />
							<?php esc_html_e( 'I approve each registration before the place is confirmed', 'mizuki-booking' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'The place is held while it waits, so nobody else can take it. The student is told as soon as you approve or decline.', 'mizuki-booking' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rescheduling', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="reschedule_enabled" value="1" <?php checked( $current ? (int) $current->reschedule_enabled : 1, 1 ); ?> />
							<?php esc_html_e( 'Students can move their own booking on the website', 'mizuki-booking' ); ?>
						</label>
						<p>
							<label>
								<?php esc_html_e( 'Closes this many hours before the class:', 'mizuki-booking' ); ?>
								<input type="number" name="reschedule_cutoff_hours" min="0" step="1"
									value="<?php echo esc_attr( $current ? $current->reschedule_cutoff_hours : 72 ); ?>" />
							</label>
							<span class="description"><?php esc_html_e( '72 = 3 days. A Saturday class then locks on the Wednesday before.', 'mizuki-booking' ); ?></span>
						</p>
						<p>
							<label>
								<?php esc_html_e( 'Maximum reschedules per booking:', 'mizuki-booking' ); ?>
								<input type="number" name="max_reschedules" min="0" step="1"
									value="<?php echo esc_attr( $current ? $current->max_reschedules : 0 ); ?>" />
							</label>
							<span class="description"><?php esc_html_e( '0 = unlimited.', 'mizuki-booking' ); ?></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cancellation', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cancel_enabled" value="1" <?php checked( $current ? (int) $current->cancel_enabled : 1, 1 ); ?> />
							<?php esc_html_e( 'Students can cancel their own booking', 'mizuki-booking' ); ?>
						</label>
						<p>
							<label>
								<?php esc_html_e( 'Closes this many hours before the class:', 'mizuki-booking' ); ?>
								<input type="number" name="cancel_cutoff_hours" min="0" step="1"
									value="<?php echo esc_attr( $current ? $current->cancel_cutoff_hours : 72 ); ?>" />
							</label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Short summary', 'mizuki-booking' ); ?></th>
					<td>
						<input type="text" name="summary" class="large-text" maxlength="255"
							value="<?php echo esc_attr( $current ? $current->summary : '' ); ?>"
							placeholder="<?php esc_attr_e( 'One line shown on the classes page, e.g. “A gentle introduction to Japanese flower arranging.”', 'mizuki-booking' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Description', 'mizuki-booking' ); ?></th>
					<td>
						<textarea name="description" rows="5" class="large-text"><?php echo esc_textarea( $current ? $current->description : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'The fuller explanation shown on the class’s own page.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Price note', 'mizuki-booking' ); ?></th>
					<td>
						<input type="text" name="price_note" class="regular-text" maxlength="120"
							value="<?php echo esc_attr( $current ? $current->price_note : '' ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. $90 per session, or $1,800 for 25 sessions', 'mizuki-booking' ); ?>" />
						<p class="description"><?php esc_html_e( 'Free text, shown on the classes page. Leave empty to hide.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Photo', 'mizuki-booking' ); ?></th>
					<td>
						<?php $image_id = $current ? (int) $current->image_id : 0; ?>
						<div class="mzk-media" data-mzk-media>
							<div class="mzk-media__preview">
								<?php if ( $image_id ) : ?>
									<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
								<?php endif; ?>
							</div>
							<input type="hidden" name="image_id" value="<?php echo esc_attr( $image_id ); ?>" />
							<button type="button" class="button" data-mzk-media-pick><?php esc_html_e( 'Choose photo', 'mizuki-booking' ); ?></button>
							<button type="button" class="button" data-mzk-media-clear><?php esc_html_e( 'Remove', 'mizuki-booking' ); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enrol button goes to', 'mizuki-booking' ); ?></th>
					<td>
						<input type="url" name="booking_url" class="large-text"
							value="<?php echo esc_attr( $current ? $current->booking_url : '' ); ?>"
							placeholder="<?php esc_attr_e( 'Leave empty to use the booking calendar', 'mizuki-booking' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'For paid classes, paste the WooCommerce product link here. Otherwise the button opens the calendar filtered to this class.', 'mizuki-booking' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Order', 'mizuki-booking' ); ?></th>
					<td><input type="number" name="sort_order" step="10" value="<?php echo esc_attr( $current ? $current->sort_order : 50 ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="active" value="1" <?php checked( $current ? (int) $current->active : 1, 1 ); ?> />
							<?php esc_html_e( 'Show this class on the website', 'mizuki-booking' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save class', 'mizuki-booking' ); ?></button>
				<?php if ( $current ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-classes' ) ); ?>"><?php esc_html_e( 'Add a new class', 'mizuki-booking' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
