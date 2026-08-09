<?php
/**
 * The round-trip acceptance gate.
 *
 * The epic's primary success criterion: every model in `src/models/` survives
 * config → PHP → config unchanged, and the output is deterministic, parseable and PHPCS-clean.
 *
 * The three files carrying closures are the interesting half. They must be *refused*, loudly, not
 * written minus the callable.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelPrinter;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelReader;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\UnrepresentableValueException;

final class ModelRoundTripTest extends TestCase {

	/**
	 * Scratch directory for this test, created on demand.
	 *
	 * A directory rather than `tempnam()`: these tests need files PHP will `include`, so they need a
	 * `.php` extension, and `tempnam( … ) . '.php'` writes to a *different* path than the one it
	 * created — orphaning the original. That leaked 265 files into /tmp before it was caught.
	 */
	private ?string $temp_dir = null;

	private int $temp_counter = 0;

	public static function setUpBeforeClass(): void {
		// Model files call WordPress at include time; this is the stub layer they need.
		require_once dirname( __DIR__, 3 ) . '/bin/model-loader.php';
	}

	protected function tearDown(): void {
		if ( $this->temp_dir === null ) {
			return;
		}

		foreach ( (array) glob( $this->temp_dir . '/*' ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file );
			}
		}

		rmdir( $this->temp_dir );
		$this->temp_dir     = null;
		$this->temp_counter = 0;
	}

	/**
	 * Path to a uniquely-named scratch file inside this test's own directory.
	 *
	 * @param string $extension File extension, without the dot.
	 */
	private function temp_path( string $extension = 'php' ): string {
		if ( $this->temp_dir === null ) {
			$this->temp_dir = sys_get_temp_dir() . '/saltus-studio-' . uniqid();
			mkdir( $this->temp_dir, 0777, true );
		}

		++$this->temp_counter;

		return sprintf( '%s/model-%d.%s', $this->temp_dir, $this->temp_counter, $extension );
	}

	private static function root(): string {
		return dirname( __DIR__, 3 );
	}

	private function printer(): ModelPrinter {
		return new ModelPrinter( 'framework-demo' );
	}

	/**
	 * Write PHP source to a temp file and include it back.
	 *
	 * @return mixed
	 */
	private function evaluate( string $source ) {
		$file = $this->temp_path();
		file_put_contents( $file, $source );

		return include $file;
	}

	/**
	 * Every model the corpus can represent, keyed for readable test output.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function representable_models(): array {
		require_once self::root() . '/bin/model-loader.php';

		$scan  = \saltus_model_scan( self::root() . '/src/models', self::root() . '/src/models-optional' );
		$cases = array();

		foreach ( $scan as $entry ) {
			if ( $entry['closures'] > 0 ) {
				continue;
			}

			foreach ( $entry['models'] as $index => $model ) {
				$name           = is_string( $model['name'] ?? null ) ? $model['name'] : 'model-' . $index;
				$cases[ $name ] = array( $model );
			}
		}

		return $cases;
	}

	/**
	 * The gate: print each model, read it back, assert nothing changed.
	 *
	 * Top-level key order is compared insensitively because the printer reorders deliberately (a
	 * scalar key must come first, or `is_multiple()` drops the model). Content is compared exactly.
	 *
	 * @param array<string, mixed> $model
	 */
	#[DataProvider( 'representable_models' )]
	public function test_model_survives_a_round_trip( array $model ): void {
		$source   = $this->printer()->print_file( $model );
		$recovered = $this->evaluate( $source );

		self::assertIsArray( $recovered );

		$expected = $model;
		$actual   = $recovered;
		ksort( $expected );
		ksort( $actual );

		self::assertEquals( $expected, $actual );
	}

	/**
	 * @param array<string, mixed> $model
	 */
	#[DataProvider( 'representable_models' )]
	public function test_printed_output_is_syntactically_valid( array $model ): void {
		$file = $this->temp_path();
		file_put_contents( $file, $this->printer()->print_file( $model ) );

		$output = array();
		$status = 0;
		exec( sprintf( 'php -l %s 2>&1', escapeshellarg( $file ) ), $output, $status );

		self::assertSame( 0, $status, implode( "\n", $output ) );
	}

	/**
	 * Generated PHP gets committed, so churn between runs would make the tool unusable.
	 *
	 * @param array<string, mixed> $model
	 */
	#[DataProvider( 'representable_models' )]
	public function test_printing_is_deterministic( array $model ): void {
		$printer = $this->printer();

		self::assertSame( $printer->print_file( $model ), $printer->print_file( $model ) );
	}

	public function test_the_whole_corpus_round_trips(): void {
		$scan     = \saltus_model_scan( self::root() . '/src/models', self::root() . '/src/models-optional' );
		$printer  = $this->printer();
		$printed  = 0;
		$refused  = 0;

		foreach ( $scan as $entry ) {
			foreach ( $entry['models'] as $model ) {
				try {
					$source = $printer->print_file( $model );
				} catch ( UnrepresentableValueException $exception ) {
					++$refused;
					continue;
				}

				$recovered = $this->evaluate( $source );
				self::assertIsArray( $recovered );

				$expected = $model;
				$actual   = $recovered;
				ksort( $expected );
				ksort( $actual );
				self::assertEquals( $expected, $actual );
				++$printed;
			}
		}

		self::assertSame( 12, $printed, 'Expected 12 representable models.' );
		self::assertSame( 3, $refused, 'Expected the three closure-bearing models to be refused.' );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Refusing rather than degrading
	 * ---------------------------------------------------------------------------
	 */

	public function test_a_closure_is_refused_with_its_path(): void {
		$model = array(
			'type'     => 'cpt',
			'name'     => 'x',
			'features' => array(
				'admin_cols' => array(
					'shortcode' => array(
						'function' => static function (): void {},
					),
				),
			),
		);

		try {
			$this->printer()->print_file( $model );
			self::fail( 'Expected the printer to refuse a closure.' );
		} catch ( UnrepresentableValueException $exception ) {
			self::assertSame( 'features.admin_cols.shortcode.function', $exception->path );
			self::assertSame( 'Closure', $exception->value_type );
			self::assertStringContainsString( 'export-only', $exception->getMessage() );
		}
	}

	public function test_the_three_closure_models_are_all_refused(): void {
		$scan    = \saltus_model_scan( self::root() . '/src/models' );
		$printer = $this->printer();
		$refused = array();

		foreach ( $scan as $entry ) {
			if ( $entry['closures'] === 0 ) {
				continue;
			}

			foreach ( $entry['models'] as $model ) {
				try {
					$printer->print_file( $model );
				} catch ( UnrepresentableValueException $exception ) {
					$refused[ (string) $entry['file'] ] = $exception->path;
				}
			}
		}

		self::assertSame(
			array(
				'post-type-all.php'    => 'features.admin_cols.shortcode.function',
				'post-type-event.php'  => 'features.admin_cols.event_computed_col.function',
				'post-type-recipe.php' => 'meta.recipe_fields.sections.other.fields.callback_field.function',
			),
			$refused
		);
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Correctness properties
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * The load-bearing one: a leading array key makes `Modeler::is_multiple()` drop the whole model.
	 *
	 * @param array<string, mixed> $model
	 */
	#[DataProvider( 'representable_models' )]
	public function test_first_emitted_key_is_always_scalar( array $model ): void {
		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		self::assertIsArray( $recovered );
		self::assertFalse(
			is_array( current( $recovered ) ),
			'The first key holds an array, so Modeler::is_multiple() would drop this model.'
		);
	}

	public function test_unknown_keys_are_preserved_after_the_canonical_ones(): void {
		$model = array(
			'custom_extension' => 'kept',
			'name'             => 'x',
			'type'             => 'cpt',
		);

		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		self::assertSame( array( 'type', 'name', 'custom_extension' ), array_keys( (array) $recovered ) );
		self::assertSame( 'kept', $recovered['custom_extension'] );
	}

	public function test_user_facing_strings_are_wrapped_for_translation(): void {
		$source = $this->printer()->print_config(
			array(
				'type'   => 'cpt',
				'name'   => 'x',
				'labels' => array(
					'has_one'     => 'Book',
					'text_domain' => 'framework-demo',
				),
			)
		);

		self::assertStringContainsString( "'has_one'     => __( 'Book', 'framework-demo' )", $source );
		// The text domain is the domain; wrapping it would be nonsense.
		self::assertStringContainsString( "'text_domain' => 'framework-demo'", $source );
	}

	public function test_ai_context_strings_are_not_translated(): void {
		$source = $this->printer()->print_config(
			array(
				'type'       => 'cpt',
				'name'       => 'x',
				'ai_context' => array( 'brand_voice' => 'Clear and practical.' ),
			)
		);

		self::assertStringContainsString( "'brand_voice' => 'Clear and practical.'", $source );
		self::assertStringNotContainsString( '__( \'Clear and practical.\'', $source );
	}

	public function test_placeholders_get_a_translators_comment(): void {
		$source = $this->printer()->print_config(
			array(
				'type'   => 'cpt',
				'name'   => 'x',
				'labels' => array(
					'overrides' => array(
						'messages' => array(
							'post_updated' => 'Updated. <a href="{permalink}">View</a>',
							'plain'        => 'No placeholders here',
						),
					),
				),
			)
		);

		self::assertStringContainsString( '/* translators: {permalink}: replaced by the permalink. */', $source );
		self::assertSame( 1, preg_match_all( '/translators:/', $source ) );
	}

	/**
	 * A string with *two* tokens must still get its comment.
	 *
	 * The check was `preg_match_all( … ) === 1`, comparing against a match *count*, so two tokens
	 * returned 2 and the entire comment block was skipped. The fixture above has a single token, so it
	 * passed throughout. `post-type-all.php:147` already ships this exact shape with a hand-written
	 * translators comment, which regenerating the model silently dropped.
	 */
	public function test_multiple_placeholders_still_get_a_translators_comment(): void {
		$source = $this->printer()->print_config(
			array(
				'type'   => 'cpt',
				'name'   => 'x',
				'labels' => array(
					'overrides' => array(
						'messages' => array(
							'post_scheduled' => 'Book scheduled for {date}. <a href="{preview_url}">Preview</a>',
						),
					),
				),
			)
		);

		self::assertStringContainsString( '{date}: replaced by the date', $source );
		self::assertStringContainsString( '{preview_url}: replaced by the preview url', $source );
		self::assertSame( 1, preg_match_all( '/translators:/', $source ) );
	}

	/**
	 * NAN and INF are refused, not silently turned into zero.
	 *
	 * `json_encode()` returns `false` for both, which cast to `''`; the `.0` suffix logic then emitted the
	 * literal `.0`, which parses as `0.0`. Reachable from ordinary input rather than contrived —
	 * `json_decode( '{"n": 1e400}' )` yields `INF`, and the REST `model` parameter is decoded JSON.
	 */
	public function test_non_finite_floats_are_refused(): void {
		$decoded = json_decode( '{"type":"cpt","name":"x","options":{"count":1e400}}', true );

		self::assertIsArray( $decoded );
		self::assertTrue( is_infinite( $decoded['options']['count'] ), 'Fixture precondition: 1e400 decodes to INF.' );

		foreach ( array( 'INF' => $decoded['options']['count'], 'NAN' => NAN, '-INF' => -INF ) as $label => $value ) {
			try {
				$this->printer()->print_file(
					array(
						'type'    => 'cpt',
						'name'    => 'x',
						'options' => array( 'count' => $value ),
					)
				);
				self::fail( 'Expected a refusal for ' . $label );
			} catch ( UnrepresentableValueException $exception ) {
				self::assertStringContainsString( 'options.count', $exception->getMessage() );
			}
		}

		// A finite float still round-trips, including a whole one.
		$recovered = $this->evaluate(
			$this->printer()->print_file(
				array(
					'type'    => 'cpt',
					'name'    => 'x',
					'options' => array( 'count' => 5.0 ),
				)
			)
		);

		self::assertIsFloat( $recovered['options']['count'] );
		self::assertSame( 5.0, $recovered['options']['count'] );
	}

	/**
	 * A model whose first key holds an array is refused rather than written.
	 *
	 * `sort_keys()` reorders by `KEY_ORDER` only, which does not by itself produce a scalar first key —
	 * the property the printer's own docblock calls "a correctness requirement, not a formatting
	 * preference". A model with no scalar keys at all was written happily and read back as
	 * `multiple=true models=2 errors=0`: two bogus models that are really its own config sections. The
	 * reader cannot catch it either, since its misparse detection needs a scalar `type` to be present.
	 */
	public function test_a_model_whose_first_key_is_an_array_is_refused(): void {
		$model = array(
			'supports' => array( 'title' ),
			'labels'   => array( 'has_one' => 'Thing' ),
		);

		try {
			$this->printer()->print_file( $model );
			self::fail( 'Expected a refusal: this config registers nothing.' );
		} catch ( UnrepresentableValueException $exception ) {
			self::assertStringContainsString( 'first key', $exception->getMessage() );
		}
	}

	/**
	 * `*​/` in a docblock must not escape the comment.
	 *
	 * The printer emitted the description verbatim, so a docblock of `Desc *​/ define("PWNED", 1); /* rest`
	 * wrote a file that defined `PWNED` on include — and `ModelWriter::assert_parses()` accepted it,
	 * because the output genuinely is valid PHP. The framework includes every file in `src/models/` on
	 * every request.
	 */
	public function test_docblock_cannot_escape_its_comment(): void {
		$source = $this->printer()->print_file(
			array(
				'type' => 'cpt',
				'name' => 'x',
			),
			'Desc */ define( "SALTUS_INJECTED", 1 ); /* rest'
		);

		self::assertStringNotContainsString( '*/ define', $source );

		// The only comment terminator is the docblock's own, and the file still parses.
		self::assertSame( 1, preg_match_all( '#\*/#', $source ) );

		$file = $this->temp_path();
		file_put_contents( $file, $source );
		include $file;

		self::assertFalse( defined( 'SALTUS_INJECTED' ), 'Injected code executed on include.' );
	}

	public function test_quotes_and_backslashes_survive_escaping(): void {
		$model = array(
			'type'   => 'cpt',
			'name'   => 'x',
			'labels' => array( 'has_one' => "It's a \\ backslash" ),
		);

		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		self::assertSame( "It's a \\ backslash", $recovered['labels']['has_one'] );
	}

	public function test_scalar_types_are_preserved_exactly(): void {
		$model = array(
			'type'    => 'cpt',
			'name'    => 'x',
			'options' => array(
				'public'        => true,
				'show_in_menu'  => false,
				'menu_position' => null,
				'count'         => 42,
			),
		);

		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		self::assertTrue( $recovered['options']['public'] );
		self::assertFalse( $recovered['options']['show_in_menu'] );
		self::assertNull( $recovered['options']['menu_position'] );
		self::assertSame( 42, $recovered['options']['count'] );
	}

	public function test_the_gate_helper_is_emitted_for_opt_in_models(): void {
		$source = $this->printer()->print_file(
			array(
				'type' => 'cpt',
				'name' => 'recipe',
			),
			'',
			'recipe'
		);

		self::assertStringContainsString( "if ( ! saltus_demo_model_enabled( 'recipe' ) ) {", $source );
		self::assertStringContainsString( 'return array();', $source );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Reader
	 * ---------------------------------------------------------------------------
	 */

	public function test_reader_finds_every_model_in_the_corpus(): void {
		$reader = new ModelReader();
		$total  = 0;

		foreach ( array_merge(
			(array) glob( self::root() . '/src/models/*.php' ),
			(array) glob( self::root() . '/src/models-optional/*.json' )
		) as $file ) {
			$total += count( $reader->read( (string) $file )['models'] );
		}

		self::assertSame( 15, $total );
	}

	public function test_reader_refuses_to_overwrite_closure_files(): void {
		$reader = new ModelReader();

		self::assertFalse( $reader->can_overwrite( self::root() . '/src/models/post-type-all.php' ) );
		self::assertFalse( $reader->can_overwrite( self::root() . '/src/models/post-type-event.php' ) );
		self::assertFalse( $reader->can_overwrite( self::root() . '/src/models/post-type-recipe.php' ) );
		self::assertTrue( $reader->can_overwrite( self::root() . '/src/models/post-type-venue.php' ) );
	}

	/**
	 * `plugin_dir_url()` resolves to an absolute URL for whichever install ran the reader, so writing
	 * it back would bake a machine-specific path into a shared config file.
	 */
	public function test_reader_reports_function_derived_values_as_non_portable(): void {
		$result = ( new ModelReader() )->read( self::root() . '/src/models/post-type-recipe.php' );

		self::assertNotSame( array(), $result['non_portable'] );
	}

	public function test_reader_detects_a_genuine_multi_model_file(): void {
		$result = ( new ModelReader() )->read( self::root() . '/src/models/taxonomy-multiple.php' );

		self::assertTrue( $result['multiple'] );
		self::assertCount( 3, $result['models'] );
	}

	public function test_reader_reports_the_misparse_trap(): void {
		$file = $this->temp_path();
		file_put_contents( $file, '<?php return array( "supports" => array( "title" ), "type" => "cpt", "name" => "gone" );' );

		$result = ( new ModelReader() )->read( $file );

		self::assertSame( array(), $result['models'] );
		self::assertStringContainsString( 'misparsed as a list', implode( "\n", $result['errors'] ) );
	}

	public function test_reader_rejects_invalid_json(): void {
		$file = $this->temp_path( 'json' );
		file_put_contents( $file, '{ "type": "cpt", oops }' );

		$result = ( new ModelReader() )->read( $file );

		self::assertStringContainsString( 'Invalid JSON', implode( "\n", $result['errors'] ) );
	}

	public function test_reader_reports_a_missing_file(): void {
		$result = ( new ModelReader() )->read( '/tmp/definitely-missing-' . uniqid() . '.php' );

		self::assertStringContainsString( 'missing or unreadable', implode( "\n", $result['errors'] ) );
	}
}
