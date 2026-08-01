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
		<h2><?php esc_html_e( 'Step 3 — What is left to do', 'mizuki-booking' ); ?></h2>

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
				<tr><td><code>[mizuki_login]</code></td><td><?php esc_html_e( 'Student login and registration', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_dashboard]</code></td><td><?php esc_html_e( 'Student area: classes, course balance, details', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_my_bookings]</code></td><td><?php esc_html_e( 'Manage a single booking from an e-mail link', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_my_courses]</code></td><td><?php esc_html_e( 'Course package balance on its own', 'mizuki-booking' ); ?></td></tr>
				<tr><td><code>[mizuki_manage]</code></td><td><?php esc_html_e( 'Front-end manager for the studio (only you can see it)', 'mizuki-booking' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
