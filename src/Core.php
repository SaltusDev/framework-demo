<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo;

use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Admin\RenamerPage;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Assets;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\I18n;

/**
 * The core class, where logic is defined.
 */
class Core {

	/**
	 * Unique identifier (slug)
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Current version.
	 *
	 * @var string
	 */
	private string $version;

	private string $file_path;

	/**
	 * Setup the class variables
	 *
	 * @param string $name      Plugin name.
	 * @param string $version   Plugin version. Use semver.
	 * @param string $file_path Plugin file path
	 */
	public function __construct( string $name, string $version, string $file_path ) {
		$this->name      = $name;
		$this->version   = $version;
		$this->file_path = $file_path;
	}

	/**
	 * Get the identifier, also used for i18n domain.
	 *
	 * @return string The unique identifier (slug)
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the current version.
	 *
	 * @return string The current version.
	 */
	public function get_version(): string {
		return $this->version;
	}

	public function get_file_path(): string {
		return $this->file_path;
	}

	public function get_dir_path(): string {
		return plugin_dir_path( $this->file_path );
	}

	public function get_dir_url(): string {
		return plugin_dir_url( $this->file_path );
	}

	/**
	 * Start the logic for this plugins.
	 *
	 * Runs on 'plugins_loaded' which is pre- 'init' filter
	 */
	public function init(): void {
		$this->set_locale();
		$this->set_assets();
		$this->set_admin_pages();
	}

	/**
	 * Load translations
	 */
	private function set_locale(): void {
		$i18n = new I18n( $this->name );
		$i18n->load_plugin_textdomain( dirname( $this->file_path ) );
	}

	/**
	 * Load assets
	 */
	private function set_assets(): void {
		$assets = new Assets( $this );
		$assets->load_assets();
	}

	private function set_admin_pages(): void {
		$renamer_page = new RenamerPage( $this );
		$renamer_page->register();
	}
}
