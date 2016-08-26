<?php
/**
 * File: wpr-loader-2.php
 *
 * @package WP-Requirements
 */

/**
 * Load the latest version of the class.
 *
 * The version number goes to the function name and to the action priority.
 * In the a.b.c, the "b" and "c" are padded with zeroes.
 * For example, 2.1.2 becomes 20102.
 * Convention: no 2.1.123 and no 2.1.2.1 versions.
 *
 * When the major release changes, we make a copy of the class and the loader,
 * so that people do not upgrade automatically.
 */

if ( ! function_exists( 'wp_requirements_class_loader_20000' ) ) :

	add_action( 'plugins_loaded', 'wp_requirements_class_loader_20000', - 20000 );

	/**
	 * Load class if not loaded already.
	 */
	function wp_requirements_class_loader_20000() {

		if ( ! class_exists( 'WP_Requirements_2', false ) ) {
			require_once dirname( __FILE__ ) . '/includes/class-wp-requirements-2.php';
		}
	}

endif;

/*EOF*/
