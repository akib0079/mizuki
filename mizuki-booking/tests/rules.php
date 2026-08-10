<?php
/**
 * Booking rules — run with: php tests/rules.php
 *
 * Covers the rules the studio actually depends on: the per-class reschedule
 * cutoffs, which booking states hold a seat, and that every notification has a
 * template using only merge tags we really replace.
 *
 * @package Mizuki_Booking
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MZK_VERSION', 'test' );

class WP_Error {
	public $code; public $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Asia/Singapore' ); }
function __( $s, $d = null ) { return $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html__( $s, $d = null ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function get_bloginfo( $k ) { return 'Mizuki'; }
function get_option( $k, $d = null ) {
	$m = array( 'admin_email' => 'studio@mizuki.com.sg', 'date_format' => 'j M Y', 'time_format' => 'g:i a' );
	return $m[ $k ] ?? $d;
}
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_date( $f, $ts ) { return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() )->format( $f ); }
function current_time( $t ) { return gmdate( 'Y-m-d H:i:s' ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function sanitize_hex_color( $s ) { return $s; }
function wp_kses_post( $s ) { return $s; }
function wp_rand( $a, $b ) { return $a; }
function wp_generate_password( $l ) { return str_repeat( 'x', $l ); }
function current_user_can() { return true; }
function wp_die( $m ) { throw new Exception( $m ); }
function add_query_arg() { return ''; }
function home_url( $p = '/' ) { return 'https://mizuki.com.sg' . $p; }
function get_permalink() { return ''; }
function get_post_status() { return 'publish'; }
class MZK_Blackouts { public static function is_blocked() { return false; } }

require __DIR__ . '/../includes/class-mzk-utils.php';
require __DIR__ . '/../includes/class-mzk-install.php';
require __DIR__ . '/../includes/class-mzk-class-types.php';
require __DIR__ . '/../includes/class-mzk-setup.php';

$pass = 0; $fail = 0;
function ck( $l, $a, $e ) {
	global $pass, $fail;
	if ( $a === $e ) { $pass++; echo "  ok   {$l}\n"; }
	else { $fail++; echo "  FAIL {$l} — got " . var_export( $a, true ) . "\n"; }
}

function at( $when, $type, $booking = null ) {
	$start = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( $when );
	$s = (object) array(
		'session_date'     => $start->format( 'Y-m-d' ),
		'start_time'       => $start->format( 'H:i:s' ),
		'duration_minutes' => 120,
	);
	return MZK_Class_Types::can_reschedule( $type, $s, $booking );
}

$strict = (object) array(
	'name' => 'Ikebana', 'reschedule_enabled' => 1, 'reschedule_cutoff_hours' => 72,
	'cancel_enabled' => 1, 'cancel_cutoff_hours' => 72, 'max_reschedules' => 0,
);
$flexible = (object) array(
	'name' => 'Preserved Flower', 'reschedule_enabled' => 1, 'reschedule_cutoff_hours' => 24,
	'cancel_enabled' => 1, 'cancel_cutoff_hours' => 24, 'max_reschedules' => 0,
);

echo "Fresh Flower / Ikebana — changes close 3 days before\n";
ck( '4 days out, still allowed', is_wp_error( at( '+4 days', $strict ) ), false );
ck( '73 hours out, still allowed', is_wp_error( at( '+73 hours', $strict ) ), false );
ck( '71 hours out, refused', at( '+71 hours', $strict )->get_error_code(), 'mzk_reschedule_cutoff' );
ck( 'Wednesday before a Saturday class, refused', at( '+2 days', $strict )->get_error_code(), 'mzk_reschedule_cutoff' );
ck( 'after the class began, refused', at( '-1 hours', $strict )->get_error_code(), 'mzk_reschedule_cutoff' );

echo "\nPreserved Flower / IFDA — changes close 24 hours before\n";
ck( '2 days out, allowed', is_wp_error( at( '+2 days', $flexible ) ), false );
ck( '30 hours out, allowed', is_wp_error( at( '+30 hours', $flexible ) ), false );
ck( '10 hours out, refused', at( '+10 hours', $flexible )->get_error_code(), 'mzk_reschedule_cutoff' );

echo "\nHow the cutoff is worded to students\n";
ck( '72 hours reads as "3 days"', MZK_Class_Types::describe_cutoff( 72 ), '3 days' );
ck( '24 hours reads as "1 day"', MZK_Class_Types::describe_cutoff( 24 ), '1 day' );

echo "\nWhich states hold a place\n";
$occ = MZK_Utils::occupying_statuses();
foreach ( array( 'confirmed', 'attended', 'pending', 'awaiting_approval' ) as $s ) {
	ck( "'{$s}' holds the place", in_array( $s, $occ, true ), true );
}
foreach ( array( 'cancelled', 'declined', 'expired', 'moved' ) as $s ) {
	ck( "'{$s}' frees the place", in_array( $s, $occ, true ), false );
}
ck( 'every holding state has a label', array_diff( $occ, array_keys( MZK_Utils::booking_statuses() ) ), array() );

echo "\nNotifications\n";
$defaults = MZK_Install::default_settings();
$known = array(
	'{student_name}', '{student_email}', '{student_phone}', '{class_type}', '{session_title}',
	'{session_date}', '{session_time}', '{session_duration}', '{seats}', '{booking_id}',
	'{manage_url}', '{studio_name}', '{site_url}', '{old_session_date}', '{old_session_time}',
	'{sessions_left}', '{dashboard_url}', '{decline_reason}', '{password_url}', '{reason}',
);
foreach ( array( 'confirm', 'reminder', 'reschedule', 'cancel', 'pending', 'approved', 'declined', 'welcome', 'moved' ) as $t ) {
	ck( "'{$t}' has a subject and body", ( ! empty( $defaults[ $t . '_subject' ] ) && ! empty( $defaults[ $t . '_body' ] ) ), true );
	preg_match_all( '/\{[a-z_]+\}/', $defaults[ $t . '_body' ] . $defaults[ $t . '_subject' ], $m );
	ck( "'{$t}' uses only tags we replace", array_values( array_unique( array_diff( $m[0], $known ) ) ), array() );
}

echo "\nGenerated pages\n";
$pages = MZK_Setup::pages();
foreach ( $pages as $key => $page ) {
	ck( "'{$key}' can be stored in settings", array_key_exists( $key, $defaults ), true );
}
ck( 'page slugs are unique', count( array_unique( array_column( $pages, 'slug' ) ) ), count( $pages ) );
ck( 'shortcodes are unique', count( array_unique( array_column( $pages, 'shortcode' ) ) ), count( $pages ) );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
