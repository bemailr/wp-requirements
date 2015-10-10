<?php
/**
 * Class WP_Requirements for checking server and site for meeting your code requirements
 *
 * You can use this class to check, that PHP, MySQL and WordPress (version, plugins, themes) meet requirements
 * to make your code in a plugin work.
 *
 * You can define those rules as both array or a JSON file (soon). For an example json file see the requirements-sample.json.
 * Copy that file to a new one without "-sample" in the file name part and adjust data to your needs.
 * You can place this file in such place (that this class with search in):
 * 1. The same folder as this file.
 * 2. Root plugin directory (usually, '/wp-content/plugins/your-plugin/wp-requirements.json').
 * 3. WP_CONTENT_DIR
 * 4. Root of WordPress install.
 */

// Do not load the class twice. Although there might be compatibility issues.
if ( ! class_exists( 'WP_Requirements' ) ) :

	class WP_Requirements {

		const VERSION = '1.1';

		public $results  = array();
		public $required = array();

		public $requirements_details_url = '';
		public $locale                   = 'wp-requirements';
		public $version_compare_operator = '>=';
		public $not_valid_actions        = array( 'deactivate', 'admin_notice' );

		private $icon_good = '<span class="dashicons dashicons-yes"></span>&nbsp';
		private $icon_bad  = '<span class="dashicons dashicons-minus"></span>&nbsp';

		private $plugin = array();

		/**
		 * WP_Requirements constructor.
		 *
		 * @param array $requirements
		 */
		public function __construct( $requirements = array() ) {
			// plugin information is always required, so get it once
			$this->set_plugin();

			// Requirements can be specified in JSON file
			if ( empty( $requirements ) ) {
				$requirements = $this->load_json();
			}

			// heavy processing here
			$this->validate_requirements( $requirements );
		}

		/**
		 * Set paths, name etc for a plugin
		 */
		protected function set_plugin() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_dir  = explode( '/', plugin_basename( __FILE__ ) );
			$plugin      = get_plugins( '/' . $plugin_dir[0] );
			$plugin_file = array_keys( $plugin );

			$this->plugin = array(
				'dirname'  => $plugin_dir[0],
				'filename' => $plugin_file[0],
				'name'     => $plugin[ $plugin_file[0] ]['Name'],
				'basename' => $plugin_dir[0] . '/' . $plugin_file[0],
				'fullpath' => wp_normalize_path( WP_PLUGIN_DIR ) . '/' . $plugin_dir[0]
			);
		}

		/**
		 * All the requirements will be checked and become accesible here:
		 *     $this->results
		 *
		 * @param array $requirements
		 */
		protected function validate_requirements( $requirements ) {
			if ( empty( $requirements ) ) {
				return;
			}

			if ( ! empty( $requirements['params'] ) ) {
				$this->set_params( $requirements['params'] );
			}

			foreach ( $requirements as $key => $data ) {
				switch ( $key ) {
					case 'php':
						$this->validate_php( $data );
						break;
					case 'mysql':
						$this->validate_mysql( $data );
						break;
					case 'wordpress':
						$this->validate_wordpress( $data );
						break;
				}
			}
		}

		/**
		 * Redefine all params by those, that were submitted by a user
		 *
		 * @param array $params
		 */
		protected function set_params( $params ) {
			$this->locale                   = ! empty( $params['locale'] ) ? wp_strip_all_tags( (string) $params['locale'] ) : $this->locale;
			$this->requirements_details_url = ! empty( $params['requirements_details_url'] ) ? esc_url( trim( (string) $params['requirements_details_url'] ) ) : $this->requirements_details_url;
			$this->version_compare_operator = ! empty( $params['version_compare_operator'] ) ? (string) $params['version_compare_operator'] : $this->version_compare_operator;
			$this->not_valid_actions        = ! empty( $params['not_valid_actions'] ) ? (array) $params['not_valid_actions'] : $this->not_valid_actions;
		}

		/**
		 * Check all PHP related data, like version and extensions
		 *
		 * @param array $php
		 */
		protected function validate_php( $php ) {
			$result = $required = array();

			foreach ( $php as $type => $data ) {
				switch ( $type ) {
					case 'version':
						$result[ $type ]   = version_compare( phpversion(), $data, $this->version_compare_operator );
						$required[ $type ] = $data;
						break;

					case 'extensions':
						$data = is_array( $data ) ? $data : (array) $data;

						// check that all required extensions are loaded
						foreach ( $data as $extension ) {
							if ( $extension && is_string( $extension ) ) {
								$result[ $type ][ $extension ] = extension_loaded( $extension );
								$required[ $type ][]           = $extension;
							}
						}

						break;
				}
			}

			$this->results['php']  = $result;
			$this->required['php'] = $required;
		}

		/**
		 * Check all MySqll related data, like version (so far)
		 *
		 * @param array $mysql
		 */
		protected function validate_mysql( $mysql ) {
			if ( ! empty( $mysql['version'] ) ) {
				$this->results['mysql']['version']  = version_compare( $this->get_current_mysql_ver(), $mysql['version'], $this->version_compare_operator );
				$this->required['mysql']['version'] = $mysql['version'];
			}
		}

		/**
		 * Check all WordPress related data, like version, plugins and theme
		 *
		 * @param array $wordpress
		 */
		protected function validate_wordpress( $wordpress ) {
			global $wp_version;

			$result = $required = array();

			foreach ( $wordpress as $type => $data ) {
				switch ( $type ) {
					case 'version':
						$result[ $type ]   = version_compare( $wp_version, $data, '>=' );
						$required[ $type ] = $data;
						break;

					case 'plugins':
						if ( ! function_exists( 'is_plugin_active' ) ) {
							include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
						}

						$data = is_array( $data ) ? $data : (array) $data;

						foreach ( $data as $plugin => $version ) {
							if ( $plugin && is_string( $plugin ) ) {
								// check that it's active

								$raw_Data                     = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
								$result[ $type ][ $plugin ]   = is_plugin_active( $plugin ) && version_compare( $raw_Data['Version'], $version, $this->version_compare_operator );
								$required[ $type ][ $plugin ] = $version;
							}
						}

						break;

					case 'theme':
						$theme         = is_array( $data ) ? $data : (array) $data;
						$current_theme = wp_get_theme();

						// now check the theme - user defined slug can be either template (parent theme) or stylesheet (currently active theme)
						foreach ( $theme as $slug => $version ) {
							if (
								( $current_theme->get_template() === $slug ||
								  $current_theme->get_stylesheet() === $slug ) &&
								version_compare( $current_theme->get( 'Version' ), $version, $this->version_compare_operator )
							) {
								$result[ $type ][ $slug ] = true;
							} else {
								$result[ $type ][ $slug ] = false;
							}

							$required[ $type ][ $slug ] = $version;
						}

						break;
				}
			}

			$this->results['wordpress']  = $result;
			$this->required['wordpress'] = $required;
		}

		/**
		 * Check that requirements are met.
		 * If any of rules are failed, the whole check will return false.
		 * True otherwise.
		 *
		 * @return bool
		 */
		public function valid() {
			return ! $this->in_array_recursive( false, $this->results );
		}

		/**
		 * Get the list of registered actions and do everything defined by them
		 */
		public function process_failure() {
			if ( empty( $this->results ) || empty( $this->not_valid_actions ) ) {
				return;
			}

			foreach ( $this->not_valid_actions as $action ) {
				switch ( $action ) {
					case 'deactivate':
						deactivate_plugins( $this->get_plugin( 'basename' ), true );

						if ( isset( $_GET['activate'] ) ) {
							unset( $_GET['activate'] );
						}
						break;

					case 'admin_notice':
						add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
						break;
				}
			}
		}

		/**
		 * Display an admin notice in WordPress admin area
		 */
		public function display_admin_notice() {
			echo '<div class="notice is-dismissible error">';

			echo '<p>';

			printf(
				__( '%s can\'t be activated because your site doesn\'t meet all requirements.', $this->locale ),
				'<strong>' . $this->get_plugin( 'name' ) . '</strong>'
			);

			echo '</p>';

			// Display the link to more details, if we have it
			if ( ! empty( $this->requirements_details_url ) ) {
				printf(
					'<p>' . __( 'Please read more details <a href="%s">here</a>.', $this->locale ) . '</p>',
					esc_url( $this->requirements_details_url )
				);
			} else { // so we need to display all the failures in a notice
				echo '<ul>';
				foreach ( $this->results as $type => $data ) {
					echo $this->format_php_mysql_notice( $type, $data );
				}
				echo '</ul>';

			}

			echo '</div>';
		}

		/**
		 * Prepare a string, that will be displayed in a row for PHP and MySQL only
		 *
		 * @param string $type What's the type of the data: php or mysql
		 * @param array $data Contains version and extensions keys with their values
		 *
		 * @return string $result
		 */
		private function format_php_mysql_notice( $type, $data ) {
			$string_version        = __( '%s: current %s, required %s', $this->locale );
			$string_ext_loaded     = __( '%s is activated', $this->locale );
			$string_ext_not_loaded = __( '%s is not activated', $this->locale );
			$string_wp_loaded      = __( '%s of a valid version %s is activated', $this->locale );
			$string_wp_not_loaded  = __( '%s of a valid version %s is not activated', $this->locale );

			$message = array();

			foreach ( $data as $key => $value ) { // version : 5.5 || extensions : [curl,mysql]
				$section = $cur_version = '';

				if ( $type === 'php' ) {
					switch ( $key ) {
						case 'version':
							$section     = 'PHP Version';
							$cur_version = phpversion();
							break;
						case 'extensions':
							$section = 'PHP Extension';
							break;
					}
				} elseif ( $type === 'mysql' ) {
					$section     = 'MySQL Version';
					$cur_version = $this->get_current_mysql_ver();
				} elseif ( $type === 'wordpress' ) {
					switch ( $key ) {
						case 'version':
							$section = 'WordPress Version';
							global $wp_version;
							$cur_version = $wp_version;
							break;
						case 'plugins':
							$section = 'Plugin';
							break;
						case 'theme':
							$section = 'Theme';
							break;
					}
				}

				// Ordinary bool meant this is just a 'version'
				if ( is_bool( $value ) ) {
					$message[] = $this->get_notice_status_icon( $value ) .
					             sprintf(
						             $string_version,
						             $section,
						             $cur_version,
						             $this->version_compare_operator . $this->required[ $type ][ $key ]
					             );
				} elseif ( is_array( $value ) && ! empty( $value ) ) {
					// We need to know - whether we work with PHP extensions or WordPress plugins/theme
					// Extensions are currently passed as an ordinary numeric (while plugins - associative) array
					if ( ! $this->is_array_associative( $this->required[ $type ][ $key ] ) ) { // these are extensions
						foreach ( $value as $entity => $is_valid ) {
							$message[] = $this->get_notice_status_icon( $is_valid ) .
							             sprintf(
								             $is_valid ? $string_ext_loaded : $string_ext_not_loaded,
								             $section . ' "' . $entity . '"'
							             );
						}
					} else {
						foreach ( $value as $entity => $is_valid ) {
							$entity_name = '';
							// Plugins and themes has different data sources
							if ( $key == 'plugins' ) {
								$entity_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $entity, false );
								$entity_name = $entity_data['Name'];
							} elseif ( $key == 'theme' ) {
								$entity_data = wp_get_theme();
								$entity_name = $entity_data->get( 'Name' );
							}
							$message[] = $this->get_notice_status_icon( $is_valid ) .
							             sprintf(
								             $is_valid ? $string_wp_loaded : $string_wp_not_loaded,
								             $section . ' "' . $entity_name . '"',
								             $this->version_compare_operator . $this->required[ $type ][ $key ][ $entity ]
							             );
						}
					}
				}
			} // endforeach

			return '<li>' . implode( '</li><li>', $message ) . '</li>';
		}

		/**
		 * Return a visual icon indicator of success or error
		 *
		 * @param bool $status
		 *
		 * @return string
		 */
		private function get_notice_status_icon( $status ) {
			return ( $status === true ) ? $this->icon_good : $this->icon_bad;
		}

		/**
		 * Does $haystack contain $needle in any of the values?
		 * Adapted to our needs, works with arrays in arrays
		 *
		 * @param mixed $needle What to search
		 * @param array $haystack Where to search
		 *
		 * @return bool
		 */
		private function in_array_recursive( $needle, $haystack ) {
			foreach ( $haystack as $type => $v ) {
				if ( $needle === $v ) { // useful for recursion only
					return true;
				} elseif ( is_array( $v ) ) {
					// basically checks only version value
					if ( in_array( $needle, $v, true ) ) {
						return true;
					}

					// now, time for recursion
					if ( ! $this->in_array_recursive( $needle, $v ) ) {
						continue;
					} else {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Returns a value indicating whether the given array is an associative array.
		 *
		 * @param array $array the array being checked
		 *
		 * @return boolean whether the array is associative
		 */
		private function is_array_associative( $array ) {
			return array_keys( array_merge( $array ) ) !== range( 0, count( $array ) - 1 );
		}

		/**
		 * Get the MySQL version number based on data in global WPDB class
		 *
		 * @uses WPDB $wpdb
		 * @return string MySQL version number, like 5.5
		 */
		private function get_current_mysql_ver() {
			global $wpdb;

			/** @var stdClass $wpdb */
			return substr( $wpdb->dbh->server_info, 0, strpos( $wpdb->dbh->server_info, '-' ) );
		}

		/**
		 * Retrieve current plugin data, like paths, name etc
		 *
		 * @param string $data
		 *
		 * @return mixed
		 */
		public function get_plugin( $data = '' ) {
			// get all the data
			if ( empty( $data ) ) {
				return $this->plugin;
			}

			// get specific plugin data
			if ( ! empty( $this->plugin[ $data ] ) ) {
				return $this->plugin[ $data ];
			}

			return null;
		}

		/********************************
		 ************* JSON *************
		 *******************************/

		/**
		 * Load wp-requirements.json
		 *
		 * @return array
		 */
		protected function load_json() {
			$json_file = $this->search_json();
			$json_data = '{}';

			if ( $json_file !== '' ) {
				$json_data = file_get_contents( $json_file );
			}

			return ! empty( $json_data ) ? $this->parse_json( $json_data ) : array();
		}

		/**
		 * Search for a JSON file in different places
		 *
		 * @return string Path to a found json file
		 */
		protected function search_json() {
			$file = '/wp-requirements.json';

			// 1) Search in this same folder
			$path = wp_normalize_path( dirname( __FILE__ ) . $file );
			if ( is_readable( $path ) ) {
				return $path;
			}

			// 2) Plugin base path
			$path = $this->get_plugin( 'fullpath' ) . $file;
			if ( is_readable( $path ) ) {
				return $path;
			}

			// 3) WP_CONTENT_DIR
			$path = WP_CONTENT_DIR . $file;
			if ( is_readable( $path ) ) {
				return $path;
			}

			// 4) WordPress base bath
			$path = ABSPATH . $file;
			if ( is_readable( $path ) ) {
				return $path;
			}

			return '';
		}

		/**
		 * Parse JSON string to make it an array that is usable for us
		 *
		 * @param string $json
		 *
		 * @return array
		 */
		protected function parse_json( $json ) {
			if ( is_array( $data = json_decode( $json, true ) ) ) {
				return $data;
			}

			return array();
		}
	}

endif;