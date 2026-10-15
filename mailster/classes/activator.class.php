<?php
/**
 * Activator class file.
 *
 * @package mailster
 */

/**
 * Watches plugin activations for Mailster-related plugins.
 *
 * @since 2.0.1
 */
class MailsterActivator {


	/**
	 * Hooks the activator into WordPress plugin activation.
	 *
	 * @since 2.0.1
	 */
	public function __construct() {

		add_action( 'activated_plugin', array( $this, 'check' ), 10, 2 );
	}


	/**
	 * Handles activation of plugins whose basename starts with mailster.
	 *
	 * @since 2.0.1
	 *
	 * @param string $plugin       Plugin basename that was activated.
	 * @param bool   $network_wide Whether the plugin was network-activated.
	 * @return void
	 */
	public function check( $plugin, $network_wide ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Matches activated_plugin signature.

		if ( ! preg_match( '/^mailster/', $plugin ) ) {
			return;
		}

		// TODO: notify the user that the Mailster plugin is required.
	}
}
