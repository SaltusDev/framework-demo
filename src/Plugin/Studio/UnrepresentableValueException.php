<?php
/**
 * Thrown when a config value cannot be rendered as PHP source.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * A config value the printer refuses to render.
 *
 * Closures are the real case: `book` and `event` put one in `admin_cols.function`, and `recipe` uses
 * a `callback` field. A closure has no source representation available at runtime, so the printer
 * cannot emit it.
 *
 * The important part is that this is an exception rather than a fallback. Rendering `null`, or
 * omitting the key, or emitting a placeholder string would all produce a file that looks complete
 * and has quietly lost a feature — the one failure mode a code generator cannot apologise its way
 * out of. Refusing loudly leaves the caller to offer "export as a new file" instead.
 */
class UnrepresentableValueException extends \RuntimeException {

	/**
	 * Dotted path to the offending value.
	 *
	 * @var string
	 */
	public readonly string $path;

	/**
	 * Type of the offending value, as reported by `get_debug_type()`.
	 *
	 * @var string
	 */
	public readonly string $value_type;

	/**
	 * @param string      $path       Dotted path to the value.
	 * @param string      $value_type Debug type of the value.
	 * @param string|null $advice     Why it cannot be rendered, and what to do. Defaults to the closure case.
	 */
	public function __construct( string $path, string $value_type, ?string $advice = null ) {
		$this->path       = $path;
		$this->value_type = $value_type;

		$advice ??= 'Closures and objects have no source representation, so this config is export-only — write it to a new file and port the callable by hand.';

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message built from a config path and a type name; never rendered to a page.
		parent::__construct(
			sprintf(
				'Cannot render %s at "%s" as PHP. %s',
				$value_type,
				$path === '' ? '(root)' : $path,
				$advice
			)
		);
	}

	/**
	 * A float with no PHP literal: NAN or INF.
	 *
	 * Refused rather than rendered because the previous behaviour was silent corruption:
	 * `json_encode()` returns `false` for both, and the `.0` suffix logic turned that into the literal
	 * `.0`, which parses as zero. Reachable from ordinary input — `json_decode( '{"n": 1e400}' )` yields
	 * `INF` and the REST `model` parameter is decoded JSON.
	 *
	 * @param string $path  Dotted path to the value.
	 * @param float  $value The non-finite value.
	 */
	public static function non_finite_float( string $path, float $value ): self {
		return new self(
			$path,
			is_nan( $value ) ? 'NAN' : ( $value > 0 ? 'INF' : '-INF' ),
			'PHP has no literal for a non-finite float. Emitting one silently produced 0.0, so it is refused instead. Use a finite number, or omit the key.'
		);
	}

	/**
	 * A model whose first key holds an array.
	 *
	 * `Modeler::is_multiple()` is `is_array( current( $config ) )`, so the first key alone decides how the
	 * framework reads the entire file. Writing this produces config that registers nothing, silently.
	 *
	 * @param string $key The offending first key.
	 */
	public static function array_first_key( string $key ): self {
		return new self(
			$key,
			'an array as the model\'s first key',
			'Modeler::is_multiple() decides a file\'s shape from its first element, so this would be read as a list of models — none with a "type" key — and registered as nothing. Put a scalar key ("type") first.'
		);
	}
}
