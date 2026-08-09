<?php
/**
 * Thrown when a model file write is refused.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * A refused write, carrying a machine-readable reason.
 *
 * Every guard in `ModelWriter` throws this rather than returning false, so a caller cannot ignore a
 * failure by forgetting to check a return value. The `reason` code is what the REST layer maps to an
 * error code and HTTP status; the message is for a developer reading a log.
 *
 * Each refusal is a named static factory rather than an inline `new`. That keeps every reason code
 * beside the wording it produces — and since `WordPress.Security.EscapeOutput.ExceptionNotEscaped`
 * fires on any `throw new` whose argument is not a literal, it also confines the sniff to this one
 * file instead of scattering twenty `phpcs:ignore` comments across the writer.
 */
class WriteRefusedException extends \RuntimeException {

	/** Writing is disabled by a WordPress constant. */
	public const REASON_DISALLOWED = 'writes_disallowed';

	/** The filename is unsafe or has the wrong extension. */
	public const REASON_BAD_NAME = 'invalid_filename';

	/** The resolved path escapes the allowed directory. */
	public const REASON_OUTSIDE_ROOT = 'path_outside_root';

	/** The target exists and overwriting was not requested. */
	public const REASON_EXISTS = 'file_exists';

	/** The target holds a closure, so regenerating it would lose the callable. */
	public const REASON_HAS_CLOSURE = 'target_has_closure';

	/** The generated source is not valid PHP. */
	public const REASON_INVALID_SYNTAX = 'invalid_syntax';

	/** The filesystem rejected the write. */
	public const REASON_IO = 'write_failed';

	/**
	 * Machine-readable reason code.
	 *
	 * @var string
	 */
	public readonly string $reason;

	/**
	 * @param string $reason  One of the REASON_* constants.
	 * @param string $message Developer-facing explanation.
	 */
	public function __construct( string $reason, string $message ) {
		$this->reason = $reason;

		parent::__construct( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message; the REST layer escapes before output.
	}

	/**
	 * A WordPress constant forbids code changes.
	 *
	 * @param string $constant The constant that is set.
	 */
	public static function disallowed( string $constant ): self {
		return new self(
			self::REASON_DISALLOWED,
			sprintf(
				'%s is set, so this site does not permit writing plugin code. Download the generated file and deploy it instead.',
				$constant
			)
		);
	}

	/**
	 * The filename is unusable.
	 *
	 * @param string $detail What is wrong with it.
	 */
	public static function bad_name( string $detail ): self {
		return new self( self::REASON_BAD_NAME, $detail );
	}

	/**
	 * The path resolves outside the models directory.
	 *
	 * @param string $detail What was rejected.
	 */
	public static function outside_root( string $detail ): self {
		return new self( self::REASON_OUTSIDE_ROOT, $detail );
	}

	/**
	 * The target exists and no overwrite was requested.
	 *
	 * @param string $filename Target filename.
	 */
	public static function exists( string $filename ): self {
		return new self(
			self::REASON_EXISTS,
			sprintf( '%s already exists. Pass overwrite to replace it.', $filename )
		);
	}

	/**
	 * The target holds a callable that a regeneration would drop.
	 *
	 * @param string $filename Target filename.
	 */
	public static function has_closure( string $filename ): self {
		return new self(
			self::REASON_HAS_CLOSURE,
			sprintf(
				'%s cannot be regenerated in place: it holds a closure, declares more than one model, or could not be read (an opt-in model whose toggle is off reads as empty). Overwriting would silently drop code or models. Write to a new filename instead.',
				$filename
			)
		);
	}

	/**
	 * The generated source does not parse.
	 *
	 * @param string $detail Parser message.
	 */
	public static function invalid_syntax( string $detail ): self {
		return new self(
			self::REASON_INVALID_SYNTAX,
			'The generated file is not valid PHP and was not written: ' . $detail
		);
	}

	/**
	 * The filesystem refused the operation.
	 *
	 * @param string $detail What failed.
	 */
	public static function io( string $detail ): self {
		return new self( self::REASON_IO, $detail );
	}
}
