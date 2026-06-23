<?php
defined( 'ABSPATH' ) || exit;

// Always register the filter; conditions are checked inside.
add_filter( 'pre_wp_mail', 'rm_intercept_wp_mail', 10, 2 );

/**
 * Intercept wp_mail() and route it through Resend with the custom template applied.
 *
 * Returning a non-null value short-circuits wp_mail() entirely.
 */
function rm_intercept_wp_mail( $null, $atts ) {
	// Pass through if interception is disabled or no API key is set.
	if ( rm_opt( 'intercept_wp_mail', '1' ) !== '1' ) {
		return $null;
	}
	if ( ! rm_opt( 'api_key' ) ) {
		return $null;
	}

	$to      = $atts['to'];
	$subject = (string) $atts['subject'];
	$message = (string) $atts['message'];
	$headers = (array) $atts['headers'];

	// Determine sender from headers, falling back to plugin settings.
	$from_name  = rm_opt( 'from_name',  get_bloginfo( 'name' ) );
	$from_email = rm_opt( 'from_email', (string) get_option( 'admin_email' ) );

	foreach ( $headers as $header ) {
		if ( ! is_string( $header ) ) continue;
		if ( stripos( $header, 'from:' ) === 0 ) {
			$from_raw = trim( substr( $header, 5 ) );
			if ( preg_match( '/^(.+?)\s*<([^>]+)>$/', $from_raw, $m ) ) {
				$from_name  = trim( $m[1] );
				$from_email = trim( $m[2] );
			} elseif ( is_email( $from_raw ) ) {
				$from_email = $from_raw;
			}
		}
	}

	$from = $from_name ? "{$from_name} <{$from_email}>" : $from_email;
	$html = rm_render_template( $message, $subject );

	rm_send_via_resend( $to, $subject, $html, $from );

	return true; // short-circuit wp_mail
}

/**
 * Send an email directly through the Resend REST API.
 *
 * @param string|array $to      One or more recipient email addresses.
 * @param string       $subject Subject line.
 * @param string       $html    Fully rendered HTML body.
 * @param string       $from    Formatted sender: "Name <email>".
 * @return array{success: bool, error: string}
 */
function rm_send_via_resend( $to, $subject, $html, $from = '' ) {
	$api_key = rm_opt( 'api_key' );
	if ( ! $api_key ) {
		return [ 'success' => false, 'error' => 'No Resend API key configured.' ];
	}

	if ( ! $from ) {
		$name  = rm_opt( 'from_name',  get_bloginfo( 'name' ) );
		$email = rm_opt( 'from_email', (string) get_option( 'admin_email' ) );
		$from  = $name ? "{$name} <{$email}>" : $email;
	}

	$payload = [
		'from'    => $from,
		'to'      => (array) $to,
		'subject' => $subject,
		'html'    => $html,
	];

	$reply_to = rm_opt( 'reply_to' );
	if ( $reply_to && is_email( $reply_to ) ) {
		$payload['reply_to'] = [ $reply_to ];
	}

	$response = wp_remote_post( 'https://api.resend.com/emails', [
		'timeout' => 15,
		'headers' => [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		],
		'body'    => wp_json_encode( $payload ),
	] );

	if ( is_wp_error( $response ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Resend Mailer] ' . $response->get_error_message() );
		return [ 'success' => false, 'error' => $response->get_error_message() ];
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$msg = isset( $body['message'] ) ? (string) $body['message'] : "HTTP {$code}";
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Resend Mailer] API error: ' . $msg );
		return [ 'success' => false, 'error' => $msg ];
	}

	return [ 'success' => true, 'error' => '' ];
}
