<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo;

/**
 * Defines the post types and taxonomies
 *
 */
class Loader {


	/**
	 * The plugin's instance.
	 *
	 * @var Plugin
	 */
	protected $core;

	/**
	 * The custom post types.
	 *
	 * @var array
	 */
	protected $post_types = array();

	/**
	 * The custom taxonomies.
	 *
	 * @var array
	 */
	protected $taxonomies = array();

	/**
	 * Define the core functionality of the plugin.
	 *
	 */
	public function __construct( Core $core ) {
		$this->core = $core;

	}

	/**
	 * Register the Post types and taxonomies.
	 *
	 */
	public function run() {
	}
}

