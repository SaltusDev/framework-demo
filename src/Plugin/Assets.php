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
	}

	/**
	 * Load assets
	 *
	 */
	public function load_admin_styles( string $hook_suffix = '' ): void {
		if ( ! $hook_suffix || ! str_contains( $hook_suffix, 'framework-demo' ) ) {
			return;
		}

		wp_register_style(
			$this->core->get_name() . '_admin',
			plugins_url( 'assets/css/admin-style.css', $this->core->get_file_path() ),
			false,
			$this->core->get_version()
		);

		wp_enqueue_style( $this->core->get_name() . '_admin' );
	}
}
