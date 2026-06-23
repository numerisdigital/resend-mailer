<?php
defined( 'ABSPATH' ) || exit;

/**
 * Get a plugin option with an optional default.
 */
function rm_opt( $key, $default = '' ) {
	return (string) get_option( 'rm_' . $key, $default );
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
