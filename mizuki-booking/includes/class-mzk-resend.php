<?php
/**
 * Resend delivery.
 *
 * wp_mail() on shared hosting fails quietly: PHP's mail() hands off to a local
 * sendmail that is often unconfigured or unroutable, WordPress reports success,
 * and nothing arrives. Sending over Resend's HTTPS API removes that guesswork —
 * every send returns a real result, which is written to the delivery log.
 *
 * @package Mizuki_Booking
 */

defined( 'ABSPATH' ) || exit;

class MZK_Resend {

	const ENDPOINT = 'https://api.resend.com/emails';

	/**
	 * Should mail go through Resend?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'resend' === MZK_Install::get_setting( 'mail_provider', 'wp' ) && '' !== self::api_key();
	}

	/**
	 * The stored API key.
	 *
	 * @return string
	 */
	public static function api_key() {
		return trim( (string) MZK_Install::get_setting( 'resend_api_key', '' ) );
	}

	/**
	 * Masked key for display, so the screen never shows the whole secret.
	 *
	 * @return string
	 */
	public static function masked_key() {
		$key = self::api_key();
		if ( '' === $key ) {
			return '';
		}
		return str_repeat( '•', 8 ) . ' ' . substr( $key, -4 );
	}

	/**
	 * The From header, as Resend expects it.
	 *
	 * @return string
	 */
	public static function from() {
		$name  = trim( (string) MZK_Install::get_setting( 'mail_from_name', '' ) );
		$email = trim( (string) MZK_Install::get_setting( 'mail_from_email', '' ) );

		if ( ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}
		if ( '' === $name ) {
			$name = MZK_Install::get_setting( 'studio_name', get_bloginfo( 'name' ) );
		}

		return sprintf( '%s <%s>', $name, $email );
	}

	/**
	 * Send one message through Resend.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return true|WP_Error
	 */
	public static function send( $to, $subject, $html ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'mzk_resend_no_key', __( 'No Resend API key has been saved.', 'mizuki-booking' ) );
		}
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'mzk_resend_bad_to', __( 'That is not a valid e-mail address.', 'mizuki-booking' ) );
		}

		$body = array(
			'from'    => self::from(),
			'to'      => array( $to ),
			'subject' => $subject,
			'html'    => $html,
		);

		$reply_to = trim( (string) MZK_Install::get_setting( 'mail_reply_to', '' ) );
		if ( is_email( $reply_to ) ) {
			$body['reply_to'] = $reply_to;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Network level: DNS, firewall, timeout. Hosts sometimes block
			// outbound HTTPS, which is worth saying plainly.
			return new WP_Error(
				'mzk_resend_unreachable',
				sprintf(
					/* translators: %s: underlying error. */
					__( 'Could not reach Resend: %s', 'mizuki-booking' ),
					$response->get_error_message()
				)
			);
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$message = '';
		if ( is_array( $parsed ) ) {
			$message = $parsed['message'] ?? ( $parsed['error']['message'] ?? '' );
		}
		if ( '' === $message ) {
			$message = wp_remote_retrieve_response_message( $response );
		}

		// The two mistakes almost everyone makes, named explicitly.
		if ( 401 === $code ) {
			$message = __( 'Resend rejected the API key. Check it was copied in full.', 'mizuki-booking' );
		} elseif ( 403 === $code || false !== stripos( $message, 'domain' ) ) {
			$message = sprintf(
				/* translators: %s: the from address. */
				__( 'Resend will not send from %s — that domain is not verified on your Resend account yet.', 'mizuki-booking' ),
				self::from()
			);
		}

		return new WP_Error(
			'mzk_resend_failed',
			sprintf(
				/* translators: 1: HTTP status, 2: message. */
				__( 'Resend refused the message (%1$d): %2$s', 'mizuki-booking' ),
				$code,
				$message
			)
		);
	}

	/**
	 * Check the key and domain without sending anything to a student.
	 *
	 * @return true|WP_Error
	 */
	public static function verify() {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'mzk_resend_no_key', __( 'Enter your Resend API key first.', 'mizuki-booking' ) );
		}

		$response = wp_remote_get(
			'https://api.resend.com/domains',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mzk_resend_unreachable',
				sprintf(
					/* translators: %s: underlying error. */
					__( 'Could not reach Resend: %s. Your host may be blocking outgoing connections.', 'mizuki-booking' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'mzk_resend_bad_key', __( 'Resend did not accept that API key.', 'mizuki-booking' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'mzk_resend_failed',
				sprintf(
					/* translators: %d: HTTP status. */
					__( 'Resend answered with status %d.', 'mizuki-booking' ),
					$code
				)
			);
		}

		// Is the From domain actually verified?
		$parsed  = json_decode( wp_remote_retrieve_body( $response ), true );
		$domains = is_array( $parsed ) && isset( $parsed['data'] ) ? (array) $parsed['data'] : array();

		$from_email = trim( (string) MZK_Install::get_setting( 'mail_from_email', '' ) );
		$from_email = is_email( $from_email ) ? $from_email : get_option( 'admin_email' );
		$domain     = substr( strrchr( $from_email, '@' ), 1 );

		foreach ( $domains as $entry ) {
			if ( ! isset( $entry['name'] ) || strtolower( $entry['name'] ) !== strtolower( $domain ) ) {
				continue;
			}
			if ( isset( $entry['status'] ) && 'verified' !== strtolower( $entry['status'] ) ) {
				return new WP_Error(
					'mzk_resend_domain_pending',
					sprintf(
						/* translators: 1: domain, 2: status. */
						__( 'The domain %1$s is on your Resend account but its status is “%2$s”. Finish the DNS records before sending.', 'mizuki-booking' ),
						$domain,
						$entry['status']
					)
				);
			}
			return true;
		}

		return new WP_Error(
			'mzk_resend_domain_missing',
			sprintf(
				/* translators: 1: domain, 2: from address. */
				__( 'The key works, but %1$s is not a verified domain on your Resend account, so mail from %2$s will be refused. Add and verify the domain in Resend, or send from onboarding@resend.dev while testing.', 'mizuki-booking' ),
				$domain,
				$from_email
			)
		);
	}
}
