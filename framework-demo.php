<?php
/**
 * Saltus Framework
 *
 * @wordpress-plugin
 * Plugin Name:       Saltus Framework Demo
 * Plugin URI:        https://saltus.io/
 * Description:       Saltus Plugin Framework Demo.
 * Version:           1.0.11
 * Author:            Saltus
 * Author URI:        https://saltus.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       framework-demo
 * Domain Path:       /languages
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo;

// If this file is called directly, quit.
if ( ! defined( 'WPINC' ) ) {
	exit;
}
if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

if ( class_exists( \Saltus\WP\Framework\Core::class ) ) {

	/*
	* The path to the plugin root directory is mandatory,
	* so it loads the models from a subdirectory.
	*/
	$framework = new \Saltus\WP\Framework\Core( dirname( __FILE__ ) );
	$framework->register();

	/**
	 * Initialize plugin
	 *
	 */
	add_action(
		'plugins_loaded',
		function () use ( $framework ) {
			$plugin = new Core( 'saltus-framework', '0.0.2', __FILE__, $framework );
			$plugin->init();
		}
	);

}




