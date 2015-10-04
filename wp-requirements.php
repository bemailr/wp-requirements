<?php
/**
 * Class WP_Requirementsfor checking server and site for meeting your code requirements
 *
 * You can use this plugin to check, that PHP, MySQL and WordPress (version, plugins, themes) meet requirements
 * to make your code in a plugin or theme work.
 *
 * You can define those rules as both array or a JSON file. For an example json file see the requirements-sample.json.
 * Copy that file to a new one without "-sample" in the file name part and adjust data to your needs.
 * You can place this file in such place (that this class with search in):
 * 1. The same folder as this file.
 * 2. Root plugin or theme directory (usually, '/wp-content/plugins/your-plugin/requirements.json').
 * 3. Root of WordPress install.
 */

// Do not load the class twice. Although there might be compatibility issues.
if ( class_exists( 'WP_Requirements' ) ) {
	return;
}

class WP_Requirements {

	const VERSION = '1.0';

	public $results = array();

	public $redirect_url;
	public $locale                   = 'wp-requirements';
	public $version_compare_operator = '>=';

	public function __construct( $requrements = array() ) {

		if ( empty( $requrements ) ) {
			$requrements = $this->load_json();
		}

		// heavy processing here
		$this->validate_requirements( $requrements );
	}

	protected function validate_requirements( Array $requrements ) {

		if ( empty( $requrements ) ) {
			return;
		}

		if ( ! empty( $requrements['params'] ) ) {
			$this->set_params( $requrements['params'] );
		}

		foreach ( $requrements as $key => $data ) {
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
		$this->redirect_url             = ! empty( $params['redirect_url'] ) ? esc_url( (string) $params['redirect_url'] ) : $this->redirect_url;
		$this->version_compare_operator = ! empty( $params['version_compare_operator'] ) ? (string) $params['version_compare_operator'] : $this->version_compare_operator;
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

	private function get_current_mysql_ver() {
		global $wpdb;

		/** @var Stdclass $wpdb */
		return substr( $wpdb->dbh->server_info, 0, strpos( $wpdb->dbh->server_info, '-' ) );
	}

	public function is_met() {


		return true;
	}

	public function notify() {
		add_action( 'admin_notice', array( $this, 'format_message' ) );
	}

	/********************************
	 ************* JSON *************
	 *******************************/

	/**
	 * Load requirements.json, uses hierarchy:
	 * 1) the same path as __FILE__
	 * 2) plugin/theme base path
	 * 3) WordPress ABSPATH
	 */
	protected function load_json() {
		$json_file = $this->search_json();
		$json_data = '{}';

		if ( $json_file !== '' ) {
			$json_data = file_get_contents( $json_file );
		}

		return $this->parse_json( $json_data );
	}

	protected function search_json() {
		$path = __DIR__ . '/wp-requirements.json';

		if ( file_exists( $path ) ) {
			return $path;
		}

		return '';
	}

	protected function parse_json( $json ) {
		return (array) json_decode( $json );
	}
}