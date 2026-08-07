<?php
/**
 * Injects opt-in models that cannot gate themselves.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin;

/**
 * Adds non-PHP demo models through the framework's `extra_models` filter.
 *
 * ## Why the JSON model needs this
 *
 * Every PHP model in `src/models/` gates itself with `saltus_demo_model_enabled()` and returns an
 * empty array when disabled — `ModelFactory::create()` soft-fails a config with no `type` key. A
 * JSON model cannot call a PHP function, and the framework's `active` key does not work
 * (`BaseModel::is_disabled()` treats `false` as "not disabled"; see `src/models/_demo-toggle.php`).
 *
 * So `snippet.json` lives in `src/models-optional/`, outside the directory the `Modeler` scans, and
 * is injected here only when its toggle is on. This is the filter's intended purpose — it receives
 * an empty array specifically so plugins can *append* models.
 *
 * The demo value is unchanged: the model is still declared entirely in JSON, proving the loader
 * accepts the format. Only the enable/disable decision moved into PHP.
 */
class OptionalModels {

	/**
	 * Optional models, keyed by their settings toggle slug.
	 *
	 * @var array<string, string> Toggle slug => file name in `src/models-optional/`.
	 */
	private const OPTIONAL_MODELS = array(
		'snippet' => 'snippet.json',
	);

	/**
	 * Absolute path to the plugin's optional-models directory.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * @param string $directory Absolute path to `src/models-optional/`.
	 */
	public function __construct( string $directory ) {
		$this->directory = rtrim( $directory, '/\\' );
	}

	/**
	 * Register the filter.
	 */
	public function register(): void {
		add_filter( 'saltus/framework/models/extra_models', array( $this, 'add_enabled_models' ) );
	}

	/**
	 * Append every enabled optional model to the framework's model list.
	 *
	 * @param mixed $models Models collected so far (an empty array from the framework).
	 * @return mixed
	 */
	public function add_enabled_models( $models ) {
		if ( ! is_array( $models ) ) {
			$models = array();
		}

		foreach ( self::OPTIONAL_MODELS as $toggle => $file_name ) {
			if ( ! saltus_demo_model_enabled( $toggle ) ) {
				continue;
			}

			$config = $this->read_model( $file_name );
			if ( $config !== null ) {
				$models[] = $config;
			}
		}

		return $models;
	}

	/**
	 * Decode one optional model file.
	 *
	 * @param string $file_name File name within the optional-models directory.
	 * @return array<string, mixed>|null Null when unreadable or malformed.
	 */
	private function read_model( string $file_name ): ?array {
		$path = $this->directory . '/' . $file_name;

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin file, not a remote resource.
		if ( ! is_string( $contents ) ) {
			return null;
		}

		$decoded = json_decode( $contents, true );

		return is_array( $decoded ) && isset( $decoded['type'] ) ? $decoded : null;
	}
}
