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
if ( class_exists( 'WP_Requirements' ) ) {
	return;
}

class WP_Requirements {

	const VERSION = '1.0';

	public $results = array();

	public $requirements_details_url = '';
	public $locale                   = 'wp-requirements';
	public $version_compare_operator = '>=';
	public $not_valid_actions        = array( 'deactivate', 'admin_notice' );

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
		$plugin_dir  = explode( '/', plugin_basename( __FILE__ ) );
		$plugin      = get_plugins( '/' . $plugin_dir[0] );
		$plugin_file = array_keys( $plugin );
		//$plugin_data = array_values( $plugin );

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
		$this->requirements_details_url = ! empty( $params['requirements_details_url'] ) ? esc_url( (string) $params['requirements_details_url'] ) : $this->requirements_details_url;
		$this->version_compare_operator = ! empty( $params['version_compare_operator'] ) ? (string) $params['version_compare_operator'] : $this->version_compare_operator;
		$this->not_valid_actions        = ! empty( $params['not_valid_actions'] ) ? (array) $params['not_valid_actions'] : $this->not_valid_actions;
	}

	/**
	 * Check all PHP related data, like version and extensions
	 *
	 * @param array $php
	 */
	protected function validate_php( $php ) {
		$result = array();

		foreach ( $php as $type => $data ) {
			switch ( $type ) {
				case 'version':
					$result[ $type ] = version_compare( phpversion(), $data, $this->version_compare_operator );

					break;

				case 'extensions':
					$data = is_array( $data ) ? $data : (array) $data;

					// check that all required extensions are loaded
					foreach ( $data as $extension ) {
						if ( $extension && is_string( $extension ) ) {
							$result[ $type ][ $extension ] = extension_loaded( $extension );
						}
					}

					break;
			}
		}

		$this->results['php'] = $result;
	}

	/**
	 * Check all MySqll related data, like version (so far)
	 *
	 * @param array $mysql
	 */
	protected function validate_mysql( $mysql ) {
		if ( ! empty( $mysql['version'] ) ) {
			$this->results['mysql']['version'] = version_compare( $this->get_current_mysql_ver(), $mysql['version'], $this->version_compare_operator );
		}
	}

	/**
	 * Check all WordPress related data, like version, plugins and theme
	 *
	 * @param array $wordpress
	 */
	protected function validate_wordpress( $wordpress ) {
		global $wp_version;

		$result = array();

		foreach ( $wordpress as $type => $data ) {
			switch ( $type ) {
				case 'version':
					$result[ $type ] = version_compare( $wp_version, $data, '>=' );
					break;

				case 'plugins':
					if ( ! function_exists( 'is_plugin_active' ) ) {
						include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					}

					$data = is_array( $data ) ? $data : (array) $data;

					foreach ( $data as $plugin => $version ) {
						if ( $plugin && is_string( $plugin ) ) {
							// check that it's active

							$raw_Data                   = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
							$result[ $type ][ $plugin ] = is_plugin_active( $plugin ) && version_compare( $raw_Data['Version'], $version, $this->version_compare_operator );
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
					}

					break;
			}
		}

		$this->results['wordpress'] = $result;
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
		foreach ( $this->not_valid_actions as $action ) {
			switch ( $action ) {
				case 'deactivate':
					deactivate_plugins( $this->get_plugin( 'basename' ), true );

					if ( isset( $_GET['activate'] ) ) {
						unset( $_GET['activate'] );
					}
					break;

				case 'admin_notice':
					add_action( 'admin_notices', array( $this, 'disply_admin_notice' ) );
					break;
			}
		}
	}

	/**
	 * Display an admin notice in WordPress admin area
	 */
	public function disply_admin_notice() {
		echo '<div class="notice is-dismissible error"><p>';

		printf(
			__( '%s can\'t be activated because your site doesn\'t meet plugin requirements.', $this->locale ),
			'<strong>' . $this->get_plugin( 'name' ) . '</strong>'
		);
		if ( ! empty( $this->requirements_details_url ) ) {
			printf(
				' ' . __( 'Please read more details <a href="%s">here</a>.', $this->locale ),
				esc_url( $this->requirements_details_url )
			);
		}

		echo '</p></div>';
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
	 * Get the MySQL version number based on data in global WPDB class
	 *
	 * @uses WPDB $wpdb
	 * @return string MySQL version number, like 5.5
	 */
	private function get_current_mysql_ver() {
		global $wpdb;

		/** @var Stdclass $wpdb */
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
	 * Load requirements.json
	 */
	protected function load_json() {
		$json_file = $this->search_json();
		$json_data = '{}';

		if ( $json_file !== '' ) {
			$json_data = file_get_contents( $json_file );
		}

		return $this->parse_json( $json_data );
	}

	/**
	 * Search for a JSON file in different places
	 *
	 * @return string Path to a found json file
	 */
	protected function search_json() {
		$file = '/wp-requirements.json';

		// 1) Search in this same folder
		$path = wp_normalize_path( __DIR__ . $file );
		if ( file_exists( $path ) ) {
			return $path;
		}

		// 2) Plugin base path
		$path = $this->get_plugin( 'fullpath' ) . $file;
		if ( file_exists( $path ) ) {
			return $path;
		}

		// 3) WP_CONTENT_DIR
		$path = WP_CONTENT_DIR . $file;
		if ( file_exists( $path ) ) {
			return $path;
		}

		// 4) WordPress base bath
		$path = ABSPATH . $file;
		if ( file_exists( $path ) ) {
			return $path;
		}

		return '';
	}

	/**
	 * @param string $json
	 *
	 * @return array
	 */
	protected function parse_json( $json ) {
		return json_decode( $json, true );
	}
}