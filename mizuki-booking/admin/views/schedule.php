<?php
/**
 * Admin schedule: month grid with per-session capacity controls.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$month = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : '';
$class_filter = isset( $_GET['class_type'] ) ? (int) $_GET['class_type'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
	$month = MZK_Utils::now()->format( 'Y-m' );
}

$first_day   = $month . '-01';
$month_start = MZK_Utils::make_datetime( $first_day );
$days_in     = (int) $month_start->format( 't' );
$last_day    = $month . '-' . str_pad( (string) $days_in, 2, '0', STR_PAD_LEFT );

$prev = gmdate( 'Y-m', strtotime( $first_day . ' -1 month' ) );
$next = gmdate( 'Y-m', strtotime( $first_day . ' +1 month' ) );

$sessions  = MZK_Sessions::grouped_by_date(
	array(
		'from'          => $first_day,
		'to'            => $last_day,
		'class_type_id' => $class_filter,
	)
);
$blackouts = MZK_Blackouts::map( $first_day, $last_day, $class_filter ? $class_filter : null );

$start_of_week = (int) get_option( 'start_of_week', 0 );
$weekdays      = MZK_Utils::weekdays();
$today         = MZK_Utils::today();

// Quick stats for the header.
$upcoming = MZK_Bookings::query(
	array(
		'statuses' => array( 'confirmed' ),
		'upcoming' => true,
		'limit'    => 500,
	)
);
$horizon_end = MZK_Utils::now()->modify( '+2 months' )->format( 'Y-m-d' );
$open_ahead  = MZK_Sessions::query(
	array(
		'from'          => $today,
		'to'            => $horizon_end,
		'status'        => 'open',
		'only_bookable' => true,
	)
);
?>
<div class="wrap mzk-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Schedule', 'mizuki-booking' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add session', 'mizuki-booking' ); ?>
	</a>
	<hr class="wp-header-end" />

	<div class="mzk-stats">
		<div class="mzk-stat">
			<span class="mzk-stat__num"><?php echo esc_html( count( $upcoming ) ); ?></span>
			<span class="mzk-stat__label"><?php esc_html_e( 'Upcoming confirmed bookings', 'mizuki-booking' ); ?></span>
		</div>
		<div class="mzk-stat">
			<span class="mzk-stat__num"><?php echo esc_html( count( $open_ahead ) ); ?></span>
			<span class="mzk-stat__label"><?php esc_html_e( 'Bookable sessions in the next 2 months', 'mizuki-booking' ); ?></span>
		</div>
		<div class="mzk-stat">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php MZK_Admin::form_fields( 'extend_horizon' ); ?>
				<button type="submit" class="button">
					<?php esc_html_e( 'Top up the schedule', 'mizuki-booking' ); ?>
				</button>
			</form>
			<span class="mzk-stat__label">
				<?php
				printf(
					/* translators: %d: months. */
					esc_html__( 'Generate weekly sessions %d months ahead', 'mizuki-booking' ),
					(int) MZK_Install::get_setting( 'months_ahead', 3 )
				);
				?>
			</span>
		</div>
	</div>

	<form method="get" class="mzk-toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( MZK_Admin::SLUG ); ?>" />
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . MZK_Admin::SLUG . '&m=' . $prev . '&class_type=' . $class_filter ) ); ?>">&laquo;</a>
		<strong class="mzk-toolbar__month"><?php echo esc_html( wp_date( 'F Y', $month_start->getTimestamp() ) ); ?></strong>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . MZK_Admin::SLUG . '&m=' . $next . '&class_type=' . $class_filter ) ); ?>">&raquo;</a>
		<input type="hidden" name="m" value="<?php echo esc_attr( $month ); ?>" />
		<select name="class_type">
			<option value="0"><?php esc_html_e( 'All classes', 'mizuki-booking' ); ?></option>
			<?php foreach ( MZK_Class_Types::all() as $type ) : ?>
				<option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $class_filter, (int) $type->id ); ?>>
					<?php echo esc_html( $type->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mizuki-booking' ); ?></button>
	</form>

	<div class="mzk-cal">
		<?php for ( $i = 0; $i < 7; $i++ ) : ?>
			<div class="mzk-cal__dow"><?php echo esc_html( $weekdays[ ( $start_of_week + $i ) % 7 ] ); ?></div>
		<?php endfor; ?>

		<?php
		$lead = ( (int) $month_start->format( 'w' ) - $start_of_week + 7 ) % 7;
		for ( $i = 0; $i < $lead; $i++ ) :
			?>
			<div class="mzk-cal__cell mzk-cal__cell--empty"></div>
		<?php endfor; ?>

		<?php
		for ( $day = 1; $day <= $days_in; $day++ ) :
			$date     = $month . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
			$is_today = $date === $today;
			$blocked  = isset( $blackouts[ $date ] );
			$rows     = isset( $sessions[ $date ] ) ? $sessions[ $date ] : array();
			$classes  = 'mzk-cal__cell';
			$classes .= $is_today ? ' is-today' : '';
			$classes .= $blocked ? ' is-blocked' : '';
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<div class="mzk-cal__date">
					<span><?php echo esc_html( $day ); ?></span>
					<a class="mzk-cal__add"
						href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=add&date=' . $date ) ); ?>"
						title="<?php esc_attr_e( 'Add a session on this date', 'mizuki-booking' ); ?>">+</a>
				</div>

				<?php if ( $blocked ) : ?>
					<div class="mzk-cal__blocked">
						<?php echo esc_html( $blackouts[ $date ] ? $blackouts[ $date ] : __( 'Studio closed', 'mizuki-booking' ) ); ?>
					</div>
				<?php endif; ?>

				<?php foreach ( $rows as $session ) : ?>
					<div class="mzk-slot mzk-slot--<?php echo esc_attr( $session->status ); ?>"
						style="--mzk-accent: <?php echo esc_attr( $session->class_colour ); ?>">
						<div class="mzk-slot__top">
							<strong><?php echo esc_html( wp_date( get_option( 'time_format' ), MZK_Utils::session_start( $session )->getTimestamp() ) ); ?></strong>
							<span class="mzk-slot__class"><?php echo esc_html( $session->class_name ); ?></span>
						</div>
						<div class="mzk-slot__meta">
							<span title="<?php esc_attr_e( 'Booked / limit', 'mizuki-booking' ); ?>">
								<?php echo esc_html( $session->seats_taken . '/' . $session->effective_capacity ); ?>
							</span>
							<span><?php echo esc_html( $session->duration_label ); ?></span>
							<?php if ( 'open' !== $session->status ) : ?>
								<span class="mzk-tag"><?php echo esc_html( MZK_Utils::session_statuses()[ $session->status ] ); ?></span>
							<?php endif; ?>
						</div>
						<div class="mzk-slot__actions">
							<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => -1, 'return_page' => MZK_Admin::SLUG ) ) ); ?>"
								title="<?php esc_attr_e( 'Hold back one place (e.g. a student who signed up over chat)', 'mizuki-booking' ); ?>">−</a>
							<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'adjust_capacity', array( 'id' => $session->id, 'delta' => 1, 'return_page' => MZK_Admin::SLUG ) ) ); ?>"
								title="<?php esc_attr_e( 'Open one more place', 'mizuki-booking' ); ?>">+</a>
							<a class="mzk-mini" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-sessions&tab=add&id=' . $session->id ) ); ?>"
								title="<?php esc_attr_e( 'Edit session', 'mizuki-booking' ); ?>"><?php esc_html_e( 'Edit', 'mizuki-booking' ); ?></a>
							<a class="mzk-mini" href="<?php echo esc_url( admin_url( 'admin.php?page=mzk-bookings&session_id=' . $session->id ) ); ?>"
								title="<?php esc_attr_e( 'View participants', 'mizuki-booking' ); ?>"><?php esc_html_e( 'List', 'mizuki-booking' ); ?></a>
							<?php if ( 'open' === $session->status ) : ?>
								<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'session_status', array( 'id' => $session->id, 'status' => 'closed', 'return_page' => MZK_Admin::SLUG ) ) ); ?>"
									title="<?php esc_attr_e( 'Hide from students', 'mizuki-booking' ); ?>"><?php esc_html_e( 'Close', 'mizuki-booking' ); ?></a>
							<?php else : ?>
								<a class="mzk-mini" href="<?php echo esc_url( MZK_Admin::action_url( 'session_status', array( 'id' => $session->id, 'status' => 'open', 'return_page' => MZK_Admin::SLUG ) ) ); ?>"
									title="<?php esc_attr_e( 'Show to students again', 'mizuki-booking' ); ?>"><?php esc_html_e( 'Open', 'mizuki-booking' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endfor; ?>
	</div>

	<p class="description">
		<?php esc_html_e( 'The − and + buttons change how many places are offered on the website without touching the class limit — use them to hold seats for students who booked directly with you.', 'mizuki-booking' ); ?>
	</p>
</div>
