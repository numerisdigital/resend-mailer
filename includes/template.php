<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render the stored template with all variable substitutions applied.
 *
 * @param string $body    The email body content (HTML or plain text).
 * @param string $subject The email subject line.
 * @return string Fully rendered HTML email ready to send.
 */
function rm_render_template( $body, $subject ) {
	$template = rm_opt( 'template' ) ?: rm_default_template();

	// If the body looks like plain text, preserve line breaks as HTML.
	if ( strpos( ltrim( $body ), '<' ) !== 0 ) {
		$body = nl2br( esc_html( $body ) );
	}

	// Logo settings.
	$logo_id       = (int) rm_opt( 'logo_id' );
	$logo_url      = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
	$logo_height   = max( 1, (int) ( rm_opt( 'logo_height' ) ?: 48 ) );
	$logo_align    = in_array( rm_opt( 'logo_align' ), [ 'left', 'center', 'right' ], true )
	                 ? rm_opt( 'logo_align' ) : 'center';
	$logo_position = rm_opt( 'logo_position' ) === 'above' ? 'above' : 'inside';

	// CSS margin to achieve alignment on a block-level <img>.
	$margin_map = [
		'left'   => '0 auto 0 0',
		'center' => '0 auto',
		'right'  => '0 0 0 auto',
	];
	$img_margin = $margin_map[ $logo_align ];

	$logo_img = $logo_url
		? '<img src="' . esc_url( $logo_url ) . '"
                 alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"
                 style="height:' . $logo_height . 'px;width:auto;display:block;border:0;margin:' . $img_margin . ';" />'
		: '';

	// Build logo_block (inside the card) and logo_above (outside the card).
	if ( $logo_img && $logo_position === 'inside' ) {
		$logo_block = '
          <tr>
            <td class="rm-logo-cell" align="' . $logo_align . '"
                style="padding:32px 52px 0;text-align:' . $logo_align . ';">
              ' . $logo_img . '
            </td>
          </tr>';
		$logo_above = '';
	} elseif ( $logo_img && $logo_position === 'above' ) {
		$logo_block = '';
		$logo_above = '
          <table width="600" cellpadding="0" cellspacing="0" role="presentation"
                 style="max-width:600px;width:100%;">
            <tr>
              <td align="' . $logo_align . '"
                  style="padding:20px 52px;text-align:' . $logo_align . ';">
                ' . $logo_img . '
              </td>
            </tr>
          </table>';
	} else {
		$logo_block = '';
		$logo_above = '';
	}

	$vars = [
		'{{subject}}'         => esc_html( $subject ),
		'{{body}}'            => $body,
		'{{logo_block}}'      => $logo_block,
		'{{logo_above}}'      => $logo_above,
		'{{bg_color}}'        => esc_attr( rm_opt( 'bg_color',        '#f0f0f0' ) ),
		'{{container_color}}' => esc_attr( rm_opt( 'container_color', '#ffffff' ) ),
		'{{text_color}}'      => esc_attr( rm_opt( 'text_color',      '#1a1a1a' ) ),
		'{{heading_font}}'    => esc_attr( rm_opt( 'heading_font',    'Georgia, "Times New Roman", serif' ) ),
		'{{body_font}}'       => esc_attr( rm_opt( 'body_font',       'Arial, Helvetica, sans-serif' ) ),
		'{{site_name}}'       => esc_html( get_bloginfo( 'name' ) ),
		'{{site_url}}'        => esc_url( home_url() ),
		'{{year}}'            => (string) gmdate( 'Y' ),
	];

	return str_replace( array_keys( $vars ), array_values( $vars ), $template );
}
