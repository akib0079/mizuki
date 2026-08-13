<?php
/**
 * Setup screen: create every page in one click, add demo content, see what's left to do.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

$pages       = MZK_Setup::status();
$outstanding = MZK_Setup::outstanding();
$has_demo    = MZK_Setup::has_demo();
$all_made    = true;

foreach ( $pages as $page ) {
	if ( ! $page['id'] ) {
		$all_made = false;
		break;
	}
}
?>
<div class="wrap mzk-wrap">
	<h1><?php esc_html_e( 'Setup', 'mizuki-booking' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Press one button and every page the booking system needs is created, with its shortcode already in place. Nothing is duplicated — pages you already have are reused.', 'mizuki-booking' ); ?>
	</p>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Step 1 — Create the pages', 'mizuki-booking' ); ?></h2>

		<table class="widefat striped mzk-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mizuki-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $pages as $page ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $page['title'] ); ?></strong></td>
					<td><?php echo esc_html( $page['desc'] ); ?></td>
					<td><code><?php echo esc_html( $page['shortcode'] ); ?></code></td>
					<td>
						<?php if ( $page['id'] ) : ?>
							<span class="mzk-tag">&#10003; <?php esc_html_e( 'Created', 'mizuki-booking' ); ?></span><br />
							<a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'mizuki-booking' ); ?></a>
							<?php if ( $page['edit'] ) : ?>
								| <a href="<?php echo esc_url( $page['edit'] ); ?>"><?php esc_html_e( 'Edit', 'mizuki-booking' ); ?></a>
							<?php endif; ?>
						<?php else : ?>
							<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'Not created', 'mizuki-booking' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php MZK_Admin::form_fields( 'create_pages' ); ?>
			<p class="submit">
				<button type="submit" class="button button-primary button-hero">
					<?php echo $all_made ? esc_html__( 'Re-check the pages', 'mizuki-booking' ) : esc_html__( 'Create all pages now', 'mizuki-booking' ); ?>
				</button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Safe to press more than once. Each page is linked in Settings automatically, so confirmation e-mails point to the right place.', 'mizuki-booking' ); ?>
			</p>
		</form>
	</div>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Step 2 — Try it with demo content', 'mizuki-booking' ); ?></h2>

		<?php if ( $has_demo ) : ?>
			<p><?php esc_html_e( 'Demo content is installed. You have a weekly pattern, eight weeks of sessions, a studio closure, two course students, several bookings and one registration waiting for approval.', 'mizuki-booking' ); ?></p>
			<p class="description">
				<strong><?php esc_html_e( 'Remember to remove it before you go live', 'mizuki-booking' ); ?></strong> —
				<?php esc_html_e( 'this deletes only the demo items, never anything you added yourself.', 'mizuki-booking' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'remove_demo' ); ?>
				<p class="submit">
					<button type="submit" class="button" data-mzk-confirm>
						<?php esc_html_e( 'Remove demo content', 'mizuki-booking' ); ?>
					</button>
				</p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Adds a realistic week of classes so you can click around and see how everything behaves before entering your own timetable.', 'mizuki-booking' ); ?></p>
			<ul class="mzk-list-plain">
				<li><?php esc_html_e( 'A weekly pattern: Fresh Flower and Ikebana on Saturday, Ikebana on Wednesday evening, Preserved Flower on Sunday, IFDA on Tuesday', 'mizuki-booking' ); ?></li>
				<li><?php esc_html_e( 'Eight weeks of sessions generated from it', 'mizuki-booking' ); ?></li>
				<li><?php esc_html_e( 'A three-day studio closure', 'mizuki-booking' ); ?></li>
				<li><?php esc_html_e( 'Two course students, one nearly finished', 'mizuki-booking' ); ?></li>
				<li><?php esc_html_e( 'Seven bookings, including one waiting for approval', 'mizuki-booking' ); ?></li>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'install_demo' ); ?>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add demo content', 'mizuki-booking' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Step 3 — Payments', 'mizuki-booking' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Each class decides how students get a place. Set this under Classes & Rules; this is where you can see it all at once.', 'mizuki-booking' ); ?>
		</p>

		<?php $payments = MZK_Setup::payment_status(); ?>
		<table class="widefat striped mzk-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Class', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'How students book', 'mizuki-booking' ); ?></th>
					<th><?php esc_html_e( 'Shop product', 'mizuki-booking' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $payments as $row ) : ?>
				<tr>
					<td>
						<span class="mzk-swatch" style="background: <?php echo esc_attr( $row['colour'] ); ?>"></span>
						<strong><?php echo esc_html( $row['name'] ); ?></strong>
					</td>
					<td>
						<?php
						if ( 'paid' === $row['mode'] ) {
							esc_html_e( 'Pay first, through the shop', 'mizuki-booking' );
						} elseif ( 'package' === $row['mode'] ) {
							esc_html_e( 'Course — enrol once, then book sessions', 'mizuki-booking' );
						} else {
							esc_html_e( 'Book on the calendar, no payment online', 'mizuki-booking' );
						}
						?>
					</td>
					<td>
						<?php if ( ! $row['needs'] ) : ?>
							<span class="mzk-muted"><?php esc_html_e( 'not needed', 'mizuki-booking' ); ?></span>
						<?php elseif ( $row['product'] ) : ?>
							<a href="<?php echo esc_url( $row['product']['edit'] ); ?>"><?php echo esc_html( $row['product']['name'] ); ?></a>
							<?php if ( 'publish' !== $row['product']['status'] ) : ?>
								<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'draft — not on sale yet', 'mizuki-booking' ); ?></span>
							<?php else : ?>
								<span class="mzk-muted"><?php echo wp_kses_post( $row['product']['price'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'no product — students cannot pay', 'mizuki-booking' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-classes&edit=' . $row['id'] ) ); ?>">
							<?php esc_html_e( 'Change', 'mizuki-booking' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'create_products' ); ?>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the missing products', 'mizuki-booking' ); ?></button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Creates a draft product for every paid class and course that has none, already connected to the calendar. You set the price and publish — nothing goes on sale by itself.', 'mizuki-booking' ); ?>
				</p>
			</form>
		<?php else : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'WooCommerce is not active, so students cannot pay online. Classes can still be booked on the calendar.', 'mizuki-booking' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Step 4 — What is left to do', 'mizuki-booking' ); ?></h2>

		<?php if ( ! $outstanding ) : ?>
			<p class="mzk-ready">
				<strong><?php esc_html_e( '✓ Everything is ready — the booking system is live and taking bookings.', 'mizuki-booking' ); ?></strong>
			</p>
		<?php else : ?>
			<ul class="mzk-todo">
				<?php foreach ( $outstanding as $item ) : ?>
					<li><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=templates' ) ); ?>">
				<?php esc_html_e( 'Set the weekly pattern', 'mizuki-booking' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-classes' ) ); ?>">
				<?php esc_html_e( 'Check the class rules', 'mizuki-booking' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-settings' ) ); ?>">
				<?php esc_html_e( 'E-mails and reminders', 'mizuki-booking' ); ?>
			</a>
			<?php if ( ! empty( $pages['studio_page_id']['url'] ) ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $pages['studio_page_id']['url'] ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Open the front-end manager', 'mizuki-booking' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Diagnostics', 'mizuki-booking' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'If sessions are not appearing on the calendar, the answer is almost always here.', 'mizuki-booking' ); ?>
		</p>

		<?php $diag = MZK_Setup::diagnostics(); ?>

		<?php if ( $diag['missing'] ) : ?>
			<div class="notice notice-error inline">
				<p>
					<strong><?php esc_html_e( 'Some database tables are missing.', 'mizuki-booking' ); ?></strong><br />
					<?php echo esc_html( implode( ', ', $diag['missing'] ) ); ?><br />
					<?php esc_html_e( 'Nothing can be saved until these exist. Press “Check and repair database” below.', 'mizuki-booking' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<table class="widefat striped">
			<tbody>
				<?php foreach ( $diag['tables'] as $label => $info ) : ?>
					<tr>
						<td style="width: 220px;"><code><?php echo esc_html( $label ); ?></code></td>
						<td>
							<?php if ( $info['exists'] ) : ?>
								<span class="mzk-tag">&#10003; <?php esc_html_e( 'present', 'mizuki-booking' ); ?></span>
								<?php
								printf(
									/* translators: %d: number of rows. */
									esc_html( _n( '%d row', '%d rows', (int) $info['rows'], 'mizuki-booking' ) ),
									(int) $info['rows']
								);
								?>
							<?php else : ?>
								<span class="mzk-tag mzk-tag--warn"><?php esc_html_e( 'MISSING', 'mizuki-booking' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><?php esc_html_e( 'Active weekly sessions', 'mizuki-booking' ); ?></td>
					<td>
						<strong><?php echo esc_html( $diag['active_templates'] ); ?></strong>
						<?php if ( ! $diag['active_templates'] ) : ?>
							— <?php esc_html_e( 'add one under Sessions → Weekly pattern, or nothing can be generated', 'mizuki-booking' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Sessions from today onwards', 'mizuki-booking' ); ?></td>
					<td>
						<strong><?php echo esc_html( $diag['future_sessions'] ); ?></strong>
						<?php if ( $diag['first_session'] ) : ?>
							— <?php echo esc_html( $diag['first_session'] . ' → ' . $diag['last_session'] ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Last generate run', 'mizuki-booking' ); ?></td>
					<td>
						<?php
						$last = (array) $diag['last_error'];
						if ( ! $last ) {
							esc_html_e( 'never run', 'mizuki-booking' );
						} elseif ( ! empty( $last['error'] ) ) {
							echo '<span class="mzk-danger">' . esc_html( $last['error'] ) . '</span> (' . esc_html( $last['when'] ) . ')';
						} else {
							printf(
								/* translators: 1: created, 2: range, 3: time. */
								esc_html__( '%1$d created for %2$s at %3$s', 'mizuki-booking' ),
								(int) ( $last['created'] ?? 0 ),
								esc_html( $last['range'] ?? '—' ),
								esc_html( $last['when'] ?? '—' )
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Plugin / schema version', 'mizuki-booking' ); ?></td>
					<td><?php echo esc_html( $diag['plugin_version'] . ' / ' . $diag['db_version'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Site timezone', 'mizuki-booking' ); ?></td>
					<td><?php echo esc_html( $diag['timezone'] ); ?></td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php MZK_Admin::form_fields( 'repair_tables' ); ?>
			<p class="submit">
				<button type="submit" class="button"><?php esc_html_e( 'Check and repair database', 'mizuki-booking' ); ?></button>
				<span class="description"><?php esc_html_e( 'Only adds what is missing — it never deletes anything.', 'mizuki-booking' ); ?></span>
			</p>
		</form>
	</div>

	<div class="mzk-card">
		<h2><?php esc_html_e( 'Every shortcode', 'mizuki-booking' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Drop any of these into a page or an Elementor shortcode widget.', 'mizuki-booking' ); ?></p>
		<table class="widefat striped">
			<tbody>
				<tr><td><code>[mizuki_calendar]</code></td><td><?php esc_html_e( 'Booking calendar, all classes', 'mizuki-booking' ); ?></td></tr>
				<tr>
					<td><code>[mizuki_calendar class="ikebana"]</code></td>
					<td>
						<?php esc_html_e( 'One class only. Slugs:', 'mizuki-booking' ); ?>
						<?php
						$slugs = array();
						foreach ( MZK_Class_Types::all( true ) as $type ) {
							$slugs[] = $type->slug;
						}
						echo ' <code>' . esc_html( implode( '</code>, <code>', $slugs ) ) . '</code>';
						?>
					</td>
				</tr>
				<tr><td><code>[mizuki_calendar months="3" view="list"]</code></td><td><?php esc_html_e( 'More months, or a plain list instead of a grid', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_course_portal]</code></td><td><?php esc_html_e( 'Course students: log in, see the balance, book with no payment', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_course_portal course="ifda"]</code></td><td><?php esc_html_e( 'The same, restricted to one course', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_login]</code></td><td><?php esc_html_e( 'Student login and registration', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_dashboard]</code></td><td><?php esc_html_e( 'Student area: classes, course balance, details', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_my_bookings]</code></td><td><?php esc_html_e( 'Manage a single booking from an e-mail link', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_my_courses]</code></td><td><?php esc_html_e( 'Course package balance on its own', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_manage]</code></td><td><?php esc_html_e( 'Front-end manager for the studio (only you can see it)', 'mizuki-booking' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
