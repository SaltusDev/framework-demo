<?php
/**
 * Saltus Framework
 *
 * @wordpress-plugin
 * Plugin Name:       Saltus Framework
 * Plugin URI:        https://saltus.io/
 * Description:       Saltus Plugin Framework.
 * Version:           0.0.1
 * Author:            Saltus
 * Author URI:        https://saltus.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       saltus-framework
 * Domain Path:       /languages
 */

namespace Saltus\WP\Plugin\Saltus\PluginFramework;

use Saltus\WP\Plugin\Saltus\Framework;

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

