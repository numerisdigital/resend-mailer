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

	// Build the logo block.
	$logo_id  = (int) rm_opt( 'logo_id' );
	$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

	if ( $logo_url ) {
		$logo_block = '<tr>
            <td class="rm-logo-cell" align="center"
                style="padding:36px 52px 0;">
              <img src="' . esc_url( $logo_url ) . '"
                   alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"
                   style="max-height:56px;max-width:220px;display:block;border:0;" />
            </td>
          </tr>';
	} else {
		$logo_block = '';
	}

	$vars = [
		'{{subject}}'         => esc_html( $subject ),
		'{{body}}'            => $body,
		'{{logo_block}}'      => $logo_block,
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
