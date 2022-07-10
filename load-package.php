<?php
/**
 * This file is designed to be used to load as package NOT a WP plugin!
 *
 * @version 4.0.0
 * @package CoCart Compatibility Package
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'COCART_COMPATIBILITY_PACKAGE_FILE' ) ) {
	define( 'COCART_COMPATIBILITY_PACKAGE_FILE', __FILE__ );
}

// Include the main CoCart Compatibility Package class.
if ( ! class_exists( 'CoCart\Compatibility\Package', false ) ) {
	include_once untrailingslashit( plugin_dir_path( COCART_COMPATIBILITY_PACKAGE_FILE ) ) . '/includes/class-cocart-compatibility.php';
}

/**
 * Returns the main instance of cocart_compatibility_package and only runs if it does not already exists.
 *
 * @return cocart_compatibility_package
 */
if ( ! function_exists( 'cocart_compatibility_package' ) ) {
	function cocart_compatibility_package() {
		return CoCart\Compatibility\Package::init();
	}

	cocart_compatibility_package();
}
