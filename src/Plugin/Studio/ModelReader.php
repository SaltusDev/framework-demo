<?php
/**
 * Reads a model configuration file back into an array.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * Loads a model config file and reports what cannot survive a round-trip.
 *
 * ## Why `include` rather than a PHP parser
 *
 * A model file returns a real array, so including it yields the exact structure the framework sees —
 * no parser to keep in step with PHP syntax, no partial support for the expressions models actually
 * use. The cost is that the file *runs*, which shapes everything below.
 *
 * ## What that costs
 *
 * Model files are not inert data. They call `__()` throughout, `plugin_dir_url()` in `recipe`, and
 * `get_option()` through `saltus_demo_model_enabled()` in every opt-in model. Two consequences:
 *
 * - **Requires WordPress.** Inside a request every function exists. Outside one, the caller must
 *   provide stubs — `bin/model-loader-stubs.php` is the CLI's version of exactly that.
 * - **Function-derived values arrive resolved, not preserved.** `plugin_dir_url()` reads back as an
 *   absolute URL for whichever install ran the reader. Writing that back would bake a machine-specific
 *   path into a config file, so such values are reported as non-portable and never silently rewritten.
 *
 * ## The refusal
 *
 * A closure has no source representation at runtime, so a config containing one cannot be printed
 * back. `can_overwrite()` therefore returns false for those files and callers must export elsewhere.
 * The alternative — writing the file anyway, minus the callable — produces something that looks
 * complete and has quietly lost a feature.
 */
class ModelReader {

	/**
	 * Values that look like they came from a path or URL helper rather than a literal.
	 *
	 * Not a security check; a heuristic for "this string is about *this* machine". Anything matching
	 * is reported as non-portable so a caller does not write it into a shared config file.
	 *
	 * @var list<string>
	 */
	private const NON_PORTABLE_PATTERNS = array(
		'#^https?://#i',
		'#^(/|[A-Za-z]:\\\\)#',
	);

	/**
	 * Read one model file.
	 *
	 * @param string $path Absolute path to a `.php` or `.json` model file.
	 * @return array{
	 *     models: list<array<string, mixed>>,
	 *     closures: list<string>,
	 *     non_portable: list<string>,
	 *     multiple: bool,
	 *     errors: list<string>
	 * }
	 */
	public function read( string $path ): array {
		$result = array(
			'models'       => array(),
			'closures'     => array(),
			'non_portable' => array(),
			'multiple'     => false,
			'errors'       => array(),
		);

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			$result['errors'][] = 'File is missing or unreadable: ' . $path;

			return $result;
		}

		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( $extension === 'yml' || $extension === 'yaml' ) {
			// Modeler::load() collects these extensions, but the framework ships no YAML parser
			// (symfony/yaml is not a dependency), so such a file silently fails to parse there too.
			$result['errors'][] = 'YAML models cannot be read: the framework lists .yml/.yaml but symfony/yaml is not installed.';

			return $result;
		}

		$config = $extension === 'json'
			? $this->read_json( $path, $result['errors'] )
			: $this->read_php( $path, $result['errors'] );

		if ( $config === null ) {
			return $result;
		}

		$result['closures']     = $this->find_closures( $config );
		$result['non_portable'] = $this->find_non_portable( $config );

		if ( $config === array() ) {
			/*
			 * An opt-in model whose gate is closed, the toggle helper, or a broken file. The framework
			 * soft-fails all three identically, so the reader cannot tell them apart — and that
			 * ambiguity is exactly why this must not read as "safe".
			 *
			 * Recorded as an error rather than returning quietly. At shipped defaults the nine opt-in
			 * models are *off*, so `post-type-recipe.php` — 12 kB, closure included — returned `array()`
			 * here with empty `closures` and empty `errors`, and `can_overwrite()` therefore said yes.
			 * A single write replaced the whole file with a 62-byte stub. The closure guard failed in
			 * precisely the configuration users run.
			 */
			$result['errors'][] = 'The file returned an empty array, so its real configuration cannot be seen: either an opt-in model whose toggle is off, a helper that declares no model, or a broken file. Overwriting would discard whatever it actually contains.';

			return $result;
		}

		/*
		 * `Modeler::is_multiple()` decides a whole file's shape from `is_array( current( $config ) )`
		 * — the FIRST element only. Mirrored here so the reader reports what the framework will
		 * actually do, not what the file appears to mean.
		 */
		$result['multiple'] = is_array( current( $config ) );

		if ( ! $result['multiple'] ) {
			$result['models'][] = $config;

			return $result;
		}

		if ( isset( $config['type'] ) && ! is_array( $config['type'] ) ) {
			// One model whose first key happens to hold an array: the framework shreds it into
			// "models" that are really its own config sections, finds no `type` in them, and
			// registers nothing.
			$result['errors'][] = sprintf(
				'Single model misparsed as a list: the first key ("%s") holds an array, so Modeler::is_multiple() treats the file as a list of models and drops it entirely. Move a scalar key ("type") first.',
				(string) array_key_first( $config )
			);

			return $result;
		}

		foreach ( $config as $key => $model ) {
			if ( ! is_array( $model ) ) {
				$result['errors'][] = sprintf( 'Element "%s" is not a model array and will be skipped.', (string) $key );
				continue;
			}

			$result['models'][] = $model;
		}

		return $result;
	}

	/**
	 * Whether a file can be safely regenerated in place.
	 *
	 * False in three cases, each one a way that writing would lose something a reader cannot recover:
	 *
	 * - **A closure.** Printing it back drops the callable, leaving a file that looks complete.
	 * - **An unreadable or empty result** (`errors`), which includes an opt-in model whose toggle is off.
	 *   The reader cannot see what is really in the file, so it cannot claim replacing it is safe.
	 * - **More than one model.** `ModelWriter::write()` prints exactly one, so regenerating
	 *   `taxonomy-multiple.php` — which declares `genre`, `writer` and `country` — would keep one and
	 *   silently delete two. Found by running the corpus round-trip at shipped defaults rather than by
	 *   reading: the file has no closure and no errors, so every other guard passed it.
	 *
	 * Callers should offer "export as a new file" instead of overwriting.
	 *
	 * @param string $path Absolute path to a model file.
	 */
	public function can_overwrite( string $path ): bool {
		$result = $this->read( $path );

		return $result['errors'] === array()
			&& $result['closures'] === array()
			&& count( $result['models'] ) === 1;
	}

	/**
	 * Decode a JSON model.
	 *
	 * @param string       $path   Absolute path.
	 * @param list<string> $errors Collected errors, by reference.
	 * @return array<string|int, mixed>|null
	 */
	private function read_json( string $path, array &$errors ): ?array {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Reading a bundled config file, not a remote resource.

		if ( ! is_string( $contents ) ) {
			$errors[] = 'Could not read the file.';

			return null;
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			$errors[] = 'Invalid JSON: ' . $exception->getMessage();

			return null;
		}

		if ( ! is_array( $decoded ) ) {
			$errors[] = 'A JSON model must decode to an object.';

			return null;
		}

		return $decoded;
	}

	/**
	 * Include a PHP model.
	 *
	 * The file executes: it will call `__()`, and possibly `get_option()` and `plugin_dir_url()`.
	 * Those must already exist, whether from WordPress or from a stub layer.
	 *
	 * @param string       $path   Absolute path.
	 * @param list<string> $errors Collected errors, by reference.
	 * @return array<string|int, mixed>|null
	 */
	private function read_php( string $path, array &$errors ): ?array {
		try {
			$config = include $path;
		} catch ( \Throwable $exception ) {
			$errors[] = sprintf(
				'Threw while loading (%s): %s. Model files call WordPress functions at include time, so they need WordPress or a stub layer.',
				get_debug_type( $exception ),
				$exception->getMessage()
			);

			return null;
		}

		if ( ! is_array( $config ) ) {
			$errors[] = 'A model file must return an array; got ' . get_debug_type( $config ) . '.';

			return null;
		}

		return $config;
	}

	/**
	 * Find every closure in a config tree.
	 *
	 * @param array<string|int, mixed> $config Config tree.
	 * @param string                   $path   Dotted path prefix.
	 * @return list<string> Dotted paths.
	 */
	private function find_closures( array $config, string $path = '' ): array {
		$found = array();

		foreach ( $config as $key => $value ) {
			$child = $path === '' ? (string) $key : $path . '.' . (string) $key;

			if ( $value instanceof \Closure ) {
				$found[] = $child;
				continue;
			}

			if ( is_array( $value ) ) {
				$found = array_merge( $found, $this->find_closures( $value, $child ) );
			}
		}

		return $found;
	}

	/**
	 * Find values that look machine-specific rather than authored.
	 *
	 * @param array<string|int, mixed> $config Config tree.
	 * @param string                   $path   Dotted path prefix.
	 * @return list<string> Dotted paths.
	 */
	private function find_non_portable( array $config, string $path = '' ): array {
		$found = array();

		foreach ( $config as $key => $value ) {
			$child = $path === '' ? (string) $key : $path . '.' . (string) $key;

			if ( is_array( $value ) ) {
				$found = array_merge( $found, $this->find_non_portable( $value, $child ) );
				continue;
			}

			if ( ! is_string( $value ) ) {
				continue;
			}

			foreach ( self::NON_PORTABLE_PATTERNS as $pattern ) {
				if ( preg_match( $pattern, $value ) === 1 ) {
					$found[] = $child;
					break;
				}
			}
		}

		return $found;
	}
}
