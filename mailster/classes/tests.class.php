<?php
/**
 * Tests class file.
 *
 * @package mailster
 */

/**
 * Runs Mailster environment compatibility checks and collects results.
 *
 * @since 2.0.0
 */
class MailsterTests {

	/**
	 * Reserved message buffer (unused by the current runner).
	 *
	 * @since 2.0.0
	 *
	 * @var mixed
	 */
	private $message;

	/**
	 * Map of pending test identifiers to run.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $tests;

	/**
	 * Current test callable or method name.
	 *
	 * @since 2.0.0
	 *
	 * @var mixed
	 */
	private $current;

	/**
	 * Next test identifier placeholder.
	 *
	 * @since 2.0.0
	 *
	 * @var mixed
	 */
	private $next;

	/**
	 * Total number of tests in the current run.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Whether the last recorded result was a non-success failure.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	private $last_is_error;

	/**
	 * Test identifier that produced the last failure entry.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	private $last_error_test;

	/**
	 * Message from the last failure entry.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	private $last_error_message;

	/**
	 * Severity type of the last failure entry.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private $last_error_type;

	/**
	 * Optional help link from the last failure entry.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	private $last_error_link;

	/**
	 * Identifier of the test currently executing.
	 *
	 * @since 2.0.0
	 *
	 * @var string|null
	 */
	private $current_id;

	/**
	 * Aggregated error, warning, notice, and success counts and messages.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Builds the test list and initializes result counters.
	 *
	 * @since 2.0.0
	 *
	 * @param string|array|null $test Optional. Single test ID, list of IDs, or null for all tests.
	 */
	public function __construct( $test = null ) {

		if ( ! is_null( $test ) ) {
			$this->tests = is_array( $test ) ? array_keys( $test ) : array( $test => 0 );
		} else {
			$this->tests = $this->get_tests();
		}
		$this->total  = count( $this->tests );
		$this->errors = array(
			'count'         => 0,
			'error_count'   => 0,
			'warning_count' => 0,
			'notice_count'  => 0,
			'success_count' => 0,
			'all'           => array(),
			'error'         => array(),
			'warning'       => array(),
			'notice'        => array(),
			'success'       => array(),
		);
	}

	/**
	 * Proxies severity helpers and runs tests by short method name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method Method name being called.
	 * @param array  $args   Arguments passed to the method.
	 * @return bool|null True when a dynamic test passed, false on failure, null otherwise.
	 */
	public function __call( $method, $args ) {

		switch ( $method ) {
			case 'error':
			case 'warning':
			case 'notice':
			case 'success':
				call_user_func_array( array( &$this, $method ), $args );
				break;
		}

		if ( method_exists( $this, 'test_' . $method ) ) {
			$this->run( $method );
			return ! $this->last_is_error;
		}
	}

	/**
	 * Executes a single test by identifier and records its outcome.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $test_id Optional. Test identifier. Default first pending test.
	 * @param array       $args    Optional. Arguments forwarded to the test method.
	 * @return bool|null True on success, false when the last result failed, null if unknown.
	 */
	public function run( $test_id = null, $args = array() ) {

		if ( null === $test_id ) {
			$test_id = key( $this->tests );
		}

		if ( isset( $this->tests[ $test_id ] ) ) {

			$this->last_is_error      = false;
			$this->last_error_test    = null;
			$this->last_error_message = null;
			$this->last_error_type    = 'success';
			$this->last_error_link    = null;

			$this->current_id = $test_id;
			$this->current    = $this->tests[ $test_id ];

			try {
				if ( is_callable( $this->current ) ) {
					call_user_func_array( $this->current, array( $this ) );
				} elseif ( method_exists( $this, 'test_' . $test_id ) ) {
					call_user_func_array( array( &$this, 'test_' . $test_id ), $args );
				} else {
					$this->warning( 'Test \'' . $test_id . '\' does not exist!' );
				}
			} catch ( Exception $e ) {
				$this->error( $e );
			}
			return ! ( $this->last_error_test === $test_id );

		}

		return null;
	}

	/**
	 * Discovers test_* methods and returns a filtered identifier map.
	 *
	 * @since 2.0.0
	 *
	 * @return array Test identifiers keyed for the runner.
	 */
	public function get_tests() {

		$tests = get_class_methods( $this );
		$tests = preg_grep( '/^test_/', $tests );
		$tests = array_values( $tests );
		$tests = preg_replace( '/^test_/', '', $tests );
		$tests = array_flip( $tests );

		return apply_filters( 'mailster_tests', $tests );
	}

	/**
	 * Builds HTML and plain-text summaries of recorded test results.
	 *
	 * @since 2.0.0
	 *
	 * @return array {
	 *     @type string $test Current test identifier.
	 *     @type string $time Timestamp when the message was built.
	 *     @type string $html HTML result markup.
	 *     @type string $text Plain-text non-success summary.
	 * }
	 */
	public function get_message() {

		$time   = gmdate( 'Y-m-d H:i:s' );
		$html   = '';
		$text   = '';
		$maxlen = max( array_map( 'strlen', array_keys( $this->get_tests() ) ) );

		foreach ( array( 'error', 'warning', 'notice', 'success' ) as $type ) {
			if ( ! $this->errors[ $type . '_count' ] ) {
				continue;
			}
			foreach ( $this->errors[ $type ] as $test_id => $test_errors ) {

				foreach ( $test_errors as $i => $error ) {
					$name = $this->nicename( $test_id );
					$more = '';
					if ( $error['data']['link'] ) {
						$more = ( false !== filter_var( $error['data']['link'], FILTER_VALIDATE_URL ) ) ? ' (<a class="mailster-test-result-link external" href="' . esc_url( $error['data']['link'] ) . '">' . esc_html__( 'More Info', 'mailster' ) . '</a>)' : $this->beacon( $error['data']['link'] );
					}
					$html .= '<div class="mailster-test-result mailster-test-is-' . $type . '"><h4>' . $name . $more . '</h4><div class="mailster-test-result-more">' . nl2br( $error['msg'] ) . '</div></div>';
					if ( 'success' !== $type ) {
						$text .= '[' . $type . '] ' . $test_id . ': ' . wp_strip_all_tags( $error['msg'] ) . "\n";
					}
				}
			}
		}

		return array(
			'test' => $this->current_id,
			'time' => $time,
			'html' => $html,
			'text' => $text,
		);
	}

	/**
	 * Converts a test identifier into a human-readable label.
	 *
	 * @since 2.0.0
	 *
	 * @param string $test Test identifier.
	 * @return string Nicely formatted test name.
	 */
	public function nicename( $test ) {
		if ( empty( $test ) ) {
			return $test;
		}
		$test = ucwords( str_replace( array( 'test_', '_' ), array( '', ' ' ), $test ) );
		$test = str_replace( array( 'Php', 'Wordpress', 'Wp ', 'Db', 'Mymail', 'Wpmail' ), array( 'PHP', 'WordPress', 'WP ', 'DB ', 'MyMail', 'wpmail()' ), $test );
		return $test;
	}

	/**
	 * Advances the queue and returns the next pending test identifier.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null Next test identifier, or null when finished.
	 */
	public function get_next() {

		foreach ( $this->tests as $key => $value ) {
			unset( $this->tests[ $key ] );

			if ( $key === $this->current_id ) {
				break;
			}
		}
		$next = key( $this->tests );
		return $next;
	}

	/**
	 * Returns the human-readable name of the current test.
	 *
	 * @since 2.0.0
	 *
	 * @return string Current test label.
	 */
	public function get_current() {
		return $this->nicename( $this->current_id );
	}

	/**
	 * Returns the severity type of the most recent result.
	 *
	 * @since 2.0.0
	 *
	 * @return string Result type such as error, warning, notice, or success.
	 */
	public function get_current_type() {
		return $this->last_error_type;
	}

	/**
	 * Returns the total number of tests in this run.
	 *
	 * @since 2.0.0
	 *
	 * @return int Total test count.
	 */
	public function get_total() {

		return $this->total;
	}

	/**
	 * Returns per-severity counts for the current run.
	 *
	 * @since 2.0.0
	 *
	 * @return array {
	 *     @type int $error   Error count.
	 *     @type int $warning Warning count.
	 *     @type int $notice  Notice count.
	 *     @type int $success Success count.
	 * }
	 */
	public function get_error_counts() {

		return array(
			'error'   => $this->errors['error_count'],
			'warning' => $this->errors['warning_count'],
			'notice'  => $this->errors['notice_count'],
			'success' => $this->errors['success_count'],
		);
	}



	/**
	 * Records an error-level result for the current test.
	 *
	 * @since 2.0.0
	 *
	 * @param string|Exception $msg  Error message or exception.
	 * @param string|null      $link Optional. Help URL or beacon ID.
	 * @return void
	 */
	private function error( $msg, $link = null ) {

		$this->failure( 'error', $msg, $link );
	}


	/**
	 * Records a warning-level result for the current test.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $msg  Warning message.
	 * @param string|null $link Optional. Help URL or beacon ID.
	 * @return void
	 */
	private function warning( $msg, $link = null ) {

		$this->failure( 'warning', $msg, $link );
	}


	/**
	 * Records a notice-level result for the current test.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $msg  Notice message.
	 * @param string|null $link Optional. Help URL or beacon ID.
	 * @return void
	 */
	private function notice( $msg, $link = null ) {

		$this->failure( 'notice', $msg, $link );
	}

	/**
	 * Records a success-level result for the current test.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $msg  Success message.
	 * @param string|null $link Optional. Help URL or beacon ID.
	 * @return void
	 */
	private function success( $msg, $link = null ) {

		$this->failure( 'success', $msg, $link );
	}


	/**
	 * Stores a typed result message against the current test identifier.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $type Result type: error, warning, notice, or success.
	 * @param mixed       $msg  Message content.
	 * @param string|null $link Optional. Help URL or beacon ID.
	 * @return void
	 */
	private function failure( $type, $msg, $link = null ) {

		$test_id = $this->current_id;

		if ( is_null( $test_id ) ) {
			$test_id = uniqid();
		}

		$data = array( 'link' => $link );
		if ( ! isset( $this->errors['all'][ $test_id ] ) ) {
			$this->errors['all'][ $test_id ] = array();
		}
		$this->errors['all'][ $test_id ][] = array(
			'msg'  => $msg,
			'data' => $data,
		);
		if ( ! isset( $this->errors[ $type ][ $test_id ] ) ) {
			$this->errors[ $type ][ $test_id ] = array();
		}
		$this->errors[ $type ][ $test_id ][] = array(
			'msg'  => $msg,
			'data' => $data,
		);
		++$this->errors['count'];
		++$this->errors[ $type . '_count' ];

		$this->last_is_error      = ( 'success' !== $type );
		$this->last_error_type    = $type;
		$this->last_error_test    = $test_id;
		$this->last_error_message = $msg;
		$this->last_error_link    = $link;
	}

	/**
	 * Returns all recorded messages, or true when none were recorded.
	 *
	 * @since 2.0.0
	 *
	 * @return array|true Message map keyed by test ID, or true when empty.
	 */
	public function get() {
		return $this->errors['count'] ? $this->errors['all'] : true;
	}

	/**
	 * Returns how many results were recorded overall or for a severity.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $type Optional. Severity key, or null for total count.
	 * @return int|false Count for the requested type, or false when unknown.
	 */
	public function has( $type = null ) {
		if ( is_null( $type ) ) {
			return $this->errors['count'];
		} elseif ( isset( $this->errors[ $type ] ) ) {
			return $this->errors[ $type . '_count' ];
		}

		return false;
	}





	/**
	 * Internal fixture that records a sample error.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore -- Intentionally disabled sample fixtures.

	/**
	 * Internal fixture that records a sample error.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function _test_error() {
		$this->error( 'This is an error error' );
	}

	/**
	 * Internal fixture that records a sample notice.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function _test_notice() {
		$this->notice( 'This is a notice error' );
	}

	/**
	 * Internal fixture that records a sample warning.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function _test_warning() {
		$this->warning( 'This is a warning error' );
	}

	/**
	 * Internal fixture that records a sample success.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function _test_success() {
		$this->success( 'This is a success error' );
	}

	/**
	 * Internal fixture that records one result of each severity.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function _test_multiple() {
		$this->error( 'This is a error error' );
		$this->notice( 'This is a notice error' );
		$this->warning( 'This is a warning error' );
		$this->success( 'This is a success error' );
	}

	// phpcs:enable PSR2.Methods.MethodDeclaration.Underscore



	/**
	 * Checks that the PHP version meets Mailster requirements.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_php_version() {
		if ( version_compare( PHP_VERSION, '7.2.5' ) < 0 ) {
			$this->error( sprintf( 'Mailster requires PHP version 7.2.5 or higher. Your current version is %s. Please update or ask your hosting provider to help you updating.', PHP_VERSION ) );
		} elseif ( version_compare( PHP_VERSION, '7.4' ) < 0 ) {
			$this->notice( sprintf( 'Mailster recommends PHP version 7.4 or higher. Your current version is %s. Please update or ask your hosting provider to help you updating.', PHP_VERSION ) );
		} else {
			$this->success( 'You have version ' . PHP_VERSION );
		}
	}

	/**
	 * Checks that WordPress is recent enough and preferably up to date.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_wordpress_version() {
		$update  = get_preferred_from_update_core();
		$current = get_bloginfo( 'version' );
		if ( version_compare( $current, '4.6' ) < 0 ) {
			$this->error( sprintf( 'Mailster requires WordPress version 4.6 or higher. Your current version is %s.', $current ) );
		} elseif ( $update && 'upgrade' === $update->response && version_compare( $update->current, $current ) ) {
			$this->warning( sprintf( 'Your WordPress site is not up-to-date! Version %1$s is available. Your current version is %2$s.', $update->current, $current ) );
		} else {
			$this->success( 'You have version ' . $current );

		}
	}

	/**
	 * Checks that the DOMDocument extension is available.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_dom_document_extension() {
		if ( ! class_exists( 'DOMDocument' ) ) {
			$this->error( 'Mailster requires the <a href="https://php.net/manual/en/class.domdocument.php" target="_blank" rel="noopener">DOMDocument</a> library.' );
		} else {
			$this->success( 'Your server supports <a href="https://php.net/manual/en/class.domdocument.php" target="_blank" rel="noopener">DOMDocument</a>.' );
		}
	}

	/**
	 * Checks that fsockopen is available for outbound socket tests.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_fsockopen_extension() {
		if ( ! function_exists( 'fsockopen' ) ) {
			$this->warning( 'Your server does not support <a href="https://php.net/manual/en/function.fsockopen.php" target="_blank" rel="noopener">fsockopen</a>.' );
		} else {
			$this->success( 'Your server supports <a href="https://php.net/manual/en/function.fsockopen.php" target="_blank" rel="noopener">fsockopen</a>.' );
		}
	}

	/**
	 * Checks that the effective PHP memory limit is at least 128 MB.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_memory_limit() {
		$max = max( (int) ini_get( 'memory_limit' ), (int) WP_MAX_MEMORY_LIMIT, (int) WP_MEMORY_LIMIT );
		if ( $max < 128 ) {
			$this->warning( 'Your Memory Limit is ' . size_format( $max * 1048576 ) . ', Mailster recommends at least 128 MB' );
		} else {
			$this->success( 'Your Memory Limit is ' . size_format( $max * 1048576 ) );
		}
	}

	/**
	 * Sends a wp_mail() test message to the current admin user.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_wpmail() {

		$args = $this->get_test_mail_data();

		$to      = $args['to'];
		$subject = $args['subject'];
		$message = $args['message'];

		add_action( 'wp_mail_failed', array( $this, 'wp_mail_failed' ) );
		$response = wp_mail( $to, '[wp_mail] ' . $subject, $message );
		if ( $response ) {
			$this->success( '[wp_mail] Email was successfully delivery to ' . $to );
		}
		remove_action( 'wp_mail_failed', array( $this, 'wp_mail_failed' ) );
	}


	/**
	 * Checks whether TCP port 993 is reachable on Gmail SMTP.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_port_993() {

		$this->port_test( 993, 'smtp.gmail.com' );
	}

	/**
	 * Checks whether TCP port 25 is reachable on Gmail SMTP.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_port_25() {

		$this->port_test( 25, 'smtp.gmail.com' );
	}

	/**
	 * Checks whether TCP port 2525 is reachable on SparkPost SMTP.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_port_2525() {

		$this->port_test( 2525, 'smtp.sparkpostmail.com' );
	}

	/**
	 * Checks whether TCP port 465 is reachable on Gmail SMTP.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_port_465() {

		$this->port_test( 465, 'smtp.gmail.com' );
	}

	/**
	 * Checks whether TCP port 587 is reachable on Gmail SMTP.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_port_587() {

		$this->port_test( 587, 'smtp.gmail.com' );
	}


	/**
	 * Marks the end of the automated test suite.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function test_finished() {

		$this->success( 'All tests have finished!' );
	}

	/**
	 * Records a wp_mail() failure with a contextual Mailster help link.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Error $error Failure details from wp_mail_failed.
	 * @return void
	 */
	public function wp_mail_failed( $error ) {
		$error_message = wp_strip_all_tags( $error->get_error_message() );
		$msg           = 'You are not able to use <code>wp_mail()</code> with Mailster';

		if ( false !== stripos( $error_message, 'smtp connect()' ) ) {
			$this->error( $msg . '<br>' . $error_message, '611bba516ffe270af2a99963' );
		} elseif ( false !== stripos( $error_message, 'data not accepted' ) ) {
			$this->error( $msg . '<br>' . $error_message, '611bb6ba6ffe270af2a99935' );
		} elseif ( false !== stripos( $error_message, 'could not execute' ) ) {
			$this->error( $msg . '<br>' . $error_message, '611bad7cb55c2b04bf6df0a0' );
		} else {
			$this->error( $msg . '<br>' . $error_message );
		}
	}


	/**
	 * Probes a remote host/port and records success or notice/error.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $port   TCP port number.
	 * @param string $domain Hostname to connect to.
	 * @param bool   $strict Optional. Treat closed ports as errors. Default false.
	 * @return void
	 */
	private function port_test( $port, $domain, $strict = false ) {

		$result = $this->check_port( $domain, $port );
		if ( strpos( $result, 'open' ) !== false ) {
			$this->success( sprintf( 'Port %s is open an can be used!', '<strong>' . $port . '</strong>' ) . ' <code>' . $result . '</code>' );
		} else {
			$message = sprintf( 'Port %s is NOT open an cannot be used!', '<strong>' . $port . '</strong>' ) . ' <code>' . $result . '</code>';
			if ( $strict ) {
				$this->error( $message );
			} else {
				$this->notice( $message );
			}
		}
	}

	/**
	 * Attempts an fsockopen connection and returns an open/closed status string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $host Remote hostname.
	 * @param int    $port Remote TCP port.
	 * @return string Human-readable port status.
	 */
	public function check_port( $host, $port ) {

		if ( ! function_exists( 'fsockopen' ) ) {
			return 'requires fsockopen to check ports.';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen -- TCP port probe requires fsockopen.
		$conn = @fsockopen( $host, $port, $errno, $errstr, 5 );

		$return = ( is_resource( $conn ) ? '(' . getservbyport( $port, 'tcp' ) . ') open.' : 'closed [' . $errstr . ']' );

		if ( is_resource( $conn ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fsockopen port probe above.
			fclose( $conn );
		}

		return $return;
	}

	/**
	 * Builds recipient and body data for the wp_mail() self-test.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null Mail arguments, or null when the current user is invalid.
	 */
	private function get_test_mail_data() {

		$user = wp_get_current_user();

		if ( ! $user ) {
			$this->error( 'No current user found for test mail.' );
			return;
		}
		if ( ! is_email( $user->user_email ) ) {
			$this->error( 'The current user doesn\'t have a valid email address to send a test mail.' );
			return;
		}

		$return = array(
			'to'      => $user->user_email,
			'subject' => 'This is a test mail from the Mailster Test page',
			'message' => sprintf( "This message has been sent from\n\n%s.\n\nYou can delete this message.", admin_url( 'admin.php?page=mailster-tester' ) ),
		);

		return $return;
	}
}
