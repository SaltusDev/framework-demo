<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin;

/**
 * Internationalization
 */
class I18n {

	/**
	 * Unique identifier for retrieving translated strings
	 *
	 * @var string
	 */
	private $domain;

	/**
	 * Define the domain.
	 *
	 * @param string $domain    Plugin domain.
	 */
	public function __construct( string $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Load translations.
	 *
	 * @param string $plugin_dirname Plugin directory name, relative to WP_PLUGIN_DIR.
	 */
	public function load_plugin_textdomain( string $plugin_dirname ) {

		load_plugin_textdomain(
			$this->domain,
			false,
			$plugin_dirname . '/languages/'
		);
	}

}
