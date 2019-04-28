<?php
/**
 * Saltus Framework
 *
 * @wordpress-plugin
 * Plugin Name:       Saltus Framework Demo
 * Plugin URI:        https://saltus.io/
 * Description:       Saltus Plugin Framework Demo.
 * Version:           0.0.1
 * Author:            Saltus
 * Author URI:        https://saltus.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       saltus-framework-demo
 * Domain Path:       /languages
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo;

use Saltus\WP\Framework;

// If this file is called directly, quit.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

if ( file_exists( dirname( __FILE__ ) . '/lib/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/lib/autoload.php';
}

if ( ! class_exists( Framework\Core::class ) ) {
	exit;
}

/*
 * The path to the plugin root directory is mandatory,
 * so it loads the models from a subdirectory.
 */
$framework = new Framework\Core( dirname( __FILE__ ) );

/**
 * Initialize plugin
 *
 */
add_action(
	'plugins_loaded',
	function ( $framework ) {
		$plugin = new Core( 'saltus-framework', '0.0.1', __FILE__, $framework );
		$plugin->init();
	}
);

