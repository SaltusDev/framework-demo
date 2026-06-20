<?php
/**
 * Saltus Framework
 *
 * @wordpress-plugin
 * Plugin Name:       Saltus Framework Demo
 * Plugin URI:        https://saltus.io/
 * Description:       Saltus Plugin Framework Demo.
 * Version:           2.0.0
 * Author:            Saltus
 * Author URI:        https://saltus.io/
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       framework-demo
 * Domain Path:       /languages
 * Requires PHP:      8.3
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo;

// If this file is called directly, quit.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

if ( ! defined( __NAMESPACE__ . '\PLUGIN_FILE' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );
}
if ( ! defined( __NAMESPACE__ . '\PLUGIN_VERSION' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_VERSION', '2.0.0' );
}
if ( ! defined( __NAMESPACE__ . '\PLUGIN_SLUG' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_SLUG', 'framework-demo' );
}
if ( ! defined( __NAMESPACE__ . '\PLUGIN_TEXT_DOMAIN' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_TEXT_DOMAIN', 'framework-demo' );
}
if ( ! defined( __NAMESPACE__ . '\PLUGIN_MINIMUM_PHP' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_MINIMUM_PHP', '8.3' );
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! class_exists( Core::class ) && file_exists( __DIR__ . '/src/Core.php' ) ) {
	require_once __DIR__ . '/src/Core.php';
}

if ( version_compare( PHP_VERSION, PLUGIN_MINIMUM_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Saltus Framework Demo requires PHP %1$s or newer. This site is running PHP %2$s.', 'framework-demo' ),
						PLUGIN_MINIMUM_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

if ( ! class_exists( \Saltus\WP\Framework\Core::class ) || ! class_exists( Core::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Saltus Framework Demo is missing Composer dependencies. Run composer install from the plugin directory.', 'framework-demo' )
			);
		}
	);
	return;
}

/*
 * The framework needs the plugin root path so it can load the model files.
 */
$framework = new \Saltus\WP\Framework\Core( __DIR__ );
$framework->register();

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new Core( PLUGIN_SLUG, PLUGIN_VERSION, PLUGIN_FILE );
		$plugin->init();
	}
);
