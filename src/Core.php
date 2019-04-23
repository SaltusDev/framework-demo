<?php
namespace Saltus\WP\Plugin\Saltus\PluginFramework;

/**
 * The core class, where logic is defined.
 */
class Core {

	/**
	 * Unique identifier (slug)
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Current version.
	 *
	 * @var string
	 */
	public $version;
	public $file_path;

	public $framework;

	/**
	 * Setup the class variables
	 *
	 * @param string $name      Plugin name.
	 * @param string $version   Plugin version. Use semver.
	 * @param string $file_path Plugin file path
	 * @param string $saltus    Saltus Framework
	 */
	public function __construct( string $name, string $version, string $file_path, $framework ) {
		$this->name      = $name;
		$this->version   = $version;
		$this->file_path = $file_path;
		$this->framework = $framework;
		$this->framework = 'aaa';
	}

	/**
	 * Get the identifier, also used for i18n domain.
	 *
	 * @return string The unique identifier (slug)
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the current version.
	 *
	 * @return string The current version.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Start the logic for this plugins.
	 *
	 * Runs on 'plugins_loaded'.
	 */
	public function init() {

		$this->set_locale();

		$this->add_assets();

		$this->run_loader();

	}

	/**
	 * Load translations
	 */
	private function set_locale() {
		$i18n = new Plugin\I18n( $this->name );
		$i18n->load_plugin_textdomain( dirname( $this->file_path ) );
	}

	/**
	 * Load assets
	 */
	private function add_assets() {
		$assets = new Plugin\Assets( $this );
		$assets->load_assets();
	}

}
