<?php
/**
 * Plugin Name: Mailster WordPress Newsletter Plugin
 * Plugin URI: https://mailster.co/?utm_campaign=wporg&utm_source=wordpress.org&utm_medium=plugin&utm_term=Mailster+Compatibility+Tester
 * Description: This is a compatibility test plugin for the Mailster Newsletter plugin
 * Version: 2.0.3
 * Author: EverPress
 * Author URI: https://mailster.co
 * License: GPLv2 or later
 * Text Domain: mailster
 *
 * @package mailster
 */

// Only load in the WordPress admin.
if ( ! is_admin() || ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Absolute path to the main plugin file.
 *
 * @since 1.0.1
 *
 * @var string
 */
define( 'MAILSTER_TESTER_FILE', __FILE__ );

/**
 * Plugin version string.
 *
 * @since 2.0.2
 *
 * @var string
 */
define( 'MAILSTER_TESTER_VERSION', '2.0.3' );

require_once __DIR__ . '/classes/tester.class.php';
require_once __DIR__ . '/classes/activator.class.php';
new MailsterTester();
new MailsterActivator();
