<?php
/**
 * Tests for the model file writer.
 *
 * Almost every test here asserts a *refusal*. The writer's job is to put executable PHP into a
 * directory the framework `include`s on every request, so the guards are the feature and the write
 * itself is four lines.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelPrinter;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelReader;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelWriter;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\UnrepresentableValueException;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\WriteRefusedException;

final class ModelWriterTest extends TestCase {

	private string $dir = '';

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 3 ) . '/bin/model-loader.php';
	}

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/saltus-writer-' . uniqid();
		mkdir( $this->dir, 0777, true );
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->dir . '/{,.}*', GLOB_BRACE ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file );
			}
		}

		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir );
		}
	}

	private function writer(): ModelWriter {
		return new ModelWriter( $this->dir, new ModelPrinter( 'framework-demo' ), new ModelReader() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function model(): array {
		return array(
			'type'   => 'cpt',
			'name'   => 'widget',
			'labels' => array(
				'has_one'  => 'Widget',
				'has_many' => 'Widgets',
			),
		);
	}

	public function test_writes_a_parseable_file(): void {
		$path = $this->writer()->write( self::model(), 'post-type-widget.php' );

		self::assertFileExists( $path );
		self::assertSame( $this->dir . '/post-type-widget.php', $path );

		$source = (string) file_get_contents( $path );
		token_get_all( $source, TOKEN_PARSE );

		$recovered = include $path;
		self::assertSame( 'widget', $recovered['name'] );
	}

	public function test_written_file_is_readable_by_the_web_server(): void {
		$path = $this->writer()->write( self::model(), 'a.php' );

		// tempnam() creates 0600 files; left uncorrected the server could not read what it wrote.
		self::assertSame( 0644, fileperms( $path ) & 0644 );
	}

	public function test_refuses_to_replace_an_existing_file_by_default(): void {
		$writer = $this->writer();
		$writer->write( self::model(), 'a.php' );

		$this->expectException( WriteRefusedException::class );

		try {
			$writer->write( self::model(), 'a.php' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_EXISTS, $exception->reason );
			throw $exception;
		}
	}

	public function test_replaces_an_existing_file_when_asked(): void {
		$writer = $this->writer();
		$writer->write( self::model(), 'a.php' );

		$updated         = self::model();
		$updated['name'] = 'gadget';
		$writer->write( $updated, 'a.php', true );

		$recovered = include $this->dir . '/a.php';
		self::assertSame( 'gadget', $recovered['name'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function unsafe_filenames(): array {
		return array(
			'parent traversal'    => array( '../evil.php' ),
			'nested traversal'    => array( 'a/../../evil.php' ),
			'absolute path'       => array( '/tmp/evil.php' ),
			'subdirectory'        => array( 'sub/a.php' ),
			'double dot in name'  => array( 'a..b.php' ),
			'wrong extension'     => array( 'shell.phtml' ),
			'no extension'        => array( 'model' ),
			'uppercase'           => array( 'Model.php' ),
			'leading dot'         => array( '.hidden.php' ),
			'empty'               => array( '' ),

			/*
			 * `$` in PCRE matches before a final newline and `basename()` keeps one, so `a.php\n` was
			 * accepted and created — a file invisible to every `*.php` glob (so unseen by the scanner, the
			 * linter and any cleanup) while an existence check for a legitimate `a.php` looked elsewhere.
			 */
			'trailing newline'    => array( "a.php\n" ),
			'newline mid-name'    => array( "a\n.php" ),

			/*
			 * `.json` used to be allowed while nothing branched on the extension: `write()` calls
			 * `print_file()` unconditionally, so the response was 201 with `written: true` and `models: 0`
			 * at once, having written PHP into a file the framework collects and cannot parse.
			 */
			'json target'         => array( 'model.json' ),
			'json with newline'   => array( "model.json\n" ),
		);
	}

	#[DataProvider( 'unsafe_filenames' )]
	public function test_refuses_unsafe_filenames( string $filename ): void {
		try {
			$this->writer()->write( self::model(), $filename );
			self::fail( 'Expected a refusal for: ' . $filename );
		} catch ( WriteRefusedException $exception ) {
			self::assertContains(
				$exception->reason,
				array( WriteRefusedException::REASON_BAD_NAME, WriteRefusedException::REASON_OUTSIDE_ROOT )
			);
		}

		self::assertSame( array(), glob( $this->dir . '/*' ) ?: array(), 'Nothing should have been written.' );
	}

	/**
	 * The refusal that protects against silent data loss: three shipped models hold callables.
	 */
	public function test_refuses_to_overwrite_a_file_containing_a_closure(): void {
		$original = '<?php return array( "type" => "cpt", "name" => "c", "features" => array( "admin_cols" => array( "x" => array( "function" => function () {} ) ) ) );';
		file_put_contents( $this->dir . '/has-closure.php', $original );

		try {
			$this->writer()->write( self::model(), 'has-closure.php', true );
			self::fail( 'Expected a refusal.' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_HAS_CLOSURE, $exception->reason );
		}

		self::assertSame(
			$original,
			file_get_contents( $this->dir . '/has-closure.php' ),
			'The existing file must be left byte-identical.'
		);
	}

	/**
	 * A file that yields no models must be refused, not treated as safe.
	 *
	 * This is the configuration users actually run. The nine opt-in models are **off** by default, so a
	 * gated file returns `array()` at include time — which left `closures` and `errors` both empty and
	 * `can_overwrite()` returning true. Verified at shipped defaults before the fix:
	 * `post-type-recipe.php` (12,496 bytes, closure included) reported `can_overwrite=TRUE`, and a write
	 * replaced it with a 62-byte stub.
	 *
	 * The fixture gates on an option the stub layer does not enable, because `bin/model-loader.php`
	 * forces every *demo* toggle on for the whole test process — which is exactly why the existing
	 * round-trip test only ever exercised the one configuration where the guard worked.
	 */
	public function test_refuses_to_overwrite_a_file_that_yields_no_models(): void {
		$original = '<?php if ( ! get_option( "some-toggle-that-is-off" ) ) { return array(); } return array( "type" => "cpt", "name" => "gated", "features" => array( "admin_cols" => array( "x" => array( "function" => function () {} ) ) ) );';
		file_put_contents( $this->dir . '/gated.php', $original );

		// The premise: at these defaults the file really does read back as empty.
		$read = ( new ModelReader() )->read( $this->dir . '/gated.php' );
		self::assertSame( array(), $read['models'], 'Fixture precondition: the gate must be closed.' );
		self::assertSame( array(), $read['closures'], 'Fixture precondition: the closure must be invisible behind the gate.' );

		try {
			$this->writer()->write( self::model(), 'gated.php', true );
			self::fail( 'Expected a refusal.' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_HAS_CLOSURE, $exception->reason );
		}

		self::assertSame(
			$original,
			file_get_contents( $this->dir . '/gated.php' ),
			'The existing file must be left byte-identical.'
		);
	}

	/**
	 * A file declaring several models cannot be regenerated: the writer prints exactly one.
	 *
	 * Found by running the corpus round-trip at shipped defaults rather than by reading the code.
	 * `taxonomy-multiple.php` declares `genre`, `writer` and `country`, holds no closure and produces no
	 * read errors — so every other guard passed it, and a single write would have kept one model and
	 * silently deleted two.
	 */
	public function test_refuses_to_overwrite_a_file_declaring_several_models(): void {
		$original = '<?php return array( "genre" => array( "type" => "taxonomy", "name" => "genre" ), "writer" => array( "type" => "taxonomy", "name" => "writer" ) );';
		file_put_contents( $this->dir . '/multi.php', $original );

		// The premise: readable, no closures — the conditions under which the old guards said yes.
		$read = ( new ModelReader() )->read( $this->dir . '/multi.php' );
		self::assertCount( 2, $read['models'] );
		self::assertSame( array(), $read['errors'] );
		self::assertSame( array(), $read['closures'] );

		try {
			$this->writer()->write( self::model(), 'multi.php', true );
			self::fail( 'Expected a refusal: two of the three models would vanish.' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_HAS_CLOSURE, $exception->reason );
		}

		self::assertSame( $original, file_get_contents( $this->dir . '/multi.php' ) );
	}

	/**
	 * A single readable model still overwrites, so the guards above are not simply refusing everything.
	 */
	public function test_a_single_model_file_can_still_be_overwritten(): void {
		file_put_contents( $this->dir . '/single.php', '<?php return array( "type" => "cpt", "name" => "before" );' );

		$this->writer()->write( self::model(), 'single.php', true );

		$contents = (string) file_get_contents( $this->dir . '/single.php' );

		self::assertStringContainsString( "'widget'", $contents );
		self::assertStringNotContainsString( 'before', $contents );
	}

	public function test_refuses_a_config_holding_a_closure(): void {
		$model = array(
			'type'     => 'cpt',
			'name'     => 'z',
			'features' => array( 'admin_cols' => array( 'c' => array( 'function' => static fn() => 1 ) ) ),
		);

		$this->expectException( UnrepresentableValueException::class );
		$this->writer()->write( $model, 'z.php' );
	}

	public function test_render_does_not_touch_the_filesystem(): void {
		$source = $this->writer()->render( self::model() );

		self::assertStringContainsString( "'name'", $source );
		self::assertSame( array(), glob( $this->dir . '/*' ) ?: array() );
	}

	public function test_leaves_no_temp_files_behind(): void {
		$this->writer()->write( self::model(), 'a.php' );

		self::assertSame( array(), glob( $this->dir . '/.saltus-studio-*' ) ?: array() );
	}

	public function test_refuses_when_the_models_directory_is_missing(): void {
		$writer = new ModelWriter(
			$this->dir . '/nope',
			new ModelPrinter( 'framework-demo' ),
			new ModelReader()
		);

		try {
			$writer->write( self::model(), 'a.php' );
			self::fail( 'Expected a refusal.' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_OUTSIDE_ROOT, $exception->reason );
		}
	}

	/**
	 * Hosts set `DISALLOW_FILE_MODS` to mean "no code changes from the dashboard", which is exactly
	 * what this is. Runs in a subprocess because the constant cannot be undefined once set.
	 */
	public function test_honours_disallow_file_mods(): void {
		$root   = dirname( __DIR__, 3 );
		$script = sprintf(
			'define("DISALLOW_FILE_MODS", true);'
			. 'require %s; require %s;'
			. '$w = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelWriter(%s,'
			. ' new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelPrinter("framework-demo"),'
			. ' new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelReader());'
			. 'try { $w->write(array("type"=>"cpt","name"=>"x"), "blocked.php"); echo "WROTE"; }'
			. ' catch (\Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\WriteRefusedException $e) { echo $e->reason; }',
			var_export( $root . '/vendor/autoload.php', true ),
			var_export( $root . '/bin/model-loader.php', true ),
			var_export( $this->dir, true )
		);

		$output = array();
		exec( sprintf( 'php -r %s 2>&1', escapeshellarg( $script ) ), $output );

		self::assertSame( WriteRefusedException::REASON_DISALLOWED, trim( implode( '', $output ) ) );
		self::assertFileDoesNotExist( $this->dir . '/blocked.php' );
	}

	/**
	 * A symlink target outside the models directory passes every string check, so the writer compares
	 * resolved paths.
	 */
	public function test_refuses_a_symlink_pointing_outside_the_models_directory(): void {
		$outside = sys_get_temp_dir() . '/saltus-outside-' . uniqid() . '.php';
		file_put_contents( $outside, '<?php return array( "type" => "cpt", "name" => "o" );' );
		symlink( $outside, $this->dir . '/link.php' );

		try {
			$this->writer()->write( self::model(), 'link.php', true );
			self::fail( 'Expected a refusal.' );
		} catch ( WriteRefusedException $exception ) {
			self::assertSame( WriteRefusedException::REASON_OUTSIDE_ROOT, $exception->reason );
		} finally {
			unlink( $this->dir . '/link.php' );
			unlink( $outside );
		}
	}

	public function test_writes_the_gate_helper_for_opt_in_models(): void {
		$path = $this->writer()->write( self::model(), 'a.php', false, 'A demo model.', 'widget' );

		$source = (string) file_get_contents( $path );

		self::assertStringContainsString( "saltus_demo_model_enabled( 'widget' )", $source );
		self::assertStringContainsString( 'A demo model.', $source );
	}
}
