/**
 * Build automation scripts.
 *
 * @package CoCart
 */

module.exports = function(grunt) {
	'use strict';

	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),

		// Setting directories.
		dirs: {
			php: 'includes'
		},

		// Bump version numbers (replace with version in package.json)
		replace: {
			php: {
				src: [
					'<%= dirs.php %>/class-<%= pkg.name %>.php'
				],
				overwrite: true,
				replacements: [
					{
						from: /public static \$version = \'.*.'/m,
						to: "public static $version = '<%= pkg.version %>'"
				}
				]
			},
			package: {
				src: [
					'load-package.php',
				],
				overwrite: true,
				replacements: [
					{
						from: /@version .*$/m,
						to: "@version <%= pkg.version %>"
				},
				]
			}
		},

	}); // END of Grunt modules.

	// Update version of package.
	grunt.registerTask( 'version', [ 'replace:php', 'replace:package' ] );
};
