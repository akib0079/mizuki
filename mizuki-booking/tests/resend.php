<?php
/** Checks the Resend payload and how failures are reported. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	public $code; public $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function get_option( $k, $d = null ) { return 'admin_email' === $k ? 'studio@mizuki.com.sg' : $d; }
function get_bloginfo( $k ) { return 'Mizuki'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }

$GLOBALS['captured'] = null;
$GLOBALS['next_response'] = array( 'code' => 200, 'body' => '{"id":"abc"}' );

function wp_remote_post( $url, $args ) {
	$GLOBALS['captured'] = array( 'url' => $url, 'args' => $args );
	$r = $GLOBALS['next_response'];
	return isset( $r['wp_error'] ) ? new WP_Error( 'http', $r['wp_error'] ) : $r;
}
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_response_message( $r ) { return $r['msg'] ?? ''; }

class MZK_Install {
	public static $s = array();
	public static function get_setting( $k, $d = null ) { return self::$s[ $k ] ?? $d; }
}

require __DIR__ . '/../includes/class-mzk-resend.php';

$pass = 0; $fail = 0;
function ck( $l, $a, $e ) {
	global $pass, $fail;
	if ( $a === $e ) { $pass++; echo "  ok   {$l}\n"; }
	else { $fail++; echo "  FAIL {$l} — got " . var_export( $a, true ) . "\n"; }
}

MZK_Install::$s = array(
	'mail_provider'   => 'resend',
	'resend_api_key'  => 're_test_1234567890abcd',
	'mail_from_name'  => 'Mizuki Flower Studio',
	'mail_from_email' => 'hello@mizuki.com.sg',
	'mail_reply_to'   => 'studio@mizuki.com.sg',
);

echo "Configuration\n";
ck( 'enabled when provider is resend and a key is set', MZK_Resend::enabled(), true );
ck( 'From header is name + address', MZK_Resend::from(), 'Mizuki Flower Studio <hello@mizuki.com.sg>' );
ck( 'key is masked for display', MZK_Resend::masked_key(), str_repeat( '•', 8 ) . ' abcd' );
ck( 'masked key never leaks the secret', strpos( MZK_Resend::masked_key(), 're_test' ), false );

MZK_Install::$s['mail_provider'] = 'wp';
ck( 'disabled when provider is the WordPress mailer', MZK_Resend::enabled(), false );
MZK_Install::$s['mail_provider'] = 'resend';

echo "\nA successful send\n";
$GLOBALS['next_response'] = array( 'code' => 200, 'body' => '{"id":"abc"}' );
ck( 'returns true', MZK_Resend::send( 'student@example.com', 'Your booking', '<p>Hi</p>' ), true );

$body = json_decode( $GLOBALS['captured']['args']['body'], true );
ck( 'posts to the Resend endpoint', $GLOBALS['captured']['url'], 'https://api.resend.com/emails' );
ck( 'sends the API key as a bearer token', $GLOBALS['captured']['args']['headers']['Authorization'], 'Bearer re_test_1234567890abcd' );
ck( 'recipient is an array', $body['to'], array( 'student@example.com' ) );
ck( 'subject is passed through', $body['subject'], 'Your booking' );
ck( 'html body is passed through', $body['html'], '<p>Hi</p>' );
ck( 'reply-to is set', $body['reply_to'], 'studio@mizuki.com.sg' );

MZK_Install::$s['mail_reply_to'] = 'not-an-email';
MZK_Resend::send( 'student@example.com', 'x', 'y' );
$body2 = json_decode( $GLOBALS['captured']['args']['body'], true );
ck( 'an invalid reply-to is left out entirely', isset( $body2['reply_to'] ), false );
MZK_Install::$s['mail_reply_to'] = 'studio@mizuki.com.sg';

echo "\nFailures say what actually went wrong\n";
$GLOBALS['next_response'] = array( 'code' => 401, 'body' => '{"message":"Invalid token"}' );
$r = MZK_Resend::send( 'student@example.com', 'x', 'y' );
ck( 'a bad key is reported as a key problem', ( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'API key' ) ), true );

$GLOBALS['next_response'] = array( 'code' => 403, 'body' => '{"message":"The domain is not verified"}' );
$r = MZK_Resend::send( 'student@example.com', 'x', 'y' );
ck( 'an unverified domain is named as such', ( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'not verified' ) ), true );
ck( 'and the offending address is shown', ( false !== strpos( $r->get_error_message(), 'hello@mizuki.com.sg' ) ), true );

$GLOBALS['next_response'] = array( 'wp_error' => 'cURL error 7: connection refused' );
$r = MZK_Resend::send( 'student@example.com', 'x', 'y' );
ck( 'a blocked host is reported, not swallowed', ( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'Could not reach Resend' ) ), true );

$GLOBALS['next_response'] = array( 'code' => 200, 'body' => '{}' );
$r = MZK_Resend::send( 'not-an-address', 'x', 'y' );
ck( 'a malformed recipient is refused before sending', ( is_wp_error( $r ) && 'mzk_resend_bad_to' === $r->get_error_code() ), true );

MZK_Install::$s['resend_api_key'] = '';
$r = MZK_Resend::send( 'student@example.com', 'x', 'y' );
ck( 'a missing key is refused before sending', ( is_wp_error( $r ) && 'mzk_resend_no_key' === $r->get_error_code() ), true );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
