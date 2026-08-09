<?php
/**
 * Locates the generated model-schema artifacts.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * Single place that knows where `saltus/model-schema` lives.
 *
 * The artifacts are generated into `schema/` inside this plugin today, and are intended to become a
 * standalone Composer package (see `schema/README.md`). Everything that reads them goes through here, so
 * that move is a change to `directory()` rather than a hunt through six call sites.
 *
 * The lookup already prefers `vendor/saltus/model-schema/` when present, so the published package can be
 * dropped in and picked up without touching any consumer.
 */
class Schema {

	/** Path relative to the plugin root where the generator writes. */
	private const IN_REPO_DIRECTORY = 'schema';

	/** Path relative to the plugin root where the published package would install. */
	private const PACKAGE_DIRECTORY = 'vendor/saltus/model-schema';

	private string $plugin_dir;

	/**
	 * @param string $plugin_dir Absolute path to the plugin root, with or without a trailing slash.
	 */
	public function __construct( string $plugin_dir ) {
		$this->plugin_dir = rtrim( $plugin_dir, '/\\' );
	}

	/**
	 * Directory holding the artifacts.
	 *
	 * Prefers the installed package so extraction needs no consumer change; falls back to the in-repo
	 * directory the generator writes to.
	 */
	public function directory(): string {
		$package = $this->plugin_dir . '/' . self::PACKAGE_DIRECTORY;

		if ( is_dir( $package ) ) {
			return $package;
		}

		return $this->plugin_dir . '/' . self::IN_REPO_DIRECTORY;
	}

	/**
	 * Absolute path to the JSON Schema.
	 */
	public function json_path(): string {
		return $this->directory() . '/model.schema.json';
	}

	/**
	 * Absolute path to the PHP enum map.
	 */
	public function enums_path(): string {
		return $this->directory() . '/model-enums.php';
	}

	/**
	 * The generated enum map, or an empty array when it has never been generated.
	 *
	 * Returning empty rather than throwing is deliberate: a missing map degrades the *quality* of
	 * validation without breaking the plugin, and the Studio screen already reports when the schema has
	 * not been generated. A fatal on a missing generated file would be a worse failure than a warning.
	 *
	 * @return array<string, mixed>
	 */
	public function enums(): array {
		$path = $this->enums_path();

		if ( ! is_file( $path ) ) {
			return array();
		}

		$enums = require $path;

		return is_array( $enums ) ? $enums : array();
	}
}
