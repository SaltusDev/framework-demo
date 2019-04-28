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
	public $core;

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
	public function load_assets() {

		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_styles' ) );
	}

	/**
	 * Load assets
	 *
	 */
	public function load_admin_styles() {
		wp_register_style(
			$this->core->name . '_admin',
			plugins_url( 'assets/css/admin-style.css', $this->core->file_path ),
			false,
			$this->core->version
		);

		wp_enqueue_style( $this->core->name . '_admin' );
	}
}
