<?php
/**
 * The "include model authoring tools" opt-in on the renamer.
 *
 * Studio's classes ship into every generated plugin — excluding `src/Plugin/Studio/` would leave
 * `Core` referencing missing classes, and a half-removed feature is worse than a disabled one. So the
 * opt-out works by emitting a disable constant into the generated main file, and these tests pin both
 * directions plus the fact that the generated file still parses either way.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Renamer;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginIdentity;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginRenamer;
use ZipArchive;

final class StudioOptInTest extends TestCase {

	/** @var list<string> */
	private array $artifacts = array();

	protected function tearDown(): void {
		foreach ( $this->artifacts as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}

		$this->artifacts = array();
	}

	/**
	 * @param array<string, mixed> $extra Request overrides.
	 */
	private static function identity( array $extra = array() ): PluginIdentity {
		return PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Lib',
					'plugin_slug'       => 'acme-lib',
					'main_file'         => 'acme-lib.php',
					'namespace_segment' => 'AcmeLib',
					'author'            => 'Acme',
					'prefix'            => 'acme_lib',
					'version'           => '1.0.0',
				),
				$extra
			)
		);
	}

	/**
	 * Build a ZIP and return the generated main file's contents.
	 */
	private function generated_main_file( PluginIdentity $identity ): string {
		$zip_path = sys_get_temp_dir() . '/saltus-optin-' . uniqid() . '.zip';
		$this->artifacts[] = $zip_path;

		( new PluginRenamer( dirname( __DIR__, 3 ) ) )->build_zip( $identity, $zip_path );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $zip_path ) );

		$contents = $zip->getFromName( 'acme-lib/acme-lib.php' );
		$zip->close();

		self::assertIsString( $contents );

		return $contents;
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Request parsing
	 * ---------------------------------------------------------------------------
	 */

	public function test_studio_is_excluded_by_default(): void {
		self::assertFalse( self::identity()->include_studio );
	}

	public function test_studio_is_included_when_ticked(): void {
		self::assertTrue( self::identity( array( 'include_studio' => '1' ) )->include_studio );
	}

	/**
	 * Anything other than the literal '1' a checkbox submits must read as off — including an array,
	 * which is how a crafted request would try to smuggle a truthy value past a string check.
	 */
	public function test_non_checkbox_values_read_as_off(): void {
		foreach ( array( '0', '', 'yes', 'true' ) as $value ) {
			self::assertFalse(
				self::identity( array( 'include_studio' => $value ) )->include_studio,
				'Expected off for: ' . var_export( $value, true )
			);
		}

		self::assertFalse( self::identity( array( 'include_studio' => array( 'x' ) ) )->include_studio );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Generated output
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * Opting out emits the disable constant, prefix-renamed to the generated plugin.
	 *
	 * `FRAMEWORK_DEMO_DISABLE_STUDIO` must become `ACME_LIB_DISABLE_STUDIO`, because that is the name
	 * the generated `Core::set_studio()` will look for after the same rewrite.
	 */
	public function test_opting_out_emits_the_prefixed_disable_constant(): void {
		$main = $this->generated_main_file( self::identity() );

		self::assertStringContainsString( "define( 'ACME_LIB_DISABLE_STUDIO', true );", $main );
		self::assertStringNotContainsString( 'FRAMEWORK_DEMO_DISABLE_STUDIO', $main );
	}

	public function test_opting_in_emits_no_disable_constant(): void {
		$main = $this->generated_main_file( self::identity( array( 'include_studio' => '1' ) ) );

		self::assertStringNotContainsString( 'DISABLE_STUDIO', $main );
	}

	/**
	 * The constant is injected by pattern-matching the main file, so a parse check is the guard
	 * against a mangled insertion.
	 */
	public function test_the_generated_main_file_parses_either_way(): void {
		foreach ( array( array(), array( 'include_studio' => '1' ) ) as $extra ) {
			$main = $this->generated_main_file( self::identity( $extra ) );

			token_get_all( $main, TOKEN_PARSE );
			self::assertStringContainsString( 'Plugin Name:       Acme Lib', $main );
		}
	}

	/**
	 * The generated constant must line up with what the generated `Core` checks, or the opt-out is a
	 * comment that does nothing.
	 */
	public function test_the_emitted_constant_matches_what_core_checks(): void {
		$zip_path = sys_get_temp_dir() . '/saltus-optin-core-' . uniqid() . '.zip';
		$this->artifacts[] = $zip_path;

		( new PluginRenamer( dirname( __DIR__, 3 ) ) )->build_zip( self::identity(), $zip_path );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $zip_path ) );

		$main = (string) $zip->getFromName( 'acme-lib/acme-lib.php' );
		$core = (string) $zip->getFromName( 'acme-lib/src/Core.php' );
		$zip->close();

		self::assertSame( 1, preg_match( "/define\(\s*'([A-Z_]+_DISABLE_STUDIO)'/", $main, $emitted ) );
		self::assertSame( 1, preg_match( "/defined\(\s*'([A-Z_]+_DISABLE_STUDIO)'/", $core, $checked ) );
		self::assertSame( $emitted[1], $checked[1] );
	}

	/**
	 * The filter name is rewritten too, so a generated plugin does not listen on the demo's namespace.
	 */
	public function test_the_studio_filter_is_renamed(): void {
		$zip_path = sys_get_temp_dir() . '/saltus-optin-filter-' . uniqid() . '.zip';
		$this->artifacts[] = $zip_path;

		( new PluginRenamer( dirname( __DIR__, 3 ) ) )->build_zip( self::identity(), $zip_path );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $zip_path ) );
		$core = (string) $zip->getFromName( 'acme-lib/src/Core.php' );
		$zip->close();

		self::assertStringContainsString( 'acme_lib/studio/enabled', $core );
		self::assertStringNotContainsString( 'framework_demo/studio/enabled', $core );
	}

	/**
	 * Studio's classes ship regardless: removing them would leave Core referencing missing classes.
	 */
	public function test_studio_classes_ship_even_when_disabled(): void {
		$zip_path = sys_get_temp_dir() . '/saltus-optin-classes-' . uniqid() . '.zip';
		$this->artifacts[] = $zip_path;

		( new PluginRenamer( dirname( __DIR__, 3 ) ) )->build_zip( self::identity(), $zip_path );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $zip_path ) );

		foreach ( array( 'ModelPrinter', 'ModelReader', 'ModelWriter', 'RestController' ) as $class ) {
			self::assertNotFalse(
				$zip->locateName( 'acme-lib/src/Plugin/Studio/' . $class . '.php' ),
				$class . ' should ship even with Studio disabled.'
			);
		}

		$zip->close();
	}
}
