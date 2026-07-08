<?php
/**
 * Plugin Name:       Numeris Remailer
 * Description:       Send WordPress emails via the Resend API with a fully customisable HTML template — colours, fonts, logo and more.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Numeris Digital
 * License:           GPL-2.0+
 * Text Domain:       resend-mailer
 */

defined( 'ABSPATH' ) || exit;

define( 'RM_VERSION', '1.1.1' );
define( 'RM_FILE',    __FILE__ );
define( 'RM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'RM_URL',     plugin_dir_url( __FILE__ ) );
define( 'RM_SLUG',    'resend-mailer' );

/** Web-safe fonts supported by all major email clients. */
define( 'RM_FONTS', [
    'Arial, Helvetica, sans-serif'          => 'Arial',
    'Verdana, Geneva, sans-serif'           => 'Verdana',
    'Tahoma, Geneva, sans-serif'            => 'Tahoma',
    '"Trebuchet MS", Helvetica, sans-serif' => 'Trebuchet MS',
    'Georgia, "Times New Roman", serif'     => 'Georgia',
    '"Times New Roman", Times, serif'       => 'Times New Roman',
    '"Courier New", Courier, monospace'     => 'Courier New',
] );

require RM_DIR . 'includes/functions.php';
require RM_DIR . 'includes/template.php';
require RM_DIR . 'includes/sender.php';
require RM_DIR . 'includes/admin.php';
require RM_DIR . 'includes/github-updater.php';
