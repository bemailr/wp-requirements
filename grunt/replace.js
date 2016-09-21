/* jshint node:true */

/**
 * @link http://www.jslint.com/help.html
 */
/*jslint node:true*/

/**
 * @link https://www.npmjs.com/package/grunt-text-replace
 */

/**
 * There is no support for Grunt templates in the `from:` replacement.
 * Therefore, we need to get the config variable ourselves.
 */
var pkgJson;
var tivwp_version_define;
pkgJson = require("../package.json");
//noinspection JSUnresolvedVariable
tivwp_version_define = pkgJson.tivwp_config.version.define;

module.exports = {
    version: {
        overwrite: true,
        src: ["<%= package.name %>.php"],
        replacements: [
            {
                from: new RegExp(" \* Version: .+"),
                to: " * Version: <%= package.version %>"
            },
            {
                from: new RegExp("define\\( '(" + tivwp_version_define + ")'.+"),
                to: "define( '$1', '<%= package.version %>' );"
            }
        ]
    },
    wpi18n: {
        overwrite: true,
        src: ["node_modules/grunt-wp-i18n/vendor/wp-i18n-tools/extract.php"],
        replacements: [
            {
                from: new RegExp("public function entry_from_call\( \$call, \$file_name \) \{.*"),
                to: "public function entry_from_call( $call, $file_name ) { if ( $call['args'][ count( $call['args'] ) - 1 ] !== '<%= package.tivwp_config.text_domain %>' ) { return null; }"
            }
        ]
    }
};
