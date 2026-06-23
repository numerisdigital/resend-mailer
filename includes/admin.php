<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu',            'rm_add_menu' );
add_action( 'admin_enqueue_scripts', 'rm_enqueue_assets' );
add_action( 'wp_ajax_rm_send_test',      'rm_ajax_send_test' );
add_action( 'wp_ajax_rm_reset_template', 'rm_ajax_reset_template' );
add_action( 'wp_ajax_rm_preview_email',  'rm_ajax_preview_email' );

/* ── Menu ─────────────────────────────────────────────────────────── */

function rm_add_menu() {
	add_options_page(
		'Resend Mailer',
		'Resend Mailer',
		'manage_options',
		RM_SLUG,
		'rm_settings_page'
	);
}

/* ── Assets ───────────────────────────────────────────────────────── */

function rm_enqueue_assets( $hook ) {
	if ( $hook !== 'settings_page_' . RM_SLUG ) return;

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_media();
	wp_enqueue_style( 'rm-admin', RM_URL . 'assets/admin.css', [], RM_VERSION );
	wp_enqueue_script(
		'rm-admin',
		RM_URL . 'assets/admin.js',
		[ 'jquery', 'wp-color-picker' ],
		RM_VERSION,
		true
	);
	wp_localize_script( 'rm-admin', 'rmAdmin', [
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'rm_nonce' ),
		'defaultTpl'    => rm_default_template(),
		'resetConfirm'  => 'Reset to the default template? Your edits will be lost.',
		'mediaTitle'    => 'Select Logo',
		'mediaButton'   => 'Use this image',
	] );
}

/* ── Settings page ────────────────────────────────────────────────── */

function rm_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	/* ── Handle save ─────────────────────────────────────────────── */
	/* Only update options for the tab that submitted the form — other tabs'
	   fields are not rendered and would overwrite stored values with blanks. */
	$saved = false;
	if ( isset( $_POST['rm_nonce_field'] ) && wp_verify_nonce( sanitize_key( $_POST['rm_nonce_field'] ), 'rm_save' ) ) {

		$saving_tab = sanitize_key( $_POST['rm_saving_tab'] ?? 'sending' );

		if ( $saving_tab === 'sending' ) {
			update_option( 'rm_api_key',           sanitize_text_field( wp_unslash( $_POST['rm_api_key'] ?? '' ) ) );
			update_option( 'rm_from_name',         sanitize_text_field( wp_unslash( $_POST['rm_from_name'] ?? '' ) ) );
			update_option( 'rm_from_email',        sanitize_email( wp_unslash( $_POST['rm_from_email'] ?? '' ) ) );
			update_option( 'rm_reply_to',          sanitize_email( wp_unslash( $_POST['rm_reply_to'] ?? '' ) ) );
			update_option( 'rm_intercept_wp_mail', isset( $_POST['rm_intercept_wp_mail'] ) ? '1' : '0' );
		}

		if ( $saving_tab === 'design' ) {
			update_option( 'rm_bg_color',        sanitize_hex_color( wp_unslash( $_POST['rm_bg_color'] ?? '' ) ) ?: '#f0f0f0' );
			update_option( 'rm_container_color', sanitize_hex_color( wp_unslash( $_POST['rm_container_color'] ?? '' ) ) ?: '#ffffff' );
			update_option( 'rm_text_color',      sanitize_hex_color( wp_unslash( $_POST['rm_text_color'] ?? '' ) ) ?: '#1a1a1a' );
			update_option( 'rm_logo_id',         absint( $_POST['rm_logo_id'] ?? 0 ) );
			update_option( 'rm_logo_height',     max( 1, absint( $_POST['rm_logo_height'] ?? 48 ) ) );

			$logo_align = sanitize_key( $_POST['rm_logo_align'] ?? 'center' );
			update_option( 'rm_logo_align', in_array( $logo_align, [ 'left', 'center', 'right' ], true ) ? $logo_align : 'center' );

			$logo_position = sanitize_key( $_POST['rm_logo_position'] ?? 'inside' );
			update_option( 'rm_logo_position', in_array( $logo_position, [ 'inside', 'above' ], true ) ? $logo_position : 'inside' );

			$allowed_fonts = array_keys( RM_FONTS );
			$hf = sanitize_text_field( wp_unslash( $_POST['rm_heading_font'] ?? '' ) );
			$bf = sanitize_text_field( wp_unslash( $_POST['rm_body_font'] ?? '' ) );
			update_option( 'rm_heading_font', in_array( $hf, $allowed_fonts, true ) ? $hf : 'Georgia, "Times New Roman", serif' );
			update_option( 'rm_body_font',    in_array( $bf, $allowed_fonts, true ) ? $bf : 'Arial, Helvetica, sans-serif' );
		}

		if ( $saving_tab === 'template' ) {
			// Admins may save full HTML — skip kses.
			update_option( 'rm_template', wp_unslash( $_POST['rm_template'] ?? '' ) );
		}

		$saved = true;
	}

	$tab  = sanitize_key( $_GET['tab'] ?? 'sending' );
	$tabs = [
		'sending'  => 'Sending',
		'design'   => 'Design',
		'template' => 'Template',
	];

	/* ── Current values ──────────────────────────────────────────── */
	$api_key        = rm_opt( 'api_key' );
	$from_name      = rm_opt( 'from_name',  get_bloginfo( 'name' ) );
	$from_email     = rm_opt( 'from_email', (string) get_option( 'admin_email' ) );
	$reply_to       = rm_opt( 'reply_to' );
	$intercept      = rm_opt( 'intercept_wp_mail', '1' );
	$bg_color       = rm_opt( 'bg_color',        '#f0f0f0' );
	$container      = rm_opt( 'container_color', '#ffffff' );
	$text_color     = rm_opt( 'text_color',      '#1a1a1a' );
	$logo_id        = (int) rm_opt( 'logo_id' );
	$logo_url       = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
	$logo_height    = rm_opt( 'logo_height', '48' );
	$logo_align     = rm_opt( 'logo_align',  'center' );
	$logo_position  = rm_opt( 'logo_position', 'inside' );
	$heading_font   = rm_opt( 'heading_font', 'Georgia, "Times New Roman", serif' );
	$body_font      = rm_opt( 'body_font',    'Arial, Helvetica, sans-serif' );
	$template       = rm_opt( 'template' ) ?: rm_default_template();
	$admin_email    = (string) get_option( 'admin_email' );

	?>
	<div class="wrap rm-wrap">

		<div class="rm-header">
			<div class="rm-header-logo">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
			</div>
			<h1>Resend Mailer</h1>
		</div>

		<?php if ( $saved ) : ?>
		<div class="notice notice-success rm-notice is-dismissible"><p>Settings saved successfully.</p></div>
		<?php endif; ?>

		<nav class="rm-tabs">
			<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => RM_SLUG, 'tab' => $key ], admin_url( 'options-general.php' ) ) ); ?>"
			   class="rm-tab<?php echo $tab === $key ? ' is-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<form method="post" class="rm-form">
			<?php wp_nonce_field( 'rm_save', 'rm_nonce_field' ); ?>
			<input type="hidden" name="rm_saving_tab" value="<?php echo esc_attr( $tab ); ?>">

			<!-- ══════════════════════════════════════════════════
			     TAB: SENDING
			     ══════════════════════════════════════════════════ -->
			<?php if ( $tab === 'sending' ) : ?>

			<div class="rm-card">
				<h2 class="rm-card-title">API Connection</h2>
				<div class="rm-row">
					<label class="rm-label" for="rm_api_key">Resend API Key</label>
					<div class="rm-control">
						<div class="rm-input-wrap">
							<input type="password" id="rm_api_key" name="rm_api_key"
							       value="<?php echo esc_attr( $api_key ); ?>"
							       class="rm-input" autocomplete="new-password" placeholder="re_••••••••••••••••">
							<button type="button" class="rm-reveal-btn" aria-label="Show/hide key">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							</button>
						</div>
						<p class="rm-desc">Generate a key at <a href="https://resend.com/api-keys" target="_blank" rel="noopener">resend.com/api-keys</a>.</p>
					</div>
				</div>
			</div>

			<div class="rm-card">
				<h2 class="rm-card-title">Sender Details</h2>
				<div class="rm-row">
					<label class="rm-label" for="rm_from_name">From Name</label>
					<div class="rm-control">
						<input type="text" id="rm_from_name" name="rm_from_name"
						       value="<?php echo esc_attr( $from_name ); ?>" class="rm-input">
					</div>
				</div>
				<div class="rm-row">
					<label class="rm-label" for="rm_from_email">From Email</label>
					<div class="rm-control">
						<input type="email" id="rm_from_email" name="rm_from_email"
						       value="<?php echo esc_attr( $from_email ); ?>" class="rm-input">
						<p class="rm-desc">Must be from a <a href="https://resend.com/domains" target="_blank" rel="noopener">verified domain</a> in Resend.</p>
					</div>
				</div>
				<div class="rm-row">
					<label class="rm-label" for="rm_reply_to">Reply-To <span class="rm-optional">optional</span></label>
					<div class="rm-control">
						<input type="email" id="rm_reply_to" name="rm_reply_to"
						       value="<?php echo esc_attr( $reply_to ); ?>" class="rm-input"
						       placeholder="<?php echo esc_attr( $from_email ); ?>">
					</div>
				</div>
			</div>

			<div class="rm-card">
				<h2 class="rm-card-title">Routing</h2>
				<div class="rm-row rm-row--inline">
					<div>
						<span class="rm-label">Route all WordPress mail via Resend</span>
						<p class="rm-desc rm-desc--tight">When on, emails sent via <code>wp_mail()</code> — password resets, WooCommerce orders, contact forms — are all sent through Resend with your template applied.</p>
					</div>
					<label class="rm-toggle" for="rm_intercept_wp_mail">
						<input type="checkbox" id="rm_intercept_wp_mail" name="rm_intercept_wp_mail"
						       value="1" <?php checked( $intercept, '1' ); ?>>
						<span class="rm-track"><span class="rm-thumb"></span></span>
					</label>
				</div>
			</div>

			<div class="rm-card">
				<h2 class="rm-card-title">Test Connection</h2>
				<p class="rm-desc">Send a test email to <strong><?php echo esc_html( $admin_email ); ?></strong> using the current template and settings.</p>
				<div class="rm-test-row">
					<button type="button" id="rm-send-test" class="rm-btn rm-btn--secondary">
						Send test email
					</button>
					<span id="rm-test-result"></span>
				</div>
			</div>

			<!-- ══════════════════════════════════════════════════
			     TAB: DESIGN
			     ══════════════════════════════════════════════════ -->
			<?php elseif ( $tab === 'design' ) : ?>

			<div class="rm-card">
				<h2 class="rm-card-title">Colours</h2>

				<div class="rm-row">
					<label class="rm-label" for="rm_bg_color">Email Background</label>
					<div class="rm-control rm-control--color">
						<input type="text" id="rm_bg_color" name="rm_bg_color"
						       value="<?php echo esc_attr( $bg_color ); ?>"
						       class="rm-color-picker">
						<p class="rm-desc">The outer canvas behind the email card.</p>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label" for="rm_container_color">Card Background</label>
					<div class="rm-control rm-control--color">
						<input type="text" id="rm_container_color" name="rm_container_color"
						       value="<?php echo esc_attr( $container ); ?>"
						       class="rm-color-picker">
						<p class="rm-desc">The content card / container background.</p>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label" for="rm_text_color">Body Text</label>
					<div class="rm-control rm-control--color">
						<input type="text" id="rm_text_color" name="rm_text_color"
						       value="<?php echo esc_attr( $text_color ); ?>"
						       class="rm-color-picker">
					</div>
				</div>
			</div>

			<div class="rm-card">
				<h2 class="rm-card-title">Logo</h2>
				<div class="rm-row">
					<label class="rm-label">Image</label>
					<div class="rm-control">
						<input type="hidden" id="rm_logo_id" name="rm_logo_id"
						       value="<?php echo esc_attr( $logo_id ); ?>">

						<div id="rm-logo-preview" class="rm-logo-preview<?php echo $logo_url ? ' is-visible' : ''; ?>">
							<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="Current logo">
							<?php endif; ?>
						</div>

						<div class="rm-logo-actions">
							<button type="button" id="rm-upload-logo" class="rm-btn rm-btn--secondary">
								<?php echo $logo_url ? 'Change logo' : 'Upload logo'; ?>
							</button>
							<button type="button" id="rm-remove-logo"
							        class="rm-btn rm-btn--danger<?php echo $logo_url ? '' : ' is-hidden'; ?>">
								Remove
							</button>
						</div>
						<p class="rm-desc">SVG, PNG or WebP recommended.</p>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label" for="rm_logo_height">Logo Height</label>
					<div class="rm-control">
						<div class="rm-number-wrap">
							<input type="number" id="rm_logo_height" name="rm_logo_height"
							       value="<?php echo esc_attr( $logo_height ); ?>"
							       min="10" max="200" step="1" class="rm-input rm-input--short">
							<span class="rm-unit">px &nbsp;&nbsp;(width: auto)</span>
						</div>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label">Alignment</label>
					<div class="rm-control">
						<div class="rm-radio-group">
							<?php foreach ( [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] as $val => $lbl ) : ?>
							<label class="rm-radio-label">
								<input type="radio" name="rm_logo_align" value="<?php echo esc_attr( $val ); ?>"
								       <?php checked( $logo_align, $val ); ?>>
								<?php echo esc_html( $lbl ); ?>
							</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label">Position</label>
					<div class="rm-control">
						<div class="rm-radio-group rm-radio-group--stacked">
							<label class="rm-radio-label rm-radio-label--block">
								<input type="radio" name="rm_logo_position" value="inside"
								       <?php checked( $logo_position, 'inside' ); ?>>
								<span>
									<strong>Inside the card</strong>
									<span class="rm-radio-desc">Logo sits at the top of the email card, within the container background.</span>
								</span>
							</label>
							<label class="rm-radio-label rm-radio-label--block">
								<input type="radio" name="rm_logo_position" value="above"
								       <?php checked( $logo_position, 'above' ); ?>>
								<span>
									<strong>Above the card</strong>
									<span class="rm-radio-desc">Logo floats above the card against the email background, with padding above and below.</span>
								</span>
							</label>
						</div>
					</div>
				</div>
			</div>

			<div class="rm-card">
				<h2 class="rm-card-title">Typography</h2>
				<p class="rm-desc rm-desc--intro">Only web-safe fonts are offered — custom webfonts are not reliably supported across email clients.</p>

				<div class="rm-row">
					<label class="rm-label" for="rm_heading_font">Heading Font</label>
					<div class="rm-control">
						<select id="rm_heading_font" name="rm_heading_font" class="rm-select rm-font-select">
							<?php foreach ( RM_FONTS as $stack => $label ) : ?>
							<option value="<?php echo esc_attr( $stack ); ?>"
							        style="font-family:<?php echo esc_attr( $stack ); ?>;"
							        <?php selected( $heading_font, $stack ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<div class="rm-font-preview" id="rm-heading-preview"
						     style="font-family:<?php echo esc_attr( $heading_font ); ?>;">
							The quick brown fox
						</div>
					</div>
				</div>

				<div class="rm-row">
					<label class="rm-label" for="rm_body_font">Body Font</label>
					<div class="rm-control">
						<select id="rm_body_font" name="rm_body_font" class="rm-select rm-font-select">
							<?php foreach ( RM_FONTS as $stack => $label ) : ?>
							<option value="<?php echo esc_attr( $stack ); ?>"
							        style="font-family:<?php echo esc_attr( $stack ); ?>;"
							        <?php selected( $body_font, $stack ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<div class="rm-font-preview rm-font-preview--body" id="rm-body-preview"
						     style="font-family:<?php echo esc_attr( $body_font ); ?>;">
							The quick brown fox jumps over the lazy dog. 0123456789.
						</div>
					</div>
				</div>
			</div>

			<!-- ══════════════════════════════════════════════════
			     TAB: TEMPLATE
			     ══════════════════════════════════════════════════ -->
			<?php elseif ( $tab === 'template' ) : ?>

			<div class="rm-card">
				<h2 class="rm-card-title">Email Template</h2>
				<p class="rm-desc rm-desc--intro">
					Edit the raw HTML template used for all outgoing emails.
					Colour, font, and logo variables are injected from your Design settings when emails are sent.
				</p>

				<div class="rm-vars-grid">
					<?php
					$vars = [
						'{{subject}}'         => 'Email subject line',
						'{{body}}'            => 'Email body content (required)',
						'{{logo_block}}'      => 'Logo &lt;tr&gt; row (auto-generated)',
						'{{bg_color}}'        => 'Email background colour',
						'{{container_color}}' => 'Card background colour',
						'{{text_color}}'      => 'Body text colour',
						'{{heading_font}}'    => 'Heading font stack',
						'{{body_font}}'       => 'Body font stack',
						'{{site_name}}'       => 'WordPress site name',
						'{{site_url}}'        => 'WordPress site URL',
						'{{year}}'            => 'Current year',
					];
					foreach ( $vars as $var => $desc ) : ?>
					<div class="rm-var-row">
						<code class="rm-var-code"><?php echo esc_html( $var ); ?></code>
						<span class="rm-var-desc"><?php echo wp_kses_post( $desc ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>

				<textarea id="rm_template" name="rm_template" rows="36" class="rm-template-editor" spellcheck="false"><?php
					echo esc_textarea( $template );
				?></textarea>

				<div class="rm-template-actions">
					<button type="button" id="rm-reset-template" class="rm-btn rm-btn--ghost">
						Reset to default
					</button>
					<span id="rm-reset-result"></span>
				</div>
			</div>

			<?php endif; ?>

			<div class="rm-footer-bar">
				<button type="submit" class="rm-btn rm-btn--primary">Save settings</button>
				<a href="#" id="rm-preview-email" class="rm-btn rm-btn--secondary" target="_blank" rel="noopener">
					Preview email
				</a>
			</div>

		</form>
	</div>
	<?php
}

/* ── AJAX: test email ─────────────────────────────────────────────── */

function rm_ajax_send_test() {
	check_ajax_referer( 'rm_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$to      = (string) get_option( 'admin_email' );
	$subject = 'Resend Mailer — Test Email';
	$body    = '<h2 style="margin:0 0 16px;font-family:' . esc_attr( rm_opt( 'heading_font', 'Georgia, "Times New Roman", serif' ) ) . ';font-size:24px;font-weight:600;color:' . esc_attr( rm_opt( 'text_color', '#1a1a1a' ) ) . ';">Test email ✓</h2>'
	         . '<p style="margin:0 0 12px;">This is a test from <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>. Your API key is valid and your template is rendering correctly.</p>'
	         . '<p style="margin:0;color:#6b7280;font-size:14px;">Sent via Resend Mailer &nbsp;·&nbsp; ' . esc_html( gmdate( 'Y-m-d H:i:s' ) ) . ' UTC</p>';

	$html   = rm_render_template( $body, $subject );
	$name   = rm_opt( 'from_name',  get_bloginfo( 'name' ) );
	$email  = rm_opt( 'from_email', (string) get_option( 'admin_email' ) );
	$from   = $name ? "{$name} <{$email}>" : $email;
	$result = rm_send_via_resend( $to, $subject, $html, $from );

	if ( $result['success'] ) {
		wp_send_json_success( 'Sent to ' . $to );
	} else {
		wp_send_json_error( $result['error'] );
	}
}

/* ── AJAX: reset template ─────────────────────────────────────────── */

function rm_ajax_reset_template() {
	check_ajax_referer( 'rm_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}
	wp_send_json_success( rm_default_template() );
}

/* ── AJAX: preview email in browser ──────────────────────────────── */

function rm_ajax_preview_email() {
	check_ajax_referer( 'rm_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized', 403 );
	}

	$heading_font = rm_opt( 'heading_font', 'Georgia, "Times New Roman", serif' );
	$text_color   = rm_opt( 'text_color', '#1a1a1a' );

	$body = '<h2 style="margin:0 0 16px;font-family:' . esc_attr( $heading_font ) . ';font-size:24px;font-weight:600;color:' . esc_attr( $text_color ) . ';">Preview email ✓</h2>'
	      . '<p style="margin:0 0 12px;">This is a live preview of your email template using your current Design settings. All variables — colours, fonts, and logo — are rendered exactly as they will appear in sent emails.</p>'
	      . '<p style="margin:0;color:#6b7280;font-size:14px;">Generated by Resend Mailer &nbsp;·&nbsp; ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';

	$html = rm_render_template( $body, 'Email Preview — ' . get_bloginfo( 'name' ) );

	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full HTML email output
	echo $html;
	exit;
}
