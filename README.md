# WP Requirements

Providing WordPress developers with the ability to check server and WordPress conditions for satisfying their plugins and/or themes requirements. 

This includes (or will include in future releases):
* PHP version
* MySQL version
* Enabled PHP extensions
* WordPress version
* WordPress plugins and their appropriate versions
* WordPress themes and their appropriate versions

# How to use

```php
// Don't forget to include this library into the main file of your plugin
include( __DIR__ . '/lib/wp-requirements.php' );

// Init your requirements globally in your main plugin file
// or use any function that will return all that:
global $wpr_test;

// Any options can be omitted
$wpr_test = array(
	'php'       => array(
		'version'    => 5.3,
		'extensions' => array( 'curl', 'mysql' )
	),
	'mysql'     => array( 'version' => 5.5 ),
	'wordpress' => array(
		'version' => 3.8,
		'plugins' => array(
			'buddypress/bp-loader.php'            => 2.2,
			'bp-default-data/bp-default-data.php' => 1.0,
		),
		'theme'   => array(
			'hueman' => 1.5
		)
	),
	'params'    => array(
		'requirements_details_url' => '//google.com',
		'locale'                   => 'bpf',
		'version_compare_operator' => '>=',
		'not_valid_actions'        => array( 'deactivate', 'admin_notice' )
	)
);

/**
 * Now you need to prevent both plugin activation and functioning 
 * if the site doesn't meet requirements
 */
 
// Check on plugin activation
register_activation_hook( __FILE__, 'your_plugin_activation' );

function your_plugin_activation() {
	global $wpr_test;

	$requirements = new WP_Requirements( $wpr_test );
	if ( ! $requirements->valid() ) {
		$requirements->process_failure();
		return;
	}
	
	// your other code here...
}

// Check all the time in admin area that nothing is broken for your plugin
// You can use other hooks instead of 'admin_init'
function your_plugin_check_requirements() {
	global $wpr_test;

	$requirements = new WP_Requirements( $wpr_test );
	if ( ! $requirements->valid() ) {
		$requirements->process_failure();
	}
}

add_action( 'admin_init', 'your_plugin_check_requirements' );
```

