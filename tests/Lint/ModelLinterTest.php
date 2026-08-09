<?php
/**
 * Tests for the model linter.
 *
 * Two halves, and the second matters more:
 *
 * 1. The real corpus lints clean — a regression guard on the models themselves.
 * 2. Every rule fires against a deliberately broken fixture — a guard on the *linter*. A checker
 *    that reports nothing on a clean corpus is indistinguishable from a checker that does nothing.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Lint;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModelLinterTest extends TestCase {

	/** @var list<string> Directories to remove after each test. */
	private array $fixtures = array();

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/bin/lint-models.php';
	}

	protected function tearDown(): void {
		foreach ( $this->fixtures as $dir ) {
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				@unlink( (string) $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$this->fixtures = array();
	}

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function enums(): array {
		return require self::root() . '/schema/model-enums.php';
	}

	/**
	 * Write fixture files into a fresh directory, removed in tearDown().
	 *
	 * @param array<string, string> $files Filename => contents.
	 */
	private function fixture_dir( array $files ): string {
		$dir = sys_get_temp_dir() . '/saltus-lint-' . uniqid();
		mkdir( $dir, 0777, true );
		$this->fixtures[] = $dir;

		foreach ( $files as $name => $contents ) {
			file_put_contents( $dir . '/' . $name, $contents );
		}

		return $dir;
	}

	/**
	 * Write fixture files into a fresh directory and lint them.
	 *
	 * @param array<string, string> $files Filename => contents.
	 * @return list<array<string, string>>
	 */
	private function lint_fixture( array $files ): array {
		$scan     = \saltus_model_scan( $this->fixture_dir( $files ) );
		$findings = array();
		$enums    = self::enums();

		foreach ( $scan as $entry ) {
			$findings = array_merge( $findings, \saltus_lint_file( $entry ) );

			foreach ( $entry['models'] as $model ) {
				$findings = array_merge( $findings, \saltus_lint_model( $model, (string) $entry['file'], $enums ) );
			}
		}

		return array_merge(
			$findings,
			\saltus_lint_duplicates( $scan ),
			\saltus_lint_associations( $scan, $enums )
		);
	}

	/**
	 * @param list<array<string, string>> $findings
	 */
	private static function messages( array $findings ): string {
		return implode(
			"\n",
			array_map(
				// Hints are part of what the linter prints, so assertions may target either half.
				static fn( array $f ): string => $f['severity'] . ' ' . $f['file'] . ' ' . $f['message'] . ' ' . $f['hint'],
				$findings
			)
		);
	}

	private static function has_error( array $findings ): bool {
		foreach ( $findings as $finding ) {
			if ( $finding['severity'] === SALTUS_LINT_ERROR ) {
				return true;
			}
		}

		return false;
	}

	/*
	 * ---------------------------------------------------------------------------
	 * The real corpus
	 * ---------------------------------------------------------------------------
	 */

	public function test_the_shipped_models_lint_without_errors(): void {
		$scan  = \saltus_model_scan( self::root() . '/src/models', self::root() . '/src/models-optional' );
		$enums = self::enums();

		$errors = array();

		foreach ( $scan as $entry ) {
			foreach ( \saltus_lint_file( $entry ) as $finding ) {
				if ( $finding['severity'] === SALTUS_LINT_ERROR ) {
					$errors[] = $finding;
				}
			}

			foreach ( $entry['models'] as $model ) {
				foreach ( \saltus_lint_model( $model, (string) $entry['file'], $enums ) as $finding ) {
					if ( $finding['severity'] === SALTUS_LINT_ERROR ) {
						$errors[] = $finding;
					}
				}
			}
		}

		self::assertSame( array(), $errors, "Shipped models have lint errors:\n" . self::messages( $errors ) );
	}

	/**
	 * The corpus is 15 models across 14 files. Guards against a model silently vanishing.
	 */
	public function test_the_corpus_loads_every_model(): void {
		$scan = \saltus_model_scan( self::root() . '/src/models', self::root() . '/src/models-optional' );

		$models = 0;

		foreach ( $scan as $entry ) {
			$models += count( $entry['models'] );
		}

		self::assertCount( 14, $scan, 'Expected 14 model files (13 PHP + 1 JSON).' );
		self::assertSame( 15, $models, 'Expected 15 registerable models.' );
	}

	/**
	 * The scan recurses, matching `Modeler::load()`'s `RecursiveDirectoryIterator`.
	 *
	 * A non-recursive `glob()` meant a model in `src/models/nested/` was loaded by the framework and
	 * unlinted at the same time — the gate could not see the file it existed to guard. The count
	 * assertions above pass either way, which is why this needs its own fixture.
	 */
	public function test_the_scan_finds_models_in_subdirectories(): void {
		$dir = $this->fixture_dir(
			array(
				'post-type-top.php' => '<?php return array( "type" => "cpt", "name" => "top" );',
			)
		);

		mkdir( $dir . '/nested', 0777, true );
		file_put_contents( $dir . '/nested/post-type-deep.php', '<?php return array( "name" => "deep" );' );

		$scan = \saltus_model_scan( $dir );

		self::assertCount( 2, $scan, 'A nested model must be scanned.' );

		$findings = array();

		foreach ( $scan as $entry ) {
			foreach ( $entry['models'] as $model ) {
				$findings = array_merge( $findings, \saltus_lint_model( $model, (string) $entry['file'], self::enums() ) );
			}
		}

		// And it is genuinely linted, not merely counted: the nested model has no `type`.
		self::assertStringContainsString( 'No "type" key', self::messages( $findings ) );

		unlink( $dir . '/nested/post-type-deep.php' );
		rmdir( $dir . '/nested' );
	}

	/**
	 * Exactly three files carry closures, so exactly three are export-only.
	 */
	public function test_closure_detection_finds_the_three_known_files(): void {
		$scan = \saltus_model_scan( self::root() . '/src/models' );

		$with_closures = array();

		foreach ( $scan as $entry ) {
			if ( $entry['closures'] > 0 ) {
				$with_closures[ (string) $entry['file'] ] = $entry['closures'];
			}
		}

		self::assertSame(
			array(
				'post-type-all.php'    => 1,
				'post-type-event.php'  => 1,
				'post-type-recipe.php' => 1,
			),
			$with_closures
		);
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Each rule fires
	 * ---------------------------------------------------------------------------
	 */

	public function test_missing_type_is_an_error(): void {
		$findings = $this->lint_fixture( array( 'a.php' => '<?php return array( "name" => "x" );' ) );

		self::assertTrue( self::has_error( $findings ) );
		self::assertStringContainsString( 'No "type" key', self::messages( $findings ) );
	}

	public function test_unknown_type_is_an_error(): void {
		$findings = $this->lint_fixture( array( 'a.php' => '<?php return array( "type" => "widget", "name" => "x" );' ) );

		self::assertStringContainsString( 'Unrecognised type "widget"', self::messages( $findings ) );
	}

	public function test_overlong_post_type_name_is_an_error(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "abcdefghijklmnopqrstu" );' )
		);

		$messages = self::messages( $findings );

		self::assertStringContainsString( '21 characters after sanitization; the post ceiling is 20', $messages );

		// The framework throws `InvalidArgumentException` here — it does not truncate, which is what the
		// hint used to claim.
		self::assertStringContainsString( 'InvalidArgumentException', $messages );
		self::assertStringNotContainsString( 'truncates', $messages );
	}

	/**
	 * The ceiling is measured after `sanitize_key()`, as the framework measures it.
	 *
	 * `get_registration_name()` is `strlen( sanitize_key( $this->name ) )`, so a 22-character name with
	 * spaces sanitizes to 20 and registers fine — while the linter, measuring the raw string, reported a
	 * ceiling error that would never occur. The slug-safety rule still reports the sanitization itself, so
	 * the real problem is not hidden.
	 */
	public function test_the_name_ceiling_is_measured_after_sanitization(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "ab cd ef gh ij kl mnop" );' )
		);

		$messages = self::messages( $findings );

		self::assertStringNotContainsString( 'ceiling', $messages, 'It sanitizes to 18 characters, well inside the ceiling.' );
		self::assertStringContainsString( 'is not slug-safe', $messages );
	}

	/**
	 * A missing name is a fatal TypeError, not an empty registration name.
	 */
	public function test_the_missing_name_hint_describes_the_real_failure(): void {
		$findings = $this->lint_fixture( array( 'a.php' => '<?php return array( "type" => "cpt" );' ) );

		$messages = self::messages( $findings );

		self::assertStringContainsString( 'TypeError', $messages );
		self::assertStringNotContainsString( 'falls back to an empty registration name', $messages );
	}

	/**
	 * A taxonomy gets 32 characters, so a name that is too long for a CPT is fine here.
	 */
	public function test_taxonomy_name_ceiling_is_higher_than_a_post_types(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "taxonomy", "name" => "abcdefghijklmnopqrstu" );' )
		);

		self::assertStringNotContainsString( 'ceiling', self::messages( $findings ) );
	}

	public function test_reserved_names_are_errors(): void {
		$post = $this->lint_fixture( array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "page" );' ) );
		$tax  = $this->lint_fixture( array( 'b.php' => '<?php return array( "type" => "taxonomy", "name" => "category" );' ) );

		self::assertStringContainsString( '"page" is reserved', self::messages( $post ) );
		self::assertStringContainsString( '"category" is reserved', self::messages( $tax ) );
	}

	/**
	 * A query-var clash is a warning; four such names were wrongly listed as reserved post types.
	 *
	 * `create_initial_post_types()` registers none of `action`, `author`, `order` or `theme` — they are
	 * entries in `WP::$public_query_vars` (`wp-includes/class-wp.php:18`). So the clash needs the model to
	 * be publicly queryable with `query_var` enabled, and even then it degrades URL parsing rather than
	 * failing registration. A CPT named `order`, as WooCommerce ships, drew a hard error claiming a core
	 * collision that does not occur.
	 */
	public function test_query_var_names_warn_rather_than_error(): void {
		foreach ( array( 'action', 'author', 'order', 'theme' ) as $name ) {
			$findings = $this->lint_fixture(
				array( 'a.php' => sprintf( '<?php return array( "type" => "cpt", "name" => "%s" );', $name ) )
			);

			self::assertFalse(
				self::has_error( $findings ),
				sprintf( '"%s" is a query var, not a registered post type, so it must not be an error.', $name )
			);
			self::assertStringContainsString( 'public query vars', self::messages( $findings ) );
		}
	}

	/**
	 * The names WordPress genuinely registers stay errors.
	 */
	public function test_genuinely_reserved_names_are_still_errors(): void {
		foreach ( array( 'post', 'page', 'attachment', 'revision', 'wp_template' ) as $name ) {
			$findings = $this->lint_fixture(
				array( 'a.php' => sprintf( '<?php return array( "type" => "cpt", "name" => "%s" );', $name ) )
			);

			self::assertTrue( self::has_error( $findings ), sprintf( '"%s" is registered by core.', $name ) );
		}
	}

	/**
	 * The filename convention rule is gone, and must stay gone.
	 *
	 * It fired on two deliberately-named shipped files (`post-type-all.php` → `book`,
	 * `post-type-basic.php` → `movie`) while its own hint said renaming them is a behaviour change,
	 * because filenames control `Modeler` load order. Two of the three warnings on a clean corpus came
	 * from a rule nobody could satisfy, and `--strict` failed on exactly those.
	 */
	public function test_a_filename_that_does_not_name_its_model_is_not_reported(): void {
		$findings = $this->lint_fixture(
			array( 'post-type-all.php' => '<?php return array( "type" => "cpt", "name" => "book" );' )
		);

		self::assertSame( array(), $findings, 'A divergent filename is a review matter, not a gate finding.' );
	}

	public function test_non_slug_safe_name_is_an_error(): void {
		$findings = $this->lint_fixture( array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "My Type!" );' ) );

		self::assertStringContainsString( 'is not slug-safe', self::messages( $findings ) );
	}

	public function test_unknown_field_type_is_an_error(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "meta" => array( "box" => array( "fields" => array( "f" => array( "type" => "supertext" ) ) ) ) );',
			)
		);

		self::assertStringContainsString( 'Unknown field type "supertext"', self::messages( $findings ) );
	}

	/**
	 * A `type` inside field *data* is not a field type.
	 *
	 * The collector recursed into every array and called anything with a scalar `type` a field, so valid
	 * Codestar config was rejected: `attributes` is how Codestar sets the HTML input type
	 * (`fields/text/text.php:19` reads exactly that key), an `options` map may legitimately have a key
	 * named `type`, and `code_editor` takes a `settings` block — a shape `snippet.json` already uses. The
	 * corpus escaped only because no model currently nests `attributes` inside a field.
	 *
	 * @param string $fixture A model whose field carries a nested `type` in data.
	 */
	#[DataProvider( 'field_data_provider' )]
	public function test_a_type_key_in_field_data_is_not_a_field_type( string $fixture ): void {
		$findings = $this->lint_fixture( array( 'a.php' => $fixture ) );

		self::assertFalse( self::has_error( $findings ), self::messages( $findings ) );
		self::assertStringNotContainsString( 'Unknown field type', self::messages( $findings ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function field_data_provider(): array {
		$model = static fn( string $field ): string => sprintf(
			'<?php return array( "type" => "cpt", "name" => "x", "meta" => array( "box" => array( "fields" => array( %s ) ) ) );',
			$field
		);

		return array
		(
			'attributes'      => array( $model( 'array( "id" => "a", "type" => "text", "attributes" => array( "type" => "email" ) )' ) ),
			'options map'     => array( $model( 'array( "id" => "b", "type" => "select", "options" => array( "type" => "By type", "date" => "By date" ) )' ) ),
			'editor settings' => array( $model( 'array( "id" => "c", "type" => "code_editor", "settings" => array( "type" => "text/html" ) )' ) ),
			'dependency'      => array( $model( 'array( "id" => "d", "type" => "text", "dependency" => array( "type", "==", "email" ) )' ) ),
		);
	}

	/**
	 * Unknown types are still caught inside composite fields, at any depth.
	 *
	 * The previous version of this check exercised only `fields`-nested containers, so it passed while
	 * the over-eager recursion above stood.
	 */
	public function test_unknown_field_types_are_caught_at_every_nesting_depth(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "meta" => array( "box" => array( "sections" => array( "s" => array( "fields" => array( array( "id" => "r", "type" => "repeater", "fields" => array( array( "id" => "n", "type" => "bogus_nested" ) ) ), array( "id" => "t", "type" => "tabbed", "tabs" => array( array( "title" => "T", "fields" => array( array( "id" => "d", "type" => "bogus_tabbed" ) ) ) ) ) ) ) ) ) ) );',
			)
		);

		$messages = self::messages( $findings );

		self::assertStringContainsString( 'bogus_nested', $messages );
		self::assertStringContainsString( 'bogus_tabbed', $messages );
	}

	/**
	 * A model calling an unstubbed WordPress function is reported as a stub gap, not a broken model.
	 *
	 * Every missing stub used to be a hard error that failed the build with `Call to undefined
	 * function …`, which reads as the model's fault. The asymmetry gave it away: `add_filter` was
	 * stubbed while `apply_filters` was not, so registering a hook loaded and applying one failed.
	 */
	public function test_common_wordpress_functions_are_stubbed(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return apply_filters( "hook", array( "type" => "cpt", "name" => "hooked", "labels" => array( "has_one" => esc_attr__( "Hooked", "framework-demo" ) ), "options" => array( "rewrite" => array( "slug" => sanitize_title( "My Slug" ) ) ) ) );',
			)
		);

		self::assertFalse( self::has_error( $findings ), self::messages( $findings ) );
	}

	/**
	 * A model that terminates the process must fail the gate loudly.
	 *
	 * `bin/model-loader.php` uses `include`, and `exit` is not a `Throwable` — so a model opening with
	 * WordPress's near-universal `if ( ! defined( 'ABSPATH' ) ) exit;` killed the linter mid-run. This is
	 * the worst failure mode a gate can have: reproduced with one guarded file plus one genuinely broken
	 * model, the run produced **zero bytes of output and exit 0**, indistinguishable from a clean corpus.
	 *
	 * Run as a subprocess because the fix necessarily ends the process it detects.
	 */
	public function test_a_model_that_exits_fails_the_run(): void {
		$dir = $this->fixture_dir(
			array
			(
				'post-type-guarded.php' => "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\nreturn array( 'type' => 'cpt', 'name' => 'guarded' );",
				'post-type-broken.php'  => '<?php return array( "name" => "no-type" );',
			)
		);

		$command = sprintf(
			'php %s --path=%s 2>&1',
			escapeshellarg( self::root() . '/bin/lint-models.php' ),
			escapeshellarg( $dir )
		);

		$output = array();
		$status = 0;
		exec( $command, $output, $status );

		$text = implode( "\n", $output );

		self::assertNotSame( 0, $status, "A terminating model must not pass the gate:\n" . $text );
		self::assertNotSame( '', trim( $text ), 'A silent pass is indistinguishable from a clean corpus.' );
		self::assertStringContainsString( 'post-type-guarded.php', $text, 'The report must name the file that terminated.' );
	}

	public function test_an_unstubbed_function_names_the_stub_layer(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "y" => wp_definitely_not_stubbed() );' )
		);

		self::assertTrue( self::has_error( $findings ) );
		self::assertStringContainsString( 'bin/model-loader-stubs.php', self::messages( $findings ) );
	}

	public function test_known_field_types_are_accepted_at_any_depth(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "meta" => array( "box" => array( "sections" => array( "s" => array( "fields" => array( "rep" => array( "type" => "repeater", "fields" => array( "inner" => array( "type" => "typography" ) ) ) ) ) ) ) ) );',
			)
		);

		self::assertStringNotContainsString( 'Unknown field type', self::messages( $findings ) );
	}

	/**
	 * The latent misparse trap: a single model whose first key holds an array is dropped entirely.
	 */
	public function test_array_valued_first_key_is_reported_as_a_misparse(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "supports" => array( "title" ), "type" => "cpt", "name" => "gone" );' )
		);

		$messages = self::messages( $findings );

		self::assertTrue( self::has_error( $findings ) );
		self::assertStringContainsString( 'misparsed as a list', $messages );
		self::assertStringContainsString( '"supports"', $messages );
	}

	/**
	 * A genuine multi-model file must still be accepted — that is how the taxonomy trio ships.
	 */
	public function test_genuine_multi_model_files_are_accepted(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( array( "type" => "taxonomy", "name" => "one" ), array( "type" => "taxonomy", "name" => "two" ) );',
			)
		);

		self::assertFalse( self::has_error( $findings ), self::messages( $findings ) );
	}

	public function test_structured_field_rules_are_an_error(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "ai_context" => array( "field_rules" => array( "f" => array( "required" => true, "max_length" => 5 ) ) ) );',
			)
		);

		self::assertStringContainsString( 'non-string value', self::messages( $findings ) );
	}

	public function test_string_list_field_rules_are_accepted(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "ai_context" => array( "field_rules" => array( "f" => array( "Keep it short." ) ) ) );',
			)
		);

		self::assertFalse( self::has_error( $findings ), self::messages( $findings ) );
	}

	public function test_duplicate_model_names_are_an_error(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "dupe" );',
				'b.php' => '<?php return array( "type" => "cpt", "name" => "dupe" );',
			)
		);

		self::assertStringContainsString( 'Duplicate model name', self::messages( $findings ) );
	}

	public function test_active_key_is_flagged_as_non_functional(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "cpt", "name" => "x", "active" => false );' )
		);

		self::assertStringContainsString( 'does not work', self::messages( $findings ) );
	}

	public function test_orphan_taxonomy_association_is_flagged(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "taxonomy", "name" => "t", "associations" => array( "ghost" ) );' )
		);

		self::assertStringContainsString( 'which no model registers', self::messages( $findings ) );
	}

	/**
	 * Core post types are legitimate targets: `country` attaches to the built-in `post`.
	 */
	public function test_association_with_a_core_post_type_is_accepted(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "taxonomy", "name" => "t", "associations" => array( "post" ) );' )
		);

		self::assertStringNotContainsString( 'no model registers', self::messages( $findings ) );
	}

	/**
	 * A bare string is valid — the framework assigns it straight through.
	 */
	public function test_string_association_is_accepted(): void {
		$findings = $this->lint_fixture(
			array(
				'a.php' => '<?php return array( "type" => "cpt", "name" => "book" );',
				'b.php' => '<?php return array( "type" => "taxonomy", "name" => "t", "associations" => "book" );',
			)
		);

		self::assertFalse( self::has_error( $findings ), self::messages( $findings ) );
		self::assertStringNotContainsString( 'no model registers', self::messages( $findings ) );
	}

	public function test_yaml_models_are_reported_as_unparseable(): void {
		$findings = $this->lint_fixture( array( 'a.yml' => "type: cpt\nname: x\n" ) );

		self::assertStringContainsString( 'symfony/yaml is not installed', self::messages( $findings ) );
	}

	public function test_invalid_json_is_an_error(): void {
		$findings = $this->lint_fixture( array( 'a.json' => '{ "type": "cpt", oops }' ) );

		self::assertStringContainsString( 'Invalid JSON', self::messages( $findings ) );
	}

	public function test_empty_return_is_flagged(): void {
		$findings = $this->lint_fixture( array( 'a.php' => '<?php return array();' ) );

		self::assertStringContainsString( 'Returned an empty array', self::messages( $findings ) );
	}

	public function test_taxonomy_meta_is_flagged_as_ignored(): void {
		$findings = $this->lint_fixture(
			array( 'a.php' => '<?php return array( "type" => "taxonomy", "name" => "t", "meta" => array( "b" => array() ) );' )
		);

		self::assertStringContainsString( 'never rendered', self::messages( $findings ) );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Optional JSON Schema validation
	 * ---------------------------------------------------------------------------
	 *
	 * `justinrainbow/json-schema` is a transitive dev dependency, so these skip under --no-dev.
	 */

	private static function schema_file(): string {
		return self::root() . '/schema/model.schema.json';
	}

	private static function require_validator(): void {
		if ( ! class_exists( \JsonSchema\Validator::class ) ) {
			self::markTestSkipped( 'justinrainbow/json-schema is not installed.' );
		}
	}

	/**
	 * A valid model must produce no schema findings — the check has to be quiet on good input or its
	 * warnings are worthless.
	 */
	public function test_schema_validation_is_silent_on_the_real_corpus(): void {
		self::require_validator();

		$scan = \saltus_model_scan( self::root() . '/src/models', self::root() . '/src/models-optional' );

		$findings = array();

		foreach ( $scan as $entry ) {
			foreach ( $entry['models'] as $model ) {
				$findings = array_merge(
					$findings,
					\saltus_lint_schema( $model, (string) $entry['file'], self::schema_file() )
				);
			}
		}

		self::assertSame( array(), $findings, "Schema flagged shipped models:\n" . self::messages( $findings ) );
	}

	/**
	 * An unknown top-level key reports an *empty* property, so an early version of the reporting
	 * filter discarded exactly this finding. Pinned so that cannot regress.
	 */
	public function test_schema_validation_catches_an_unknown_top_level_key(): void {
		self::require_validator();

		$findings = \saltus_lint_schema(
			array(
				'type'         => 'cpt',
				'name'         => 'x',
				'nonsense_key' => 1,
			),
			'a.php',
			self::schema_file()
		);

		self::assertNotSame( array(), $findings );
		self::assertStringContainsString( 'nonsense_key', self::messages( $findings ) );
		self::assertStringContainsString( '(root)', self::messages( $findings ) );
	}

	public function test_schema_validation_catches_a_nested_field_type(): void {
		self::require_validator();

		$findings = \saltus_lint_schema(
			array(
				'type' => 'cpt',
				'name' => 'x',
				'meta' => array( 'b' => array( 'fields' => array( 'f' => array( 'type' => 'supertext' ) ) ) ),
			),
			'a.php',
			self::schema_file()
		);

		self::assertStringContainsString( 'meta.b.fields.f.type', self::messages( $findings ) );
	}

	/**
	 * Long enum messages are truncated: a field-type failure would otherwise quote all 44 types.
	 */
	public function test_schema_messages_are_truncated(): void {
		self::require_validator();

		$findings = \saltus_lint_schema(
			array(
				'type' => 'cpt',
				'name' => 'x',
				'meta' => array( 'b' => array( 'fields' => array( 'f' => array( 'type' => 'supertext' ) ) ) ),
			),
			'a.php',
			self::schema_file()
		);

		foreach ( $findings as $finding ) {
			self::assertLessThanOrEqual( 200, strlen( $finding['message'] ) );
		}
	}

	/**
	 * Validating a single model against the whole `oneOf` union produced actively wrong output
	 * ("String value found, but an object is required (type)" for a perfectly valid `type: cpt`),
	 * because the keyed-object branch expects every top-level value to be a model. The check must
	 * use the single-model branch only.
	 */
	public function test_schema_validation_does_not_report_union_branch_noise(): void {
		self::require_validator();

		$findings = \saltus_lint_schema(
			array(
				'type'     => 'cpt',
				'name'     => 'x',
				'supports' => array( 'title' ),
			),
			'a.php',
			self::schema_file()
		);

		self::assertSame( array(), $findings, self::messages( $findings ) );
	}

	/**
	 * A missing schema file must degrade to no findings rather than throwing.
	 */
	public function test_schema_validation_is_a_noop_without_a_schema_file(): void {
		$findings = \saltus_lint_schema(
			array(
				'type' => 'cpt',
				'name' => 'x',
			),
			'a.php',
			'/tmp/definitely-not-a-schema-' . uniqid() . '.json'
		);

		self::assertSame( array(), $findings );
	}
}
