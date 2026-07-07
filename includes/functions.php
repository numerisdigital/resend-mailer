<?php
defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin option with an optional default.
 */
function rm_opt( $key, $default = '' ) {
	return (string) get_option( 'rm_' . $key, $default );
}

/**
 * Parse a comma-separated string of email addresses into a clean array,
 * silently dropping anything that isn't a valid email.
 */
function rm_parse_email_list( $value ) {
	$parts = array_map( 'trim', explode( ',', (string) $value ) );
	return array_values( array_filter( $parts, 'is_email' ) );
}

/**
 * Stable identifier for a wp_mail() "to" value, used to group every send
 * that goes to the same address(es) under one detected "form" entry.
 * Bespoke contact forms (this plugin's actual target — neither client site
 * runs a form plugin with its own registry) always mail a fixed address, so
 * the destination itself is the most reliable proxy for "which form sent
 * this", without needing any per-theme registration.
 */
function rm_forms_key( $to ) {
	$addresses = array_map( 'strtolower', array_map( 'trim', (array) $to ) );
	sort( $addresses );
	return md5( implode( ',', $addresses ) );
}

/**
 * All auto-detected mail destinations, keyed by rm_forms_key().
 */
function rm_get_detected_forms() {
	$forms = get_option( 'rm_detected_forms', [] );
	return is_array( $forms ) ? $forms : [];
}

/**
 * Called on every intercepted wp_mail() send, before any override is
 * applied, so the Sending tab's "Forms" list is self-populating — no setup
 * needed on any site this plugin is installed on.
 */
function rm_record_detected_form( $to ) {
	$key   = rm_forms_key( $to );
	$forms = rm_get_detected_forms();

	if ( isset( $forms[ $key ] ) ) {
		$forms[ $key ]['last_seen'] = time();
		$forms[ $key ]['count']     = (int) ( $forms[ $key ]['count'] ?? 0 ) + 1;
	} else {
		$address       = implode( ', ', array_map( 'trim', (array) $to ) );
		$forms[ $key ] = [
			'to'         => $address,
			'label'      => $address,
			'enabled'    => false,
			'recipients' => '',
			'first_seen' => time(),
			'last_seen'  => time(),
			'count'      => 1,
		];
	}

	update_option( 'rm_detected_forms', $forms );
}

/**
 * Recipient override for a given original "to", if the matching detected
 * form has been ticked on with valid override recipient(s) configured.
 * Returns an empty array when no override should apply.
 */
function rm_get_form_override( $to ) {
	$forms = rm_get_detected_forms();
	$key   = rm_forms_key( $to );

	if ( empty( $forms[ $key ]['enabled'] ) ) {
		return [];
	}

	return rm_parse_email_list( $forms[ $key ]['recipients'] ?? '' );
}

/**
 * Default HTML email template.
 * Uses {{variable}} placeholders replaced at send-time.
 */
function rm_default_template() {
	return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="x-apple-disable-message-reformatting" />
  <title>{{subject}}</title>
  <style type="text/css">
    body { margin: 0; padding: 0; }
    table { border-spacing: 0; }
    td { padding: 0; }
    img { border: 0; display: block; }
    @media screen and (max-width: 640px) {
      .rm-container { width: 100% !important; border-radius: 0 !important; }
      .rm-content   { padding: 32px 24px !important; }
      .rm-footer    { padding: 20px 24px !important; }
      .rm-logo-cell { padding: 28px 24px 0 !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:{{bg_color}};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
         style="background-color:{{bg_color}};">
    <tr>
      <td align="center" style="padding:48px 20px;">

        {{logo_above}}

        <table class="rm-container" width="600" cellpadding="0" cellspacing="0" role="presentation"
               style="background-color:{{container_color}};border-radius:10px;overflow:hidden;max-width:600px;width:100%;">

          {{logo_block}}

          <tr>
            <td class="rm-content"
                style="padding:44px 52px;font-family:{{body_font}};font-size:16px;line-height:1.65;color:{{text_color}};">
              {{body}}
            </td>
          </tr>

          <tr>
            <td class="rm-footer" align="center"
                style="padding:24px 52px;border-top:1px solid rgba(0,0,0,0.07);font-family:{{body_font}};font-size:13px;color:#9ca3af;line-height:1.5;">
              &copy; {{year}}
              <a href="{{site_url}}" style="color:#9ca3af;text-decoration:none;">{{site_name}}</a>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>';
}
