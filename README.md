# WP Requirements

Providing WordPress developers with the ability to check server and WordPress conditions for satisfying their plugins requirements.

Including:
* PHP minimum version
* MySQL minimum version
* Enabled PHP extensions
* WordPress minimum version
* activated WordPress plugins and their appropriate minimum versions
* activated WordPress theme and its appropriate minimum version

# How to use

There are several ways to define your own requirements:

* using ordinary PHP array
* using cool JSON file

## Using PHP

```php
// Don't forget to include this library into the main file of your plugin
include( dirname( __FILE__ ) . '/lib/wp-requirements.php' );

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

## Using JSON

Get the `wp-requirement-sample.json` from this repository, make required changes, rename it to `wp-requirement.json` and put in one these directories:

1. same place where a file with `WP_Requirements` class is located, example: `/wp-content/plugins/your-plugin/lib/wp-requirements.json`
2. plugin basename path, example: `/wp-content/plugins/your-plugin/wp-requirements.json`
3. WordPress content directory: `/wp-content/wp-requirements.json`
4. WordPress absolute path (basically, the same place where `wp-load.php` is located): `/wp-requirements.json`

That's the loading order from top to bottom. Meaning, that first found file will be loaded as requirements-provider, and other places will be just ignored.

And here is how to init with JSON file loader:

```php
// Pay attention - no params specified when initialising the class
$requirements = new WP_Requirements();

if ( ! $requirements->valid() ) {
	$requirements->process_failure();
}
```

---

Enjoy!