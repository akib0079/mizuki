<?php
/**
 * The public "our classes" page: what each class is, what it costs, when the next
 * dates are, and a button to enrol.
 *
 * This is the missing front door — the calendar answers "when can I come?", but a
 * student arriving cold first needs to know what the classes actually are.
 *
 * [mizuki_classes]                 — all active classes as cards
 * [mizuki_classes class="ikebana"] — one class in detail
 * ?class=ikebana on the same page  — detail view for that class
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Classes_Page {

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( 'mizuki_classes', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [mizuki_classes]
	 *
	 * @param array $atts class, columns, show_dates.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'      => '',
				'columns'    => 3,
				'show_dates' => 'yes',
			),
			$atts,
			'mizuki_classes'
		);

		wp_enqueue_style( 'mzk-front' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_GET['class'] ) ? sanitize_title( wp_unslash( $_GET['class'] ) ) : '';
		$single    = MZK_Class_Types::resolve( $requested ? $requested : $atts['class'] );

		ob_start();

		if ( $single && ! empty( $single->active ) ) {
			self::render_detail( $single );
		} else {
			self::render_list( $atts );
		}

		return ob_get_clean();
	}

	/**
	 * Where "Enrol" should send a student for a given class.
	 *
	 * Uses the class's own booking URL when the studio has set one (typically a
	 * WooCommerce product for paid workshops), otherwise the booking calendar
	 * filtered to that class.
	 *
	 * @param object $type Class type row.
	 * @return string
	 */
	public static function enrol_url( $type ) {
		if ( ! empty( $type->booking_url ) ) {
			return $type->booking_url;
		}

		$page = (int) MZK_Install::get_setting( 'booking_page_id' );
		if ( ! $page ) {
			return '';
		}

		return add_query_arg( 'class', $type->slug, get_permalink( $page ) );
	}

	/**
	 * The next few bookable dates for a class.
	 *
	 * @param object $type  Class type row.
	 * @param int    $limit How many.
	 * @return object[]
	 */
	public static function next_dates( $type, $limit = 3 ) {
		$months = max( 2, (int) MZK_Install::get_setting( 'months_ahead', 3 ) );

		$sessions = MZK_Sessions::query(
			array(
				'from'          => MZK_Utils::today(),
				'to'            => gmdate( 'Y-m-t', strtotime( MZK_Utils::today() . " +{$months} months" ) ),
				'class_type_id' => (int) $type->id,
				'status'        => 'open',
				'only_bookable' => true,
				'limit'         => (int) $limit,
			)
		);

		return $sessions;
	}

	/**
	 * Card grid of every active class.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	private static function render_list( $atts ) {
		$types = MZK_Class_Types::all( true );

		if ( ! $types ) {
			echo '<div class="mzk-classes"><div class="mzk-notice mzk-notice--info">'
				. esc_html__( 'No classes are listed yet.', 'mizuki-booking' )
				. '</div></div>';
			return;
		}

		$columns = max( 1, min( 4, (int) $atts['columns'] ) );
		?>
		<div class="mzk-classes" style="--mzk-cols: <?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $types as $type ) : ?>
				<?php
				$url   = self::enrol_url( $type );
				$dates = 'no' === $atts['show_dates'] ? array() : self::next_dates( $type, 3 );
				?>
				<article class="mzk-class" style="--mzk-class: <?php echo esc_attr( $type->colour ); ?>">

					<?php if ( $type->image_id ) : ?>
						<div class="mzk-class__media">
							<?php echo wp_get_attachment_image( (int) $type->image_id, 'medium_large', false, array( 'class' => 'mzk-class__img' ) ); ?>
						</div>
					<?php endif; ?>

					<div class="mzk-class__body">
						<h3 class="mzk-class__name"><?php echo esc_html( $type->name ); ?></h3>

						<?php if ( $type->summary ) : ?>
							<p class="mzk-class__summary"><?php echo esc_html( $type->summary ); ?></p>
						<?php endif; ?>

						<ul class="mzk-class__facts">
							<li>
								<span><?php esc_html_e( 'Length', 'mizuki-booking' ); ?></span>
								<strong><?php echo esc_html( MZK_Utils::format_duration( $type->default_duration ) ); ?></strong>
							</li>
							<li>
								<span><?php esc_html_e( 'Class size', 'mizuki-booking' ); ?></span>
								<strong>
									<?php
									printf(
										/* translators: %d: maximum students. */
										esc_html__( 'up to %d', 'mizuki-booking' ),
										(int) $type->default_capacity
									);
									?>
								</strong>
							</li>
							<?php if ( $type->price_note ) : ?>
								<li>
									<span><?php esc_html_e( 'Price', 'mizuki-booking' ); ?></span>
									<strong><?php echo esc_html( $type->price_note ); ?></strong>
								</li>
							<?php endif; ?>
							<?php if ( $type->course_based ) : ?>
								<li>
									<span><?php esc_html_e( 'Format', 'mizuki-booking' ); ?></span>
									<strong><?php esc_html_e( 'Course — a set number of sessions', 'mizuki-booking' ); ?></strong>
								</li>
							<?php endif; ?>
						</ul>

						<?php if ( $dates ) : ?>
							<p class="mzk-class__next"><?php esc_html_e( 'Next available', 'mizuki-booking' ); ?></p>
							<ul class="mzk-class__dates">
								<?php foreach ( $dates as $session ) : ?>
									<li>
										<span><?php echo esc_html( $session->date_label ); ?></span>
										<span class="mzk-class__time"><?php echo esc_html( $session->time_label ); ?></span>
										<span class="mzk-class__seats">
											<?php
											printf(
												/* translators: %d: places left. */
												esc_html( _n( '%d place left', '%d places left', (int) $session->seats_available, 'mizuki-booking' ) ),
												(int) $session->seats_available
											);
											?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php elseif ( 'no' !== $atts['show_dates'] ) : ?>
							<p class="mzk-note"><?php esc_html_e( 'No dates open at the moment — please contact the studio.', 'mizuki-booking' ); ?></p>
						<?php endif; ?>

						<div class="mzk-class__actions">
							<?php if ( $url ) : ?>
								<a class="mzk-btn mzk-btn--primary" href="<?php echo esc_url( $url ); ?>">
									<?php echo $type->course_based ? esc_html__( 'Enrol', 'mizuki-booking' ) : esc_html__( 'Book a place', 'mizuki-booking' ); ?>
								</a>
							<?php endif; ?>
							<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( add_query_arg( 'class', $type->slug ) ); ?>">
								<?php esc_html_e( 'More details', 'mizuki-booking' ); ?>
							</a>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Full detail for one class, with every upcoming date.
	 *
	 * @param object $type Class type row.
	 */
	private static function render_detail( $type ) {
		$url      = self::enrol_url( $type );
		$sessions = self::next_dates( $type, 24 );
		?>
		<div class="mzk-classes mzk-classes--single">
			<article class="mzk-class mzk-class--detail" style="--mzk-class: <?php echo esc_attr( $type->colour ); ?>">

				<?php if ( $type->image_id ) : ?>
					<div class="mzk-class__media">
						<?php echo wp_get_attachment_image( (int) $type->image_id, 'large', false, array( 'class' => 'mzk-class__img' ) ); ?>
					</div>
				<?php endif; ?>

				<div class="mzk-class__body">
					<h3 class="mzk-class__name"><?php echo esc_html( $type->name ); ?></h3>

					<?php if ( $type->summary ) : ?>
						<p class="mzk-class__summary"><?php echo esc_html( $type->summary ); ?></p>
					<?php endif; ?>

					<?php if ( $type->description ) : ?>
						<div class="mzk-class__desc"><?php echo wp_kses_post( wpautop( $type->description ) ); ?></div>
					<?php endif; ?>

					<ul class="mzk-class__facts">
						<li>
							<span><?php esc_html_e( 'Length', 'mizuki-booking' ); ?></span>
							<strong><?php echo esc_html( MZK_Utils::format_duration( $type->default_duration ) ); ?></strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Class size', 'mizuki-booking' ); ?></span>
							<strong>
								<?php
								printf(
									/* translators: %d: maximum students. */
									esc_html__( 'up to %d students', 'mizuki-booking' ),
									(int) $type->default_capacity
								);
								?>
							</strong>
						</li>
						<?php if ( $type->price_note ) : ?>
							<li>
								<span><?php esc_html_e( 'Price', 'mizuki-booking' ); ?></span>
								<strong><?php echo esc_html( $type->price_note ); ?></strong>
							</li>
						<?php endif; ?>
						<li>
							<span><?php esc_html_e( 'Changing your booking', 'mizuki-booking' ); ?></span>
							<strong>
								<?php
								if ( $type->reschedule_enabled ) {
									printf(
										/* translators: %s: cutoff, e.g. "3 days". */
										esc_html__( 'up to %s before the class', 'mizuki-booking' ),
										esc_html( MZK_Class_Types::describe_cutoff( (float) $type->reschedule_cutoff_hours ) )
									);
								} else {
									esc_html_e( 'please contact the studio', 'mizuki-booking' );
								}
								?>
							</strong>
						</li>
					</ul>

					<?php if ( $type->course_based ) : ?>
						<div class="mzk-notice mzk-notice--info">
							<?php esc_html_e( 'This is a course: you buy a set number of sessions and book them on the dates that suit you. We can extend your sessions if you need more time.', 'mizuki-booking' ); ?>
						</div>
					<?php endif; ?>

					<div class="mzk-class__actions">
						<?php if ( $url ) : ?>
							<a class="mzk-btn mzk-btn--primary" href="<?php echo esc_url( $url ); ?>">
								<?php echo $type->course_based ? esc_html__( 'Enrol on this course', 'mizuki-booking' ) : esc_html__( 'Book a place', 'mizuki-booking' ); ?>
							</a>
						<?php endif; ?>
						<a class="mzk-btn mzk-btn--ghost" href="<?php echo esc_url( remove_query_arg( 'class' ) ); ?>">
							<?php esc_html_e( 'All classes', 'mizuki-booking' ); ?>
						</a>
					</div>

					<h4 class="mzk-dash__title"><?php esc_html_e( 'Upcoming dates', 'mizuki-booking' ); ?></h4>

					<?php if ( ! $sessions ) : ?>
						<div class="mzk-notice mzk-notice--info">
							<?php esc_html_e( 'No dates are open at the moment. Please contact the studio and we will let you know when the next ones are announced.', 'mizuki-booking' ); ?>
						</div>
					<?php else : ?>
						<ul class="mzk-class__dates mzk-class__dates--full">
							<?php foreach ( $sessions as $session ) : ?>
								<li>
									<span><?php echo esc_html( $session->date_label ); ?></span>
									<span class="mzk-class__time"><?php echo esc_html( $session->time_label ); ?></span>
									<span class="mzk-class__seats">
										<?php
										printf(
											/* translators: %d: places left. */
											esc_html( _n( '%d place left', '%d places left', (int) $session->seats_available, 'mizuki-booking' ) ),
											(int) $session->seats_available
										);
										?>
									</span>
									<?php if ( $url ) : ?>
										<a class="mzk-mini" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Book', 'mizuki-booking' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</article>
		</div>
		<?php
	}
}
