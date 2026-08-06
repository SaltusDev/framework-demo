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
