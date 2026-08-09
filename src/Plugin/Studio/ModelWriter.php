<?php
/**
 * Writes generated model files to disk.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * Writes a model config into `src/models/`, with the guards that implies.
 *
 * ## Why this class is mostly guards
 *
 * The framework `include`s every file in `src/models/` on every request. Writing there is therefore
 * arbitrary code execution by design, and a malformed write does not degrade the site — it takes the
 * whole thing down, including the admin screen you would use to undo it.
 *
 * So the write itself is four lines and everything else is refusal:
 *
 * 1. `DISALLOW_FILE_MODS` / `DISALLOW_FILE_EDIT` are honoured. Hosts set these to mean "no code
 *    changes from the dashboard", and this is exactly that.
 * 2. The filename is validated, not sanitised. Silently rewriting a name the caller chose produces a
 *    file they did not ask for, which is worse than a refusal.
 * 3. The resolved path is confined to the models directory by `realpath()` comparison, so `../` and
 *    symlinks cannot escape.
 * 4. The generated source is parsed with `token_get_all( …, TOKEN_PARSE )` before it lands. In-process
 *    rather than `php -l`: a web request may have no PHP binary on `PATH`, and `exec` is often
 *    disabled. Verified that this throws `ParseError` on invalid input.
 * 5. Existing files are only replaced when the caller asks *and* `ModelReader` confirms the target
 *    holds no closure — regenerating a file with a callable in it would silently drop the callable.
 *
 * Writes are atomic: a temp file in the destination directory, then `rename()`. A partial write of a
 * file the framework `include`s would fatal every request until someone fixed it by hand.
 *
 * ## What this class does not do
 *
 * No capability checks and no nonces — those belong to the REST/admin layer, which knows the request.
 * This class is the filesystem boundary, callable from CLI too.
 */
class ModelWriter {

	/*
	 * Every `throw` below carries a developer-facing message built from a filename, a directory path
	 * or a parser error. None is ever rendered to a page: the REST layer maps `WriteRefusedException`
	 * to a `WP_Error` and escapes at output. Disabled for the file rather than repeated as eight
	 * identical `phpcs:ignore` comments, since the justification is the same at every site.
	 *
	 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	 *
	 * The filesystem sniffs are also disabled, and that one is a deliberate design choice rather
	 * than a convenience: `WP_Filesystem` has no atomic write. These files are `include`d by the
	 * framework on every request, so a torn write fatals the whole site — including the admin screen
	 * needed to undo it. Temp-file-then-`rename()` is the only way to make the swap atomic, and
	 * `rename()`/`unlink()`/`is_writable()` have no `WP_Filesystem` equivalents that preserve it.
	 *
	 * phpcs:disable WordPress.WP.AlternativeFunctions.rename_rename
	 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
	 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
	 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_chmod
	 */

	/**
	 * Absolute path to the directory model files may be written to.
	 *
	 * @var string
	 */
	private string $models_dir;

	private ModelPrinter $printer;

	private ModelReader $reader;

	/**
	 * @param string       $models_dir Absolute path to `src/models/`.
	 * @param ModelPrinter $printer    Printer for the config.
	 * @param ModelReader  $reader     Reader, used to check overwrite safety.
	 */
	public function __construct( string $models_dir, ModelPrinter $printer, ModelReader $reader ) {
		$this->models_dir = rtrim( $models_dir, '/\\' );
		$this->printer    = $printer;
		$this->reader     = $reader;
	}

	/**
	 * Write a model to `src/models/<filename>`.
	 *
	 * @param array<string, mixed> $model     Model config.
	 * @param string               $filename  Target filename, e.g. `post-type-book.php`.
	 * @param bool                 $overwrite Whether replacing an existing file is permitted.
	 * @param string               $docblock  Optional file-level description.
	 * @param string               $gate_slug Optional toggle slug for the opt-in gate.
	 * @return string Absolute path written.
	 * @throws WriteRefusedException When any guard rejects the write.
	 * @throws UnrepresentableValueException When the config holds a closure or object.
	 */
	public function write(
		array $model,
		string $filename,
		bool $overwrite = false,
		string $docblock = '',
		string $gate_slug = ''
	): string {
		$this->assert_writes_allowed();

		$path = $this->resolve_path( $filename );

		if ( file_exists( $path ) ) {
			$this->assert_replaceable( $path, $overwrite );
		}

		// Throws UnrepresentableValueException for a closure, which is the correct outcome: better to
		// refuse than to write a file that looks complete and has lost a callable.
		$source = $this->printer->print_file( $model, $docblock, $gate_slug );

		$this->assert_parses( $source );
		$this->write_atomically( $path, $source );

		return $path;
	}

	/**
	 * Render the file without writing it, so a caller can offer a download or a preview.
	 *
	 * @param array<string, mixed> $model     Model config.
	 * @param string               $docblock  Optional file-level description.
	 * @param string               $gate_slug Optional toggle slug.
	 * @throws WriteRefusedException When the generated source does not parse.
	 * @throws UnrepresentableValueException When the config holds a closure or object.
	 */
	public function render( array $model, string $docblock = '', string $gate_slug = '' ): string {
		$source = $this->printer->print_file( $model, $docblock, $gate_slug );

		$this->assert_parses( $source );

		return $source;
	}

	/**
	 * Refuse when WordPress says code must not change.
	 *
	 * @throws WriteRefusedException When either constant is set.
	 */
	private function assert_writes_allowed(): void {
		foreach ( array( 'DISALLOW_FILE_MODS', 'DISALLOW_FILE_EDIT' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				throw WriteRefusedException::disallowed( $constant );
			}
		}
	}

	/**
	 * Validate the filename and confine it to the models directory.
	 *
	 * Validates rather than sanitises: `sanitize_file_name()` would quietly turn `../evil.php` into
	 * something writable, producing a file the caller never asked for.
	 *
	 * @param string $filename Proposed filename.
	 * @return string Absolute destination path.
	 * @throws WriteRefusedException When the name is unsafe or escapes the directory.
	 */
	private function resolve_path( string $filename ): string {
		if ( $filename !== basename( $filename ) ) {
			throw WriteRefusedException::bad_name(
				'The filename must not contain a path. Pass a bare filename such as post-type-book.php.'
			);
		}

		/*
		 * `\z`, not `$`. In PCRE `$` also matches immediately before a final newline, and `basename()`
		 * preserves one — so `a.php\n` was accepted and created. That file is then invisible to `*.php`
		 * globs, hence unseen by `saltus_model_scan()`, the linter and any cleanup, while an existence
		 * check for a later legitimate `a.php` looks at a different path entirely.
		 *
		 * `.json` is rejected. The writer only ever emits PHP — `write()` calls `print_file()`
		 * unconditionally — so a `.json` target returned 201 with `written: true` and `models: 0` in the
		 * same response, having put PHP in a file its own reader then rejects as `Invalid JSON: Syntax
		 * error`. The framework collects `.json` from the models directory, so that lands an unparseable
		 * model in the load path. Emitting real JSON is a feature, not a bug fix; refusing is honest until
		 * then.
		 */
		if ( preg_match( '/^[a-z0-9][a-z0-9._-]*\.php\z/', $filename ) !== 1 ) {
			throw WriteRefusedException::bad_name(
				'The filename must be lowercase, start with a letter or digit, contain no newline, and end in .php. JSON models cannot be generated: the printer emits PHP, so a .json target would receive a file the framework cannot parse.'
			);
		}

		// `..` cannot survive the basename and pattern checks, but assert it anyway: this is the
		// guard whose failure would be worst, and it costs nothing.
		if ( strpos( $filename, '..' ) !== false ) {
			throw WriteRefusedException::bad_name( 'The filename must not contain "..".' );
		}

		$root = realpath( $this->models_dir );

		if ( $root === false || ! is_dir( $root ) ) {
			throw WriteRefusedException::outside_root(
				'The models directory does not exist: ' . $this->models_dir
			);
		}

		$path = $root . DIRECTORY_SEPARATOR . $filename;

		/*
		 * For an existing file, compare resolved paths — a symlink pointing outside the plugin would
		 * otherwise pass every check above and then be followed by the write.
		 */
		if ( file_exists( $path ) ) {
			$resolved = realpath( $path );

			if ( $resolved === false || strpos( $resolved, $root . DIRECTORY_SEPARATOR ) !== 0 ) {
				throw WriteRefusedException::outside_root(
					'The target resolves outside the models directory, probably through a symlink.'
				);
			}

			return $resolved;
		}

		return $path;
	}

	/**
	 * Decide whether an existing file may be replaced.
	 *
	 * @param string $path      Absolute path to the existing file.
	 * @param bool   $overwrite Whether the caller asked to overwrite.
	 * @throws WriteRefusedException When replacing is not permitted.
	 */
	private function assert_replaceable( string $path, bool $overwrite ): void {
		if ( ! $overwrite ) {
			throw WriteRefusedException::exists( basename( $path ) );
		}

		if ( ! is_writable( $path ) ) {
			throw WriteRefusedException::io( sprintf( '%s is not writable.', basename( $path ) ) );
		}

		/*
		 * The refusal that matters most. A model file holding a closure cannot be regenerated from
		 * config: `book`, `event` and `recipe` all put callables in `admin_cols`/`callback`, and
		 * rewriting them from a config round-trip would drop the callable while leaving a file that
		 * looks complete.
		 *
		 * This also covers a file the reader could not see into at all — one that returned an empty
		 * array, most importantly an opt-in model whose toggle is off. At shipped defaults that is nine
		 * of the fourteen files, so "no models" must count as a refusal rather than as safe.
		 */
		if ( ! $this->reader->can_overwrite( $path ) ) {
			throw WriteRefusedException::has_closure( basename( $path ) );
		}
	}

	/**
	 * Parse the generated source before it can reach disk.
	 *
	 * In-process via `token_get_all( …, TOKEN_PARSE )` rather than shelling out to `php -l`: in a web
	 * request the PHP binary may not be on `PATH` and `exec` is frequently disabled, and a syntax
	 * check that silently does not run is worse than none.
	 *
	 * @param string $source Generated PHP.
	 * @throws WriteRefusedException When the source does not parse.
	 */
	private function assert_parses( string $source ): void {
		try {
			token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $error ) {
			throw WriteRefusedException::invalid_syntax( $error->getMessage() );
		}
	}

	/**
	 * Write through a temp file and rename.
	 *
	 * `rename()` within one filesystem is atomic, so a reader either sees the old file or the new one.
	 * A plain `file_put_contents()` can be observed half-written, and the framework `include`s these
	 * files on every request — a truncated one fatals the site.
	 *
	 * @param string $path   Destination.
	 * @param string $source Contents.
	 * @throws WriteRefusedException When the write or rename fails.
	 */
	private function write_atomically( string $path, string $source ): void {
		$directory = dirname( $path );

		if ( ! is_writable( $directory ) ) {
			throw WriteRefusedException::io( 'The models directory is not writable: ' . $directory );
		}

		$temp = tempnam( $directory, '.saltus-studio-' );

		if ( $temp === false ) {
			throw WriteRefusedException::io( 'Could not create a temporary file in ' . $directory );
		}

		if ( file_put_contents( $temp, $source ) === false ) {
			unlink( $temp );
			throw WriteRefusedException::io( 'Could not write the temporary file.' );
		}

		// Match the directory's permissions rather than tempnam()'s restrictive 0600, or the web
		// server may be unable to read the file it just wrote.
		$mode = fileperms( $directory );

		if ( $mode !== false ) {
			chmod( $temp, $mode & 0666 );
		}

		if ( ! rename( $temp, $path ) ) {
			unlink( $temp );
			throw WriteRefusedException::io( 'Could not move the generated file into place.' );
		}
	}
}
