<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin;

use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;

/**
 * Manage Assets like scripts and styles.
 */
class Assets {

	/**
	 * The plugin's instance.
	 *
	 * @var Core
	 */
	private Core $core;

	/**
	 * Define Assets
	 *
	 * @param Core $core This plugin's instance.
	 */
	public function __construct( Core $core ) {
		$this->core = $core;
	}

	/**
	 * Load assets.
	 *
	 */
	public function load_assets(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_styles' ) );

		add_filter( 'script_loader_src', array( $this, 'correct_framework_asset_url' ), 10, 1 );
		add_filter( 'style_loader_src', array( $this, 'correct_framework_asset_url' ), 10, 1 );
	}

	/**
	 * Point framework asset URLs at the Strauss target directory.
	 *
	 * The framework builds its asset base as `vendor/saltus/framework/assets/`, but this plugin
	 * prefixes the framework into `vendor-prefixed/` with `delete_vendor_packages` enabled, so
	 * nothing remains under `vendor/saltus/`. Every framework-enqueued script and style would
	 * otherwise resolve to a WordPress 404 page served as text/html.
	 *
	 * The framework hardcodes that base with no filter of its own, so the correction has to
	 * happen at the WordPress enqueue layer. Remove this once the framework derives its asset
	 * base from the installed package location.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public function correct_framework_asset_url( $src ) {
		if ( ! is_string( $src ) || ! str_contains( $src, '/vendor/saltus/framework/assets/' ) ) {
			return $src;
		}

		return str_replace(
			'/vendor/saltus/framework/assets/',
			'/vendor-prefixed/saltus/framework/assets/',
			$src
		);
	}

	/**
	 * Load the small design layer used by the demo's frontend templates.
	 */
	public function load_frontend_styles(): void {
		wp_enqueue_style(
			$this->core->get_name() . '_frontend',
			plugins_url( 'assets/css/frontend.css', $this->core->get_file_path() ),
			array(),
			$this->core->get_version()
		);
	}

	/**
	 * Load assets
	 *
	 */
	public function load_admin_styles( string $hook_suffix = '' ): void {
		if ( ! $hook_suffix || ! str_contains( $hook_suffix, $this->core->get_name() ) ) {
			return;
		}

		wp_register_style(
			$this->core->get_name() . '_admin',
			plugins_url( 'assets/css/admin-style.css', $this->core->get_file_path() ),
			array(),
			$this->core->get_version()
		);

		wp_enqueue_style( $this->core->get_name() . '_admin' );
	}
}
