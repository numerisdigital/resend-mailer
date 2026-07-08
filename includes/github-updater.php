<?php
defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted updates via GitHub Releases, since this plugin isn't (and
 * won't be) distributed through wordpress.org. Hooking into the same
 * update_plugins transient and plugins_api filter that WordPress core uses
 * for its own updates means the "update available" notice, the one-click
 * "Update now" link, and any external tool that reads those same standard
 * transients (WP Umbrella, ManageWP, MainWP, etc.) all pick this up
 * automatically — nothing tool-specific to build.
 */

define( 'RM_GITHUB_REPO', 'numerisdigital/resend-mailer' );

add_filter( 'pre_set_site_transient_update_plugins', 'rm_github_check_for_update' );
add_filter( 'plugins_api', 'rm_github_plugin_info', 20, 3 );
add_filter( 'upgrader_source_selection', 'rm_github_fix_source_folder_name', 10, 4 );
add_action( 'upgrader_process_complete', 'rm_github_purge_cache_after_update', 10, 2 );
add_action( 'delete_site_transient_update_plugins', 'rm_github_purge_cache' );

/**
 * The installed folder name, read at runtime rather than assumed — this
 * plugin isn't guaranteed to always be installed under the literal
 * "resend-mailer" folder (e.g. a GitHub "Download ZIP" extracts under a
 * different name), and the folder WordPress needs the update package
 * renamed to must match whatever is actually on disk on that site.
 */
function rm_github_plugin_slug() {
	return dirname( plugin_basename( RM_FILE ) );
}

/**
 * Latest GitHub release, cached in a transient to stay well within
 * GitHub's unauthenticated API rate limit (60 requests/hour per IP — fine
 * now the repo is public, but still worth not hitting on every page load).
 */
function rm_github_get_latest_release() {
	$cache_key = 'rm_github_update_check';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get( 'https://api.github.com/repos/' . RM_GITHUB_REPO . '/releases/latest', array(
		'headers' => array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'ResendMailer-Updater',
		),
		'timeout' => 10,
	) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Short backoff before retrying, so a GitHub outage or rate limit
		// doesn't turn into a request on every single page load.
		set_transient( $cache_key, array(), 15 * MINUTE_IN_SECONDS );
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		set_transient( $cache_key, array(), 15 * MINUTE_IN_SECONDS );
		return array();
	}

	set_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
	return $data;
}

/**
 * Injects an update-available entry into the same transient WordPress core
 * populates from wordpress.org, if the latest GitHub release is newer than
 * what's installed.
 */
function rm_github_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$release = rm_github_get_latest_release();
	if ( empty( $release['tag_name'] ) ) {
		return $transient;
	}

	$remote_version = ltrim( $release['tag_name'], 'v' );
	$plugin_file    = plugin_basename( RM_FILE );
	$installed      = $transient->checked[ $plugin_file ] ?? RM_VERSION;

	if ( version_compare( $remote_version, $installed, '>' ) ) {
		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => rm_github_plugin_slug(),
			'plugin'      => $plugin_file,
			'new_version' => $remote_version,
			'url'         => 'https://github.com/' . RM_GITHUB_REPO,
			'package'     => $release['zipball_url'],
			'tested'      => get_bloginfo( 'version' ),
		);
		unset( $transient->no_update[ $plugin_file ] );
	} else {
		unset( $transient->response[ $plugin_file ] );
	}

	return $transient;
}

/**
 * Populates the "View details" popup on the Plugins page with the GitHub
 * release's own description/changelog.
 */
function rm_github_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || rm_github_plugin_slug() !== $args->slug ) {
		return $result;
	}

	$release = rm_github_get_latest_release();
	if ( empty( $release['tag_name'] ) ) {
		return $result;
	}

	return (object) array(
		'name'          => 'Numeris Remailer',
		'slug'          => rm_github_plugin_slug(),
		'version'       => ltrim( $release['tag_name'], 'v' ),
		'author'        => '<a href="https://numeris.digital">Numeris Digital</a>',
		'homepage'      => 'https://github.com/' . RM_GITHUB_REPO,
		'sections'      => array(
			'description' => 'Send WordPress emails via the Resend API with a fully customisable HTML template.',
			'changelog'   => wpautop( wp_kses_post( $release['body'] ?? 'No changelog provided.' ) ),
		),
		'download_link' => $release['zipball_url'],
	);
}

/**
 * GitHub's auto-generated release zip extracts to a folder named after the
 * repo/commit (e.g. numerisdigital-resend-mailer-a1b2c3d), not this site's
 * actual plugin folder — WordPress needs the extracted folder renamed to
 * match, or the update installs alongside the existing copy instead of
 * replacing it.
 */
function rm_github_fix_source_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	if ( empty( $hook_extra['plugin'] ) || plugin_basename( RM_FILE ) !== $hook_extra['plugin'] ) {
		return $source;
	}

	global $wp_filesystem;

	$target = trailingslashit( $remote_source ) . rm_github_plugin_slug() . '/';
	if ( trailingslashit( $source ) === $target ) {
		return $source;
	}

	if ( $wp_filesystem->move( $source, $target ) ) {
		return $target;
	}

	return $source;
}

/**
 * Drops the cached release data once any plugin update actually runs
 * (cheap to check regardless of which plugin), so a fresh release is
 * reflected immediately rather than waiting out the cache window.
 */
function rm_github_purge_cache_after_update( $upgrader, $options ) {
	if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
		delete_transient( 'rm_github_update_check' );
	}
}

/**
 * Drops the cached release data whenever WordPress's own update_plugins
 * transient is deleted — e.g. an admin clicking "Check Again" on the
 * Updates page. WordPress passes the transient name itself as the first
 * argument to delete_site_transient_{$transient} (never null), so this
 * can't share a signature with rm_github_purge_cache_after_update() above
 * — a shared function that checked for a null first argument would never
 * actually run when called from here, silently leaving the 6-hour GitHub
 * response cache in place no matter how many times "Check Again" is
 * clicked.
 */
function rm_github_purge_cache() {
	delete_transient( 'rm_github_update_check' );
}
