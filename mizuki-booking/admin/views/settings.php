<?php
/**
 * Admin settings: schedule horizon, reminders, e-mail templates, shortcodes.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

$settings = MZK_Install::get_settings();
$next_run = wp_next_scheduled( MZK_Cron::HOOK_REMINDERS );
$tags     = '{student_name} {student_email} {student_phone} {class_type} {session_title} {session_date} {session_time} {session_duration} {manage_url} {studio_name} {sessions_left} {old_session_date} {old_session_time}';
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Booking Settings', 'mizuki-booking' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php MZK_Admin::form_fields( 'save_settings' ); ?>

		<h2><?php esc_html_e( 'Schedule', 'mizuki-booking' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mzk-months"><?php esc_html_e( 'Months shown ahead', 'mizuki-booking' ); ?></label></th>
				<td>
					<input type="number" id="mzk-months" name="months_ahead" min="2" max="12" value="<?php echo esc_attr( $settings['months_ahead'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Minimum 2. Weekly sessions are generated this far ahead automatically each day, so families can always plan.', 'mizuki-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contact number', 'mizuki-booking' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="require_phone" value="1" <?php checked( (int) $settings['require_phone'], 1 ); ?> />
						<?php esc_html_e( 'Require a contact number when booking', 'mizuki-booking' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Payments (WooCommerce)', 'mizuki-booking' ); ?></h2>
		<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'WooCommerce is not active, so paid bookings and course packages bought online are unavailable. The calendar still works for students you book yourself.', 'mizuki-booking' ); ?></p>
			</div>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Paid bookings', 'mizuki-booking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="woo_enabled" value="1" <?php checked( (int) $settings['woo_enabled'], 1 ); ?> />
							<?php esc_html_e( 'Let students pick a session on a product page and pay for it', 'mizuki-booking' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Set each product up under Products → edit → Mizuki Booking: either “Session booking” (student picks a date) or “Course package” (grants a number of sessions).', 'mizuki-booking' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-hold"><?php esc_html_e( 'Hold a place for', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="number" id="mzk-hold" name="woo_hold_minutes" min="5" max="1440" value="<?php echo esc_attr( $settings['woo_hold_minutes'] ); ?>" />
						<?php esc_html_e( 'minutes while the order is unpaid', 'mizuki-booking' ); ?>
						<p class="description"><?php esc_html_e( 'Stops two students checking out into the last place. Unpaid holds are released automatically.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-confirm-on"><?php esc_html_e( 'Confirm the booking when the order is', 'mizuki-booking' ); ?></label></th>
					<td>
						<select name="woo_confirm_on" id="mzk-confirm-on">
							<option value="processing" <?php selected( $settings['woo_confirm_on'], 'processing' ); ?>>
								<?php esc_html_e( 'Paid (processing) — recommended', 'mizuki-booking' ); ?>
							</option>
							<option value="completed" <?php selected( $settings['woo_confirm_on'], 'completed' ); ?>>
								<?php esc_html_e( 'Completed', 'mizuki-booking' ); ?>
							</option>
						</select>
						<p class="description"><?php esc_html_e( 'The confirmation e-mail goes out at this point, and course packages are granted.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mzk-validity"><?php esc_html_e( 'Default package validity', 'mizuki-booking' ); ?></label></th>
					<td>
						<input type="number" id="mzk-validity" name="woo_package_validity" min="0" max="60" value="<?php echo esc_attr( $settings['woo_package_validity'] ); ?>" />
						<?php esc_html_e( 'months', 'mizuki-booking' ); ?>
						<p class="description"><?php esc_html_e( '0 means no expiry. Individual products can override this, and you can always extend a student’s package later.', 'mizuki-booking' ); ?></p>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Reminders', 'mizuki-booking' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mzk-rdays"><?php esc_html_e( 'Send reminder', 'mizuki-booking' ); ?></label></th>
				<td>
					<input type="number" id="mzk-rdays" name="reminder_days_before" min="0" max="30" value="<?php echo esc_attr( $settings['reminder_days_before'] ); ?>" />
					<?php esc_html_e( 'day(s) before the class, from', 'mizuki-booking' ); ?>
					<input type="number" name="reminder_hour" min="0" max="23" value="<?php echo esc_attr( $settings['reminder_hour'] ); ?>" />
					<?php esc_html_e( 'o’clock', 'mizuki-booking' ); ?>
					<p class="description">
						<?php
						if ( $next_run ) {
							printf(
								/* translators: %s: next scheduled time. */
								esc_html__( 'Next automatic check: %s.', 'mizuki-booking' ),
								esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) )
							);
						} else {
							esc_html_e( 'The reminder schedule is not registered yet — it will start on the next page load.', 'mizuki-booking' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Studio notifications', 'mizuki-booking' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="notify_admin" value="1" <?php checked( (int) $settings['notify_admin'], 1 ); ?> />
						<?php esc_html_e( 'E-mail the studio when a new booking comes in', 'mizuki-booking' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mzk-studio-name"><?php esc_html_e( 'Studio name', 'mizuki-booking' ); ?></label></th>
				<td><input type="text" id="mzk-studio-name" name="studio_name" class="regular-text" value="<?php echo esc_attr( $settings['studio_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="mzk-admin-email"><?php esc_html_e( 'Studio e-mail', 'mizuki-booking' ); ?></label></th>
				<td><input type="email" id="mzk-admin-email" name="admin_email" class="regular-text" value="<?php echo esc_attr( $settings['admin_email'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Pages', 'mizuki-booking' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mzk-booking-page"><?php esc_html_e( 'Booking page', 'mizuki-booking' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'             => 'booking_page_id',
							'id'               => 'mzk-booking-page',
							'selected'         => (int) $settings['booking_page_id'],
							'show_option_none' => __( '— none —', 'mizuki-booking' ),
							'option_none_value' => 0,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'The page holding the [mizuki_calendar] shortcode.', 'mizuki-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mzk-manage-page"><?php esc_html_e( 'Manage-booking page', 'mizuki-booking' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'             => 'manage_page_id',
							'id'               => 'mzk-manage-page',
							'selected'         => (int) $settings['manage_page_id'],
							'show_option_none' => __( '— none —', 'mizuki-booking' ),
							'option_none_value' => 0,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'The page holding [mizuki_my_bookings]. Confirmation e-mails link here so students can reschedule.', 'mizuki-booking' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'E-mail delivery', 'mizuki-booking' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'If students are not receiving anything, this is almost always why. Shared hosting often accepts a message and quietly drops it — sending through Resend removes the guesswork, because every send returns a real answer.', 'mizuki-booking' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Send e-mail using', 'mizuki-booking' ); ?></th>
				<td>
					<label style="display:block;margin-bottom:6px;">
						<input type="radio" name="mail_provider" value="wp" <?php checked( $settings['mail_provider'], 'wp' ); ?> />
						<?php esc_html_e( 'The WordPress mailer (your host)', 'mizuki-booking' ); ?>
					</label>
					<label style="display:block;">
						<input type="radio" name="mail_provider" value="resend" <?php checked( $settings['mail_provider'], 'resend' ); ?> />
						<strong><?php esc_html_e( 'Resend — recommended', 'mizuki-booking' ); ?></strong>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mzk-resend-key"><?php esc_html_e( 'Resend API key', 'mizuki-booking' ); ?></label></th>
				<td>
					<input type="password" id="mzk-resend-key" name="resend_api_key" class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( MZK_Resend::masked_key() ? MZK_Resend::masked_key() : 're_...' ); ?>" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to Resend. */
							esc_html__( 'Create one at %s → API Keys. Leave blank to keep the key you already saved.', 'mizuki-booking' ),
							'<a href="https://resend.com/api-keys" target="_blank" rel="noopener">resend.com</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Send from', 'mizuki-booking' ); ?></th>
				<td>
					<input type="text" name="mail_from_name" class="regular-text"
						value="<?php echo esc_attr( $settings['mail_from_name'] ); ?>"
						placeholder="<?php esc_attr_e( 'Studio name', 'mizuki-booking' ); ?>" />
					<input type="email" name="mail_from_email" class="regular-text"
						value="<?php echo esc_attr( $settings['mail_from_email'] ); ?>"
						placeholder="hello@mizuki.com.sg" />
					<p class="description">
						<strong><?php esc_html_e( 'The domain of this address must be verified on your Resend account', 'mizuki-booking' ); ?></strong> —
						<?php esc_html_e( 'otherwise Resend will refuse to send. While testing you can use onboarding@resend.dev, which only delivers to the address that owns the Resend account.', 'mizuki-booking' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Replies go to', 'mizuki-booking' ); ?></th>
				<td>
					<input type="email" name="mail_reply_to" class="regular-text"
						value="<?php echo esc_attr( $settings['mail_reply_to'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Where a student’s reply lands. Usually the studio inbox.', 'mizuki-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Safety net', 'mizuki-booking' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mail_fallback" value="1" <?php checked( (int) $settings['mail_fallback'], 1 ); ?> />
						<?php esc_html_e( 'If Resend fails, try the WordPress mailer as a backup', 'mizuki-booking' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="mail_log" value="1" <?php checked( (int) $settings['mail_log'], 1 ); ?> />
						<?php esc_html_e( 'Keep a log of the last 100 e-mails and what happened to them', 'mizuki-booking' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'E-mails', 'mizuki-booking' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Available tags:', 'mizuki-booking' ); ?>
			<code><?php echo esc_html( $tags ); ?></code>
		</p>

		<?php
		$templates = array(
			'confirm'    => __( 'Booking confirmation (sent immediately)', 'mizuki-booking' ),
			'reminder'   => __( 'Class reminder', 'mizuki-booking' ),
			'reschedule' => __( 'Reschedule confirmation', 'mizuki-booking' ),
			'cancel'     => __( 'Cancellation notice', 'mizuki-booking' ),
		);
		foreach ( $templates as $key => $label ) :
			?>
			<h3><?php echo esc_html( $label ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Subject', 'mizuki-booking' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( $key ); ?>_subject" class="large-text" value="<?php echo esc_attr( $settings[ $key . '_subject' ] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Message', 'mizuki-booking' ); ?></th>
					<td><textarea name="<?php echo esc_attr( $key ); ?>_body" rows="8" class="large-text code"><?php echo esc_textarea( $settings[ $key . '_body' ] ); ?></textarea></td>
				</tr>
			</table>
		<?php endforeach; ?>

		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'mizuki-booking' ); ?></button></p>
	</form>

	<div class="mzk-columns">
		<div class="mzk-card">
			<h2><?php esc_html_e( 'Send a test e-mail', 'mizuki-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'send_test_email' ); ?>
				<p>
					<select name="template">
						<?php foreach ( $templates as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="email" name="to" required value="<?php echo esc_attr( $settings['admin_email'] ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Send test', 'mizuki-booking' ); ?></button>
				</p>
			</form>

			<?php if ( 'resend' === $settings['mail_provider'] ) : ?>
				<h2><?php esc_html_e( 'Check the Resend connection', 'mizuki-booking' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php MZK_Admin::form_fields( 'verify_resend' ); ?>
					<p>
						<button type="submit" class="button"><?php esc_html_e( 'Test the connection', 'mizuki-booking' ); ?></button>
						<span class="description"><?php esc_html_e( 'Checks the key and whether your sending domain is verified — without e-mailing anyone.', 'mizuki-booking' ); ?></span>
					</p>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Run reminders now', 'mizuki-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'run_reminders' ); ?>
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Send due reminders', 'mizuki-booking' ); ?></button>
					<span class="description"><?php esc_html_e( 'Sends reminders for classes at the configured distance. Each booking is only ever reminded once.', 'mizuki-booking' ); ?></span>
				</p>
			</form>
		</div>

		<div class="mzk-card">
			<h2><?php esc_html_e( 'Delivery log', 'mizuki-booking' ); ?></h2>
			<?php $log = MZK_Mailer::recent_log( 25 ); ?>

			<?php if ( ! $log ) : ?>
				<p><?php esc_html_e( 'Nothing sent yet. Send a test above, or make a booking, and it will appear here.', 'mizuki-booking' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'When', 'mizuki-booking' ); ?></th>
							<th><?php esc_html_e( 'To', 'mizuki-booking' ); ?></th>
							<th><?php esc_html_e( 'About', 'mizuki-booking' ); ?></th>
							<th><?php esc_html_e( 'Result', 'mizuki-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'j M, H:i', $entry['time'] ) ); ?></td>
							<td><?php echo esc_html( $entry['to'] ); ?></td>
							<td>
								<?php echo esc_html( $entry['subject'] ); ?><br />
								<span class="mzk-muted"><?php echo esc_html( $entry['context'] . ' · ' . $entry['via'] ); ?></span>
							</td>
							<td>
								<?php if ( $entry['sent'] ) : ?>
									<span class="mzk-tag">&#10003; <?php esc_html_e( 'sent', 'mizuki-booking' ); ?></span>
								<?php else : ?>
									<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'failed', 'mizuki-booking' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $entry['error'] ) ) : ?>
									<br /><span class="mzk-danger"><?php echo esc_html( $entry['error'] ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php MZK_Admin::form_fields( 'clear_mail_log' ); ?>
					<p class="submit"><button type="submit" class="button"><?php esc_html_e( 'Clear the log', 'mizuki-booking' ); ?></button></p>
				</form>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( '“Sent” means the mail service accepted it. If a student still cannot find it, ask them to check spam — and make sure your sending domain has SPF and DKIM set up.', 'mizuki-booking' ); ?>
			</p>
		</div>

		<div class="mzk-card">
			<h2><?php esc_html_e( 'Shortcodes', 'mizuki-booking' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<td><code>[mizuki_calendar]</code></td>
						<td><?php esc_html_e( 'Full calendar with every class.', 'mizuki-booking' ); ?></td>
					</tr>
					<tr>
						<td><code>[mizuki_calendar class="ikebana"]</code></td>
						<td><?php esc_html_e( 'Calendar for one class only.', 'mizuki-booking' ); ?></td>
					</tr>
					<tr>
						<td><code>[mizuki_calendar months="3"]</code></td>
						<td><?php esc_html_e( 'Show more months than the default (minimum 2).', 'mizuki-booking' ); ?></td>
					</tr>
					<tr>
						<td><code>[mizuki_calendar view="list"]</code></td>
						<td><?php esc_html_e( 'Simple chronological list instead of a month grid.', 'mizuki-booking' ); ?></td>
					</tr>
					<tr>
						<td><code>[mizuki_my_bookings]</code></td>
						<td><?php esc_html_e( 'Student self-service: view, reschedule, cancel.', 'mizuki-booking' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
