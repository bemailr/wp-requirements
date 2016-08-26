# WP Requirements
## Version 2

A helpful library for checking the prerequisites when activating / running a WordPress plugin.

## About

`WP Requirements` is a library that helps WordPress developers to check whether the environment meets their plugins' requirements.

The current version of the library can be used to verify:
* Versions of PHP, MySQL and WordPress;
* Enabled PHP extensions;
* Versions of the activated WordPress theme and plugins.

# How to use

First, you need to install the library. You can just `require_once` it (see below in code samples), or use Composer:

```composer require slaffik/wp-requirements```

There are several ways to define your own requirements:

* using ordinary PHP array
* using cool JSON file

## Using PHP

```php
// Don't forget to include this library into the main file of your plugin
require_once dirname( __FILE__ ) . '/vendor/slaffik/wp-requirements/wp-requirements.php';

// Init your requirements globally in your main plugin file
// or use any function that will return all that:
global $wpr_test;

// Any options can be omitted
$wpr_test = array(
	'php'       => array(
		'version'    => 5.3,
		'extensions' => array( 'curl', 'mysql' )
	),
	'mysql'     => array( 'version' => 5.7 ),
	'wordpress' => array(
		'version' => 3.8,
		'plugins' => array(
			'buddypress/bp-loader.php'            => 2.2,
			'bp-default-data/bp-default-data.php' => '1.0',
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
 *
 * Check all the time in the admin area that nothing is broken for your plugin.
 */
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

Get the `wp-requirement-sample.json` from this repository, make required changes, rename it to `wp-requirement.json` and put in one of these directories:

1. same place where a file with `WP_Requirements` class is located, example: `/wp-content/plugins/your-plugin/vendor/slaffik/wp-requirements/wp-requirements.json`
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

## Params

You can modify some parts of the logic

* `requirements_details_url` - default is ` ` (empty). Gives the ability to define a URL that will be displayed instead of the default complete list of successful and failed checks against requirements. Useful if the list is quite big and/or if you provide such information on a special page in either WordPress admin area or on your own site.
* `locale` - default is `wp-requirements`. Gives the ability to define a domain locale, that will be used to translate default messages in this class using your own `PO/MO` files.
* `version_compare_operator` - default is `>=`. Gives the ability to finer define rules to compare versions. Other possible values are those, that are supported by [version_compare()](http://php.net/manual/en/function.version-compare.php)
* `not_valid_actions` - default is `array( 'deactivate', 'admin_notice' )`. Gives the ability to define what should be done on plugin activation if requirements are **not** met.
