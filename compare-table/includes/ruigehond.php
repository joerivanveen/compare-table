<?php

declare( strict_types=1 );

namespace {
	defined( 'ABSPATH' ) or die();

	if ( WP_DEBUG ) { // When debug display the errors generated during activation (if any).
		if ( false === function_exists( 'ruigehond_ITOEWERKLKVEIR_activation_error' ) ) {
			function ruigehond_ITOEWERKLKVEIR_activation_error() {
				if ( ( $contents = ob_get_contents() ) ) {
					update_option( 'ruigehond_ITOEWERKLKVEIR_plugin_error', $contents );
				}
			}

			add_action( 'activated_plugin', 'ruigehond_ITOEWERKLKVEIR_activation_error' );

			/* Then to display the error message: */
			add_action( 'admin_notices', static function () {
				if ( ( $message = get_option( 'ruigehond_ITOEWERKLKVEIR_plugin_error' ) ) ) {
					echo '<div class="notice notice-error"><p>', esc_html( $message ), '</p></div>';
				}
				/* Remove or it will persist */
				delete_option( 'ruigehond_ITOEWERKLKVEIR_plugin_error' );
			} );
		}
	}
}

namespace ruigehond_ITOEWERKLKVEIR_0_4_1 {

	use stdClass;

	/**
	 * Base class for plugin development, contains useful methods and variables, inherit from this in your plugin
	 */
	class ruigehond {
		public $identifier, $wpdb;
		private $options, $options_checksum, $text_domain;

		public function __construct( string $identifier ) {
			$this->identifier = $identifier; // must be ruigehond###, the unique identifier for this plugin
			global $wpdb;
			$this->wpdb = $wpdb;
			register_shutdown_function( array( $this, '__shutdown' ) );
		}

		/**
		 * called on shutdown, saves changed options for this plugin
		 */
		public function __shutdown() {
			// save the options when changed
			if (
				isset( $this->options )
				&& $this->options_checksum !== md5( json_encode( $this->options ) )
			) {
				update_option( $this->identifier, $this->options );
			}
		}

		/**
		 * wrapper for answerObject, to get it from the current namespace
		 *
		 * @param $text
		 * @param $data
		 *
		 * @return answerObject
		 *
		 * @since 0.3.3
		 */
		public function getAnswerObject( $text, $data ): answerObject {
			return new answerObject( $text, $data );
		}

		/**
		 * wrapper for questionObject, to get it from the current namespace
		 *
		 * @param $text
		 *
		 * @return questionObject
		 */
		public function getQuestionObject( $text ): questionObject {
			return new questionObject( $text );
		}

		/**
		 * wrapper for returnObject, to get it from the current namespace
		 *
		 * @param null $errorMessage
		 *
		 * @return returnObject
		 */
		public function getReturnObject( $errorMessage = null ): returnObject {
			return new returnObject( $errorMessage );
		}

		/**
		 * @param $plugin_slug string official slug of the plugin
		 *
		 * @return bool whether on (one of) the settings page(s) of this plugin
		 * @since 0.3.4
		 */
		protected function onSettingsPage( string $plugin_slug ): bool {
			if ( isset( $_GET['page'] ) && 0 === strpos( $_GET['page'], $plugin_slug ) ) {
				return true;
			}
			if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === $this->identifier ) {
				return true;
			}

			return false;
		}


		/**
		 * Loads Text Domain for WordPress plugin
		 * NOTE relies on the fact that plugin slug is also the text domain
		 * and the .mo files must be in /languages subfolder
		 *
		 * @param $text_domain string the text domain, which is also the plugin slug as per the rules of WordPress
		 *
		 * @since 0.3.1 added correct plugin domain and directory separator, deprecated old version
		 */
		public function loadTranslations( string $text_domain ): void {
			$path = "$text_domain/languages/";
			load_plugin_textdomain( $text_domain, false, $path );
		}

		/**
		 * Floating point numbers have errors that make them ugly and unusable that are not simply fixed by round()ing them
		 * Use floatForHumans to return the intended decimal as a string (floatVal if you want to perform calculations)
		 * Decimal separator is a . as is standard, use number formatting/ str_replace etc. if you want something else
		 *
		 * @param float|null $float will be formatted to be human-readable
		 *
		 * @return string the number is returned as a correctly formatted string
		 *
		 * @since    0.3.2
		 * @added input check in 0.3.3
		 */
		function floatForHumans( $float ): string {
			if ( null === $float or '' === ( $sunk = (string) $float ) ) {
				return '';
			}
			// floating point not accurate... https://stackoverflow.com/questions/4921466/php-rounding-error
			// whenever there is a series of 0's or 9's, format the number for humans that don't care about computer issues
			if ( false !== ( $index = strpos( $sunk, '00000' ) ) ) {
				$sunk = substr( $sunk, 0, $index );
				if ( '.' === substr( $sunk, - 1 ) ) {
					$sunk = substr( $sunk, 0, - 1 );
				}
			}
			if ( false !== ( $index = strpos( $sunk, '99999' ) ) ) {
				$sunk = substr( $sunk, 0, $index );
				if ( '.' === substr( $sunk, - 1 ) ) {
					$sunk = (string) ( (int) $sunk + 1 );
				} else {
					$n    = (int) substr( $sunk, - 1 ); // this can never be nine, so you can add 1 safely
					$sunk = substr( $sunk, 0, - 1 ) . ( $n + 1 );
				}
			}

			return $sunk;
		}

		/**
		 * @return float current max upload in MB
		 */
		public function maxUploadLimit(): float {
			$max_upload   = floatval( ini_get( 'upload_max_filesize' ) );
			$max_post     = floatval( ini_get( 'post_max_size' ) );
			$memory_limit = floatval( ini_get( 'memory_limit' ) );

			return min( $max_upload, $max_post, $memory_limit );
		}

		/**
		 * @param $key string the option name you request the value of
		 * @param $default mixed|null default null: what to return when key doesn't exist
		 *
		 * @return mixed|null the requested value or $default when the option doesn't exist
		 *
		 * @since    0.3.0
		 */
		public function getOption( string $key, $default = null ) {
			$options = $this->getOptions();
			if ( array_key_exists( $key, $options ) ) {
				return $options[ $key ];
			} else {
				return $default;
			}
		}

		/**
		 * @param $key string the option name you will store the value under
		 * @param $value mixed whatever you want to store under the name $key
		 *
		 * @return mixed|null the old value, should you want to do something with it
		 *
		 * @since    0.3.0
		 */
		public function setOption( string $key, $value ) {
			$return_value = $this->getOption( $key );
			// by requesting the old value you are certain $this->options is an array
			$this->options[ $key ] = $value;

			return $return_value;
		}

		/**
		 * Function gets the options for this plugin instance (identified by $this->identifier)
		 * using WordPress get_option and caching the array for further use
		 * It also stores a signature, so ruigehond can auto-update the options upon shutdown
		 *
		 * @return array all the options for $this->identifier as an array, which can be empty
		 *
		 * @since    0.3.0
		 */
		private function getOptions(): array {
			if ( false === isset( $this->options ) ) {
				$temp = get_option( $this->identifier );
				if ( $temp and is_array( $temp ) ) {
					$this->options = $temp;
				} else {
					$this->options = array();
				}
				$this->options_checksum = md5( json_encode( $this->options ) );
			}

			return $this->options;
		}

		/**
		 * Determines if a post, identified by the specified ID, exists
		 * within the WordPress database.
		 *
		 * @param int $id The ID of the post to check
		 *
		 * @return   bool          True if the post exists; otherwise, false.
		 * @since    0.0.0
		 */
		public function postExists( int $id ): bool {
			return is_string( get_post_status( $id ) ); // wp function
		}

		/**
		 * @param string $table_name
		 * @param array $values
		 *
		 * @return int the id of the inserted row, or 0 on failure
		 */
		public function insertDb( string $table_name, array $values ): int {
			$rows_affected = $this->wpdb->insert( $table_name, $values );
			if ( 1 === $rows_affected ) {
				return $this->wpdb->insert_id ?: PHP_INT_MAX; // var holds the last inserted id
			} else {
				return 0;
			}
		}

		/**
		 * @param string $table_name
		 * @param array $values
		 * @param array $where
		 *
		 * @return int 0 on failure, > 0 is the insert id, < 0 is the number of rows affected for update
		 * return value will be PHP_INT_MAX when insert succeeded, but there was no id column updated
		 */
		public function upsertDb( string $table_name, array $values, array $where ): int {
			$prepared_values = array( $table_name );
			$sql             = 'SELECT EXISTS (SELECT 1 FROM %i WHERE 1=1';

			foreach ( $where as $key => $value ) {
				$sql               .= ' AND %i = %s';
				$prepared_values[] = $key;
				$prepared_values[] = $value;
				// remove current id from values, so it will not be part of an insert statement later
				if ( 'id' === $key || "{$table_name}_id" === $key ) {
					unset( $values[ $key ] );
				}
			}

			$sql .= ');';

			$sql = $this->wpdb->prepare( $sql, $prepared_values );
			$joe = $this->wpdb->get_var( $sql );
			if ( $joe ) {
				return - $this->wpdb->update( $table_name, $values, $where );
			}

			return $this->insertDb( $table_name, $values + $where );
		}
	}

	/**
	 * A simple object that can be used as a return value for a method or function
	 * containing not only a success bit / boolean, but also messages, a question with answers and raw data
	 *
	 * External variables are public so they are encoded when you put the object through json_encode
	 */
	class returnObject {

		// public vars will be used by json_encode, private ones will not
		public $success, $messages, $question, $data = array(), $has_error = false;

		/**
		 * Constructs the returnObject with a default success value of false
		 *
		 * @param string|null $errorMessage Optional if you just want to return an error, you can initialize the returnObject as such
		 *
		 * @since   0.2.0
		 */
		public function __construct( string $errorMessage = null ) {
			$this->success   = false;
			$this->messages  = [];
			$this->has_error = false;
			if ( isset( $errorMessage ) ) {
				$this->add_message( $errorMessage, 'error' );
			}
		}

		public function get_success(): bool {
			return $this->success;
		}

		/**
		 * sets the public $success value of this return object
		 *
		 * @param bool $success sets the public $success value (true or false)
		 *
		 * @since 0.2.0
		 */
		public function set_success( bool $success ) {
			$this->success = $success;
		}

		/**
		 * add message to the public $messages array
		 *
		 * @param string $messageText Add $string to the messages already in the returnObject
		 * @param string $level Optional, default 'log': indicates type of message: 'log', 'warn' or 'error'
		 *
		 * @since 0.2.0
		 */
		public function add_message( string $messageText, string $level = 'log' ) { // possible levels are 'log', 'warn' and 'error'
			$msg              = new stdClass;
			$msg->text        = $messageText;
			$msg->level       = $level;
			$this->messages[] = $msg;
			if ( 'error' === $level ) {
				$this->has_error = true;
			}
		}

		public function get_messages(): string {
			return implode( "\n", $this->messages );
		}

		/**
		 * set the returnObject's public $data property
		 *
		 * @param mixed $data The public $data property will be set to $data, previous values are discarded
		 *
		 * @since 0.2.0
		 */
		public function set_data( $data ) {
			$this->data += $data;
		}

		public function set_question( $question ) {
			if ( isset( $question ) && $question instanceof questionObject ) {
				$this->question = $question;
			}
		}

		public function has_question(): bool {
			return isset( $this->question );
		}


	}

	class questionObject {
		public $text, $answers;

		public function __construct( $text = null ) {
			$this->text = $text;
		}

		public function add_answer( $answer ) {
			if ( isset( $answer ) && $answer instanceof answerObject ) {
				$this->answers[] = $answer;
			}
		}

		public function set_text( $text ) {
			$this->text = (string) $text;
		}
	}

	class answerObject {
		public $text, $data;

		public function __construct( $text, $data = null ) {
			$this->text = $text;
			$this->data = $data;
			// if data is null it means javascript doesn't have to send anything back
		}
	}
} // end of namespace ruigehond_ITOEWERKLKVEIR_0_4_1
