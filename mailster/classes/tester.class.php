<?php
/**
 * Tester class file.
 *
 * @package mailster
 */

/**
 * Boots the Mailster compatibility tester admin UI and AJAX runners.
 *
 * @since 1.0
 */
class MailsterTester {

	/**
	 * Absolute filesystem path to the plugin directory.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Public URL to the plugin directory.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Registers hooks and loads the plugin text domain.
	 *
	 * @since 1.0
	 */
	public function __construct() {

		$this->plugin_path = plugin_dir_path( MAILSTER_TESTER_FILE );
		$this->plugin_url  = plugin_dir_url( MAILSTER_TESTER_FILE );

		load_plugin_textdomain( 'mailster' );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_mailster_test', array( $this, 'ajax' ) );
		add_filter( 'upgrader_package_options', array( &$this, 'upgrader_package_options' ) );
		add_action( 'activated_plugin', array( $this, 'on_activate' ) );
	}


	/**
	 * Runs a single compatibility test via admin-ajax and returns JSON.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function ajax() {

		// Verify nonce.
		if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'mailster_tester' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce failed!' ) );
		}

		require_once $this->plugin_path . '/classes/tests.class.php';

		$test = new MailsterTests();

		$test_id = isset( $_POST['test_id'] ) ? sanitize_text_field( wp_unslash( $_POST['test_id'] ) ) : null;

		$success            = $test->run( $test_id );
		$return['message']  = $test->get_message();
		$return['nexttest'] = $test->get_next();
		$return['next']     = $test->nicename( $return['nexttest'] );
		$return['total']    = $test->get_total();
		$return['errors']   = $test->get_error_counts();
		$return['current']  = $test->get_current();
		$return['type']     = $test->get_current_type();

		if ( ! $success ) {
			wp_send_json_error( $return );
		}
		wp_send_json_success( $return );
	}


	/**
	 * Runs a named test or returns the tests runner instance.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $test Optional. Test identifier to run. Default null returns the runner.
	 * @return MailsterTests|array Runner instance when `$test` is null; otherwise the last message payload.
	 */
	public function test( $test = null ) {

		require_once $this->plugin_path . '/classes/tests.class.php';

		$testobj = new MailsterTests();
		if ( is_null( $test ) ) {
			return $testobj;
		}
		$testobj->run( $test );
		return $testobj->get();
	}


	/**
	 * Enqueues tester assets and localizes Freemius checkout arguments.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {

		wp_enqueue_style( 'mailster-tester', $this->plugin_url . '/assets/style.css', array(), MAILSTER_TESTER_VERSION );
		wp_enqueue_script( 'mailster-tester', $this->plugin_url . '/assets/script.js', array( 'jquery' ), MAILSTER_TESTER_VERSION, true );

		$user = wp_get_current_user();

		wp_localize_script(
			'mailster-tester',
			'mailster_tester',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mailster_tester' ),
				'fs'      => apply_filters(
					'mailster_freemius_args',
					array(
						'plugin_id'   => 12184,
						'plan_id'     => 22867,
						'public_key'  => 'pk_1efa30140fc34f21e5b89959bb877',
						'dl_endpoint' => 'https://mailster.co',
						'coupon'      => 'WPTESTER',
						'user'        => array(
							'email' => $user->user_email,
							'first' => $user->user_firstname ? $user->user_firstname : $user->display_name,
							'last'  => $user->user_lastname,
						),
					),
				),
			)
		);
	}


	/**
	 * Registers the Mailster Tester top-level admin menu page.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function admin_menu() {

		$page = add_menu_page( __( 'Mailster Tester', 'mailster' ), __( 'Mailster Tester', 'mailster' ), 'read', 'mailster-tester', array( $this, 'admin_page' ), 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2OTIuOCA2MTEuOSIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PHBhdGggZD0iTTQ3MS4xIDI0LjMgMzQ2LjQgMTc2LjcgMjIxLjcgMjQuM0gwdjU2OC4xaDE5NFYyNzMuN2wxNTIuNCAyMDcuOCAxNTIuNC0yMDcuOHYzMTguNmgxOTR2LTU2OEg0NzEuMXoiIGZpbGw9IiMyYmIyZTgiLz48L3N2Zz4=', 25 );

		add_action( 'load-' . $page, array( &$this, 'admin_enqueue_scripts' ) );
	}


	/**
	 * Renders the Mailster Tester admin screen.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function admin_page() {

		include $this->plugin_path . '/views/testpage.php';
	}


	/**
	 * Adjusts package upgrade options when installing a local Mailster zip.
	 *
	 * @since 2.0.2
	 *
	 * @param array $options Upgrader package options.
	 * @return array Filtered upgrader package options.
	 */
	public function upgrader_package_options( $options ) {

		// Check if we are installing Mailster and skip abortion if the destination exists. Check if package is a URL which indicates it's loaded from the repository.
		if ( isset( $options['package'] ) && preg_match( '/^mailster/', basename( $options['package'] ) ) && ! filter_var( $options['package'], FILTER_VALIDATE_URL ) ) {
			$options['clear_destination']           = true;
			$options['abort_if_destination_exists'] = false;
			add_filter( 'upgrader_post_install', array( &$this, 'upgrader_post_install' ) );
		}

		return $options;
	}

	/**
	 * Deactivates this tester plugin after a local Mailster package install.
	 *
	 * @since 2.0.2
	 *
	 * @param bool|WP_Error $res Installation result from the upgrader.
	 * @return bool|WP_Error Unchanged installation result.
	 */
	public function upgrader_post_install( $res ) {

		echo '<p>' . esc_html__( 'Deactivating Mailster Tester Plugin...', 'mailster' ) . '</p>';
		deactivate_plugins( MAILSTER_TESTER_FILE );
		echo '<p>' . esc_html__( 'Installing Mailster...', 'mailster' ) . '</p>';

		return $res;
	}

	/**
	 * Redirects to the tester screen when this plugin is activated.
	 *
	 * @since 2.0.2
	 *
	 * @param string $plugin Plugin basename that was activated.
	 * @return void
	 */
	public function on_activate( $plugin ) {

		if ( plugin_basename( MAILSTER_TESTER_FILE ) !== $plugin ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mailster-tester' ) );
		exit();
	}
}
