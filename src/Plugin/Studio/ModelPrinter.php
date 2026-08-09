<?php
/**
 * Renders a model configuration array as PHP source.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * Prints model config as a PHP model file.
 *
 * JSON is canonical and PHP is a one-way render target, so this class has no inverse: see
 * `ModelReader` for the lossy direction and why it refuses to overwrite files containing closures.
 *
 * Three properties matter, in this order:
 *
 * 1. **Correctness.** The first emitted key must be scalar. `Modeler::is_multiple()` decides a whole
 *    file's shape from `is_array( current( $config ) )`, so a model whose first key holds an array is
 *    parsed as a *list* of models, matches nothing, and is dropped with no error. Key order is a
 *    correctness requirement here, not a formatting preference.
 * 2. **Determinism.** Same input, same bytes, always. Generated PHP gets committed; if the output
 *    churns between runs, the first noisy diff makes people stop using the tool.
 * 3. **PHPCS cleanliness.** WPCS aligns `=>` within each array block and wants tabs, so the printer
 *    emits both deliberately.
 */
class ModelPrinter {

	/**
	 * Canonical top-level key order.
	 *
	 * Derived from the shipped corpus rather than invented: across the 15 models, `type` averages
	 * position 0.1 and `name` 1.1, then labels/options/features/meta/settings trail off. `$schema` and
	 * `type` lead because both are scalar, which is what keeps `is_multiple()` returning false.
	 *
	 * Keys absent from this list are emitted afterwards in their original order, so an unrecognised
	 * key is preserved rather than dropped.
	 *
	 * @var list<string>
	 */
	private const KEY_ORDER = array(
		'$schema',
		'type',
		'name',
		'active',
		'supports',
		'block_editor',
		'labels',
		'associations',
		'options',
		'features',
		'meta',
		'settings',
		'blocks',
		'frontend',
		'ai_context',
	);

	/**
	 * Leaf keys whose string values are user-facing and therefore translatable.
	 *
	 * Matched on the leaf key rather than the full path, because Codestar nests fields to arbitrary
	 * depth (`repeater` → `fields` → `group` → `fields` → …) and a path list could not keep up.
	 *
	 * Deliberately excluded: `text_domain` (it *is* the domain), `icon`, `id`, `column_name`, every
	 * slug and template path, and all of `ai_context` — those are instructions to AI clients and
	 * machine identifiers, not interface copy.
	 *
	 * @var list<string>
	 */
	private const TRANSLATABLE_KEYS = array(
		'attr_title',
		'desc',
		'has_many',
		'has_one',
		'help',
		'label',
		'menu_title',
		'subtitle',
		'title',
	);

	/**
	 * Paths whose every string leaf is translatable.
	 *
	 * `labels.overrides.*` holds four sub-maps of pure interface copy (`labels`, `messages`,
	 * `bulk_messages`, `ui`) whose keys are WordPress's own, so no leaf-key list would cover them.
	 *
	 * @var list<string>
	 */
	private const TRANSLATABLE_PREFIXES = array(
		'labels.overrides.labels.',
		'labels.overrides.messages.',
		'labels.overrides.bulk_messages.',
		'labels.overrides.ui.',
	);

	private string $text_domain;

	/**
	 * @param string $text_domain Text domain for `__()` calls.
	 */
	public function __construct( string $text_domain ) {
		$this->text_domain = $text_domain;
	}

	/**
	 * Render a complete model file.
	 *
	 * @param array<string, mixed> $model      Model config.
	 * @param string               $docblock   Optional file-level description.
	 * @param string               $gate_slug  Toggle slug for `saltus_demo_model_enabled()`, or ''.
	 * @return string PHP source, ending in a single newline.
	 */
	public function print_file( array $model, string $docblock = '', string $gate_slug = '' ): string {
		$out = "<?php\n";

		if ( $docblock !== '' ) {
			$out .= $this->print_docblock( $docblock );
		}

		if ( $gate_slug !== '' ) {
			/*
			 * The framework's `active` key does not work (`empty( false )` short-circuits
			 * `is_disabled()`), so an opt-in model gates itself and returns an empty array, which
			 * `ModelFactory::create()` soft-fails cleanly.
			 */
			$out .= sprintf(
				"if ( ! saltus_demo_model_enabled( '%s' ) ) {\n\treturn array();\n}\n\n",
				$this->escape_single_quoted( $gate_slug )
			);
		}

		return $out . 'return ' . $this->print_array( $this->sort_keys( $model ), 0, '' ) . ";\n";
	}

	/**
	 * Render just the config expression, without the file scaffolding.
	 *
	 * @param array<string, mixed> $model Model config.
	 */
	public function print_config( array $model ): string {
		return $this->print_array( $this->sort_keys( $model ), 0, '' );
	}

	/**
	 * Reorder top-level keys canonically, preserving unknown keys after the known ones.
	 *
	 * @param array<string, mixed> $model Model config.
	 * @return array<string, mixed>
	 */
	private function sort_keys( array $model ): array {
		$sorted = array();

		foreach ( self::KEY_ORDER as $key ) {
			if ( array_key_exists( $key, $model ) ) {
				$sorted[ $key ] = $model[ $key ];
			}
		}

		foreach ( $model as $key => $value ) {
			if ( ! array_key_exists( $key, $sorted ) ) {
				$sorted[ $key ] = $value;
			}
		}

		$this->assert_scalar_first_key( $sorted );

		return $sorted;
	}

	/**
	 * Enforce the scalar-first-key rule this class documents.
	 *
	 * `sort_keys()` reorders by `KEY_ORDER` alone, which does not by itself guarantee the invariant: a
	 * model whose keys are *all* arrays was written happily and then read back as `multiple=true
	 * models=2 errors=0` — two bogus "models" that are really its own config sections. The reader's own
	 * misparse detection cannot catch that either, since it only fires when a scalar `type` is present.
	 *
	 * `Modeler::is_multiple()` is `is_array( current( $config ) )`, so the very first key decides how the
	 * framework reads the whole file. Refusing is right: writing the file would produce config that
	 * registers nothing, with no error anywhere.
	 *
	 * @param array<string, mixed> $sorted Reordered model config.
	 * @throws UnrepresentableValueException When the first key holds an array.
	 */
	private function assert_scalar_first_key( array $sorted ): void {
		if ( $sorted === array() ) {
			return;
		}

		$first = array_key_first( $sorted );

		if ( ! is_array( $sorted[ $first ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message built from a config key name; never rendered to a page. See UnrepresentableValueException.
		throw UnrepresentableValueException::array_first_key( (string) $first );
	}

	/**
	 * Wrap a description in a file-level docblock.
	 *
	 * The text is **neutralised, not trusted**. `*​/` inside it closes the comment early, and everything
	 * after it becomes executable code in a file the framework `include`s on every request — verified: a
	 * docblock of `Desc *​/ define("PWNED", 1); /* rest` produced a file that defined `PWNED` on include
	 * and sailed through `ModelWriter::assert_parses()`, because the output genuinely *is* valid PHP.
	 * `$gate_slug` has been escaped since it was added; this had no equivalent.
	 *
	 * Rewriting `*​/` rather than rejecting it keeps a legitimate description containing the sequence
	 * usable, and there is no case where the two characters need to reach the output verbatim.
	 */
	private function print_docblock( string $text ): string {
		$out = "/**\n";

		// Also strip NUL and carriage returns: the former can truncate a value in tooling downstream,
		// the latter would leave stray \r inside the comment on a round-trip.
		$text = str_replace( array( "\0", "\r" ), '', $text );

		foreach ( explode( "\n", $text ) as $line ) {
			$line = rtrim( str_replace( '*/', '*&#47;', $line ) );
			$out .= $line === '' ? " *\n" : ' * ' . $line . "\n";
		}

		return $out . " *\n * @package Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo\n */\n\n";
	}

	/**
	 * Render an array literal.
	 *
	 * @param array<string|int, mixed> $value  Array to render.
	 * @param int                      $depth  Current indent depth.
	 * @param string                   $path   Dotted path, for translatability decisions.
	 */
	private function print_array( array $value, int $depth, string $path ): string {
		if ( $value === array() ) {
			return 'array()';
		}

		$pad       = str_repeat( "\t", $depth + 1 );
		$is_list   = array_is_list( $value );
		$alignment = $is_list ? 0 : $this->alignment_width( $value );
		$lines     = array();

		foreach ( $value as $key => $item ) {
			$child = $this->child_path( $path, (string) $key );

			$rendered = is_array( $item )
				? $this->print_array( $item, $depth + 1, $child )
				: $this->print_scalar( $item, $child );

			// WPCS requires a translators comment on any translatable string carrying placeholders,
			// immediately above the call.
			$comment = is_array( $item ) ? '' : $this->translators_comment( $item, $child );

			if ( $comment !== '' ) {
				$lines[] = $pad . $comment;
			}

			if ( $is_list ) {
				$lines[] = $pad . $rendered . ',';
				continue;
			}

			$quoted  = $this->print_key( $key );
			$lines[] = $pad . $quoted . str_repeat( ' ', max( 0, $alignment - strlen( $quoted ) ) ) . ' => ' . $rendered . ',';
		}

		return "array(\n" . implode( "\n", $lines ) . "\n" . str_repeat( "\t", $depth ) . ')';
	}

	/**
	 * WPCS aligns `=>` to one space past the longest key in the block.
	 *
	 * @param array<string|int, mixed> $value Array being rendered.
	 */
	private function alignment_width( array $value ): int {
		$widest = 0;

		foreach ( array_keys( $value ) as $key ) {
			$widest = max( $widest, strlen( $this->print_key( $key ) ) );
		}

		return $widest;
	}

	/**
	 * Render an array key.
	 *
	 * @param string|int $key Key.
	 */
	private function print_key( $key ): string {
		return is_int( $key ) ? (string) $key : "'" . $this->escape_single_quoted( (string) $key ) . "'";
	}

	/**
	 * Build a dotted path, skipping numeric segments so list indices do not fragment prefixes.
	 */
	private function child_path( string $path, string $key ): string {
		if ( ctype_digit( $key ) ) {
			return $path;
		}

		return $path === '' ? $key : $path . '.' . $key;
	}

	/**
	 * Render a scalar.
	 *
	 * @param mixed  $value Value to render.
	 * @param string $path  Dotted path.
	 */
	private function print_scalar( $value, string $path ): string {
		if ( $value === null ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_int( $value ) ) {
			return (string) $value;
		}

		if ( is_float( $value ) ) {
			/*
			 * NAN and INF have no PHP literal, so they are refused rather than rendered.
			 *
			 * `json_encode()` returns `false` for both, which cast to `''`; the `.0` suffix logic below
			 * then produced the literal `.0`, which parses as **zero**. A config saying "infinity" became
			 * one saying "0.0", silently. Reachable from ordinary JSON input, too:
			 * `json_decode( '{"count": 1e400}' )` yields `INF`, and the REST `model` parameter is decoded
			 * JSON.
			 */
			if ( ! is_finite( $value ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message built from a config path and a numeric value; never rendered to a page.
				throw UnrepresentableValueException::non_finite_float( $path, $value );
			}

			/*
			 * `json_encode()` emits the shortest string that parses back to the same double, and is
			 * locale-independent — unlike string casting, which would render `1.5` as `1,5` under a
			 * comma-decimal locale and produce a syntax error.
			 *
			 * `wp_json_encode()` is deliberately not used: this class also runs from `bin/` tooling
			 * with no WordPress loaded, where that wrapper does not exist. Verified by a fatal.
			 *
			 * The `.0` suffix matters: without it a whole float prints as `5`, which reads back as an
			 * *int* and silently changes the value's type on round-trip.
			 */
			$rendered = (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Must work without WordPress loaded; see above.

			return strpbrk( $rendered, '.eE' ) === false ? $rendered . '.0' : $rendered;
		}

		/*
		 * A closure or object has no source representation at runtime. Refuse rather than degrade:
		 * emitting null, or dropping the key, would produce a file that looks complete and has
		 * silently lost a feature. See UnrepresentableValueException.
		 */
		if ( ! is_string( $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message built from a config path and a type name; never rendered to a page.
			throw new UnrepresentableValueException( $path, get_debug_type( $value ) );
		}

		$string = $value;

		if ( $this->is_translatable( $path ) && $string !== '' ) {
			return sprintf(
				"__( '%s', '%s' )",
				$this->escape_single_quoted( $string ),
				$this->escape_single_quoted( $this->text_domain )
			);
		}

		return "'" . $this->escape_single_quoted( $string ) . "'";
	}

	/**
	 * Build a translators comment when a translatable string carries placeholders.
	 *
	 * WPCS (`WordPress.WP.I18n.MissingTranslatorsComment`) requires one directly above any `__()`
	 * whose text contains a placeholder, because a translator seeing `%s` out of context cannot know
	 * what it stands for. Two placeholder styles appear in this corpus: printf specifiers, and the
	 * framework's own `{permalink}` / `{preview_url}` / `{date}` tokens.
	 *
	 * @param mixed  $value Value being rendered.
	 * @param string $path  Dotted path.
	 */
	private function translators_comment( $value, string $path ): string {
		if ( ! is_string( $value ) || $value === '' || ! $this->is_translatable( $path ) ) {
			return '';
		}

		$notes = array();

		// `>= 1`, not `=== 1`: preg_match_all returns a match *count*, so a string with two tokens
		// returned 2 and the whole comment block was skipped. `post-type-all.php` already ships that
		// shape — 'Book scheduled for {date}. <a href="{preview_url}">Preview</a>' — with a hand-written
		// comment, so regenerating that model silently dropped it.
		if ( preg_match_all( '/\{([a-z_]+)\}/', $value, $tokens ) >= 1 ) {
			foreach ( $tokens[1] as $token ) {
				$notes[] = sprintf( '{%s}: replaced by the %s', $token, str_replace( '_', ' ', $token ) );
			}
		}

		// %s, %d, %1$s and friends — but not an escaped %%.
		if ( preg_match( '/(?<!%)%(\d+\$)?[bcdeEfFgGosuxX]/', $value ) === 1 ) {
			$notes[] = 'placeholder values are substituted at runtime';
		}

		if ( $notes === array() ) {
			return '';
		}

		return '/* translators: ' . implode( ', ', $notes ) . '. */';
	}

	/**
	 * Whether a string at this path is user-facing copy.
	 */
	private function is_translatable( string $path ): bool {
		foreach ( self::TRANSLATABLE_PREFIXES as $prefix ) {
			if ( strpos( $path, $prefix ) === 0 ) {
				return true;
			}
		}

		// `ai_context` is guidance for AI clients, not interface copy.
		if ( strpos( $path, 'ai_context' ) === 0 ) {
			return false;
		}

		$segments = explode( '.', $path );
		$leaf     = end( $segments );

		return in_array( $leaf, self::TRANSLATABLE_KEYS, true );
	}

	/**
	 * Escape a value for a single-quoted PHP string.
	 *
	 * Only backslashes and single quotes are special there, and the order matters: escaping quotes
	 * first would then double the backslashes it just introduced.
	 */
	private function escape_single_quoted( string $value ): string {
		return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $value );
	}
}
