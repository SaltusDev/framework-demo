<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Renamer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginIdentity;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginRenamer;
use ZipArchive;

class PluginRenamerTest extends TestCase {

	public function test_build_zip_creates_renamed_plugin_package(): void {
		$source = $this->make_source_fixture();
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		self::assertNotFalse( $zip->locateName( 'acme-library/acme-library.php' ) );
		self::assertFalse( $zip->locateName( 'acme-library/vendor/autoload.php' ) );
		self::assertNotFalse( $zip->locateName( 'acme-library/vendor-prefixed/autoload.php' ) );
		self::assertFalse( $zip->locateName( 'acme-library/build/Gruntfile.js' ) );

		$contents = $zip->getFromName( 'acme-library/acme-library.php' );
		self::assertIsString( $contents );
		self::assertStringContainsString( 'Plugin Name:       Acme Library', $contents );
		self::assertStringContainsString( 'Plugin URI:        https://saltus.dev/my-saltus-plugin/', $contents );
		self::assertStringContainsString( 'Author URI:        https://saltus.dev/', $contents );
		self::assertStringContainsString( 'Text Domain:       acme-library', $contents );
		self::assertStringContainsString( 'namespace Saltus\\WP\\Plugin\\Saltus\\AcmeLibrary;', $contents );
		self::assertStringNotContainsString( 'Source Header That Should Not Matter', $contents );
		self::assertStringNotContainsString( 'framework-demo', $contents );

		$prefixed_autoload = $zip->getFromName( 'acme-library/vendor-prefixed/autoload.php' );
		self::assertIsString( $prefixed_autoload );
		self::assertStringContainsString( 'Saltus\\\\WP\\\\Plugin\\\\Saltus\\\\AcmeLibrary', $prefixed_autoload );
		self::assertMatchesRegularExpression( '/ComposerAutoloaderInit[a-f0-9]{32}/', $prefixed_autoload );
		self::assertStringNotContainsString( 'ComposerAutoloaderInit0515b56810b0a42fc7dbe10cb02ee1c8', $prefixed_autoload );
		self::assertStringNotContainsString( 'PluginFrameworkDemo', $prefixed_autoload );

		$autoload_real = $zip->getFromName( 'acme-library/vendor-prefixed/composer/autoload_real.php' );
		self::assertIsString( $autoload_real );
		self::assertMatchesRegularExpression( '/ComposerAutoloaderInit[a-f0-9]{32}/', $autoload_real );
		self::assertMatchesRegularExpression( '/ComposerStaticInit[a-f0-9]{32}/', $autoload_real );
		self::assertMatchesRegularExpression( '/composerRequire[a-f0-9]{32}/', $autoload_real );
		self::assertStringNotContainsString( 'ComposerAutoloaderInit0515b56810b0a42fc7dbe10cb02ee1c8', $autoload_real );
		self::assertStringNotContainsString( 'ComposerStaticInit0515b56810b0a42fc7dbe10cb02ee1c8', $autoload_real );
		self::assertStringNotContainsString( 'composerRequire0515b56810b0a42fc7dbe10cb02ee1c8', $autoload_real );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_build_zip_replaces_plugin_version_constant(): void {
		$source = $this->make_source_fixture();
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
					'version'           => '1.5.0',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/acme-library.php' );
		self::assertIsString( $contents );
		self::assertStringContainsString( "PLUGIN_VERSION', '1.5.0'", $contents );
		self::assertStringNotContainsString( "PLUGIN_VERSION', '7.7.7'", $contents );
		// The header and the constant must agree; they drifted apart before.
		self::assertStringContainsString( 'Version:           1.5.0', $contents );
		// Read out of the source header, not from a constant in the renamer. The fixture declares 6.4
		// and 8.9 precisely because no code in this repo names those values.
		self::assertStringContainsString( 'Requires at least: 6.4', $contents );
		self::assertStringContainsString( 'Requires PHP:      8.9', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	/**
	 * A user field carrying the constant's own syntax must not capture the version rewrite.
	 *
	 * Both values are spliced into the header comment above the `define()`. When the rewrite matched a
	 * bare `PLUGIN_VERSION', '…'` anywhere in the file, the header line won the single `limit=1`
	 * replacement and `[^']*` ran across newlines from there to the next quote — deleting
	 * `@wordpress-plugin` and `Plugin Name:` (so WordPress stopped recognising the file as a plugin)
	 * while leaving the real constant at the source version. Anchoring to `define(` fixes both halves,
	 * so this asserts both: the header survives *and* the constant carries the user's version.
	 *
	 * @param string $plugin_name Hostile or benign plugin name.
	 * @param string $description Hostile or benign description.
	 */
	#[DataProvider( 'hostile_version_field_provider' )]
	public function test_build_zip_version_rewrite_cannot_be_captured_by_user_input( string $plugin_name, string $description ): void {
		$source   = $this->make_source_fixture();
		$output   = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => $plugin_name,
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'description'       => $description,
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
					'version'           => '1.5.0',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );
		$contents = $zip->getFromName( 'acme-library/acme-library.php' );
		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );

		self::assertIsString( $contents );

		// The constant, matched only in its `define()` statement so a decoy in the header cannot satisfy it.
		self::assertSame(
			1,
			preg_match( "/define\(\s*(?:__NAMESPACE__\s*\.\s*)?'\\\\?PLUGIN_VERSION'\s*,\s*'([^']*)'/", $contents, $matches ),
			'The PLUGIN_VERSION define() statement must still be present.'
		);
		self::assertSame( '1.5.0', $matches[1], 'The define() must carry the user version, not the source version.' );
		// Not asserted against the whole file: a user field may legitimately contain the source version
		// as prose (one provider case does exactly that). Only the constant's own value matters here.
		self::assertNotSame( '7.7.7', $matches[1] );

		// WordPress will not list a plugin whose header lost these.
		self::assertStringContainsString( '@wordpress-plugin', $contents );
		self::assertStringContainsString( 'Plugin Name:', $contents );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function hostile_version_field_provider(): array {
		return array(
			'benign baseline'        => array( 'Acme Library', 'An ordinary description.' ),
			'name carries the token' => array( "Acme PLUGIN_VERSION', 'x", 'An ordinary description.' ),
			'description carries it' => array( 'Acme Library', "Ships PLUGIN_VERSION', '9.9.9' inside prose" ),
			'both carry it'          => array( "A PLUGIN_VERSION', '7.7.7", "B PLUGIN_VERSION', '8.8.8" ),
		);
	}

	public function test_build_zip_replaces_package_name(): void {
		$source = $this->make_source_fixture( true );
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		self::assertNotFalse( $zip->locateName( 'acme-library/composer.json' ) );
		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringContainsString( '"name": "acme-inc/acme-library"', $contents );
		self::assertStringNotContainsString( 'saltus/framework-demo', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_package_name_trims_leading_trailing_dashes(): void {
		$source = $this->make_source_fixture( true );
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc -',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringContainsString( '"name": "acme-inc/acme-library"', $contents );
		self::assertStringNotContainsString( 'acme-inc-/', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_build_zip_replaces_composer_author_and_homepage(): void {
		$source   = $this->make_source_fixture( true );
		$output   = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'author_uri'        => 'https://acme.dev/',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringContainsString( '"name": "Acme Inc"', $contents );
		self::assertStringContainsString( '"homepage": "https://acme.dev/', $contents );
		self::assertStringNotContainsString( '"name": "Saltus"', $contents );
		self::assertStringNotContainsString( '"homepage": "https://saltus.dev', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_build_zip_adds_saltus_contributor_when_flag_set(): void {
		$source   = $this->make_source_fixture( true );
		$output   = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'        => 'Acme Library',
					'plugin_slug'        => 'acme-library',
					'main_file'          => 'acme-library.php',
					'namespace_segment'  => 'AcmeLibrary',
					'author'             => 'Acme Inc',
					'prefix'             => 'acme_library',
					'saltus_contributor' => '1',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringContainsString( '"name": "Saltus"', $contents );
		self::assertStringContainsString( '"name": "Acme Inc"', $contents );
		self::assertStringContainsString( '"email": "web@saltus.dev"', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_build_zip_omits_saltus_contributor_when_flag_unset(): void {
		$source   = $this->make_source_fixture( true );
		$output   = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringNotContainsString( '"name": "Saltus"', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	public function test_package_name_falls_back_to_local_when_author_has_no_ascii(): void {
		$source = $this->make_source_fixture( true );
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );
		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Иван Петров',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$contents = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $contents );
		self::assertStringContainsString( '"name": "local/acme-library"', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	/**
	 * Backreference syntax in any identity field must survive as literal text.
	 *
	 * `preg_replace()` expands `$0`, `$1`, `${1}` and `\1` in its *replacement* argument, and the
	 * user-built header is passed as exactly that. Before this was escaped, a description of
	 * `Free forever, $0` spliced the demo's whole header inside the new one and produced
	 * `PHP Parse error: unexpected token "*"`, while `Only $5 per site` silently became `Only  per site`.
	 */
	#[DataProvider( 'backreference_provider' )]
	public function test_build_zip_treats_backreferences_as_literal_text( string $field, string $value ): void {
		$source = $this->make_source_fixture();
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );

		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
					$field              => $value,
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );
		$contents = $zip->getFromName( 'acme-library/acme-library.php' );
		$zip->close();

		self::assertIsString( $contents );

		// The value arrives intact, not partially deleted and not expanded into a captured group.
		self::assertStringContainsString( $value, $contents, "The {$field} value was rewritten by preg_replace." );
		self::assertStringNotContainsString( 'Source Header That Should Not Matter', $contents );

		// And the result is still a parseable plugin, which is what a `$0` actually broke.
		$scratch = $source . '/parse-check.php';
		file_put_contents( $scratch, $contents );
		exec( sprintf( 'php -l %s 2>&1', escapeshellarg( $scratch ) ), $lint_output, $lint_status );
		self::assertSame( 0, $lint_status, implode( "\n", $lint_output ) );

		@unlink( $output );
		$this->remove_dir( $source );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function backreference_provider(): array {
		$cases  = array();
		$values = array(
			'dollar-zero'    => 'Free forever, $0',
			'dollar-one'     => 'Only $5 per site',
			'braced'         => 'Tier ${1} plan',
			'backslash-one'  => 'Escaped \1 sequence',
			'backslash-only' => 'Path C:\\temp',
		);

		foreach ( array( 'plugin_name', 'description', 'author' ) as $field ) {
			foreach ( $values as $label => $value ) {
				$cases[ $field . '/' . $label ] = array( $field, $value );
			}
		}

		return $cases;
	}

	/**
	 * Only the plugin's own root manifest carries the user's identity.
	 *
	 * `rewrite_contents()`'s author/homepage replacements ran on every text file, and
	 * `add_saltus_contributor()` was gated on `basename() === 'composer.json'` — which matches every
	 * vendored manifest in the tree. A built ZIP had Guzzle's manifest rewritten, and
	 * `schema/composer.json` carried the user's name against the Saltus email address.
	 */
	public function test_build_zip_rewrites_only_the_root_manifest(): void {
		$source = $this->make_source_fixture( true );
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );

		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'        => 'Acme Library',
					'plugin_slug'        => 'acme-library',
					'main_file'          => 'acme-library.php',
					'namespace_segment'  => 'AcmeLibrary',
					'author'             => 'Acme Inc',
					'author_uri'         => 'https://acme.example/',
					'prefix'             => 'acme_library',
					'saltus_contributor' => '1',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		$root = $zip->getFromName( 'acme-library/composer.json' );
		self::assertIsString( $root );
		self::assertStringContainsString( '"name": "Acme Inc"', $root );
		self::assertStringContainsString( 'https://acme.example/', $root );

		// The schema package describes itself, not the generated plugin.
		$schema_manifest = $zip->getFromName( 'acme-library/schema/composer.json' );
		self::assertIsString( $schema_manifest );
		self::assertStringNotContainsString( 'Acme Inc', $schema_manifest );
		self::assertStringContainsString( '"name": "Saltus"', $schema_manifest );

		$vendored = $zip->getFromName( 'acme-library/vendor-prefixed/guzzlehttp/composer.json' );
		self::assertIsString( $vendored );
		self::assertStringNotContainsString( 'Acme Inc', $vendored );
		self::assertStringNotContainsString( 'web@saltus.dev', $vendored );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	/**
	 * Exclusions match root-relative paths, so a nested README is not caught by the root one.
	 *
	 * `should_exclude()` compared basenames, so the `README.md` entry stripped every README at any depth
	 * and generated plugins shipped the extractable `saltus/model-schema` package with no documentation.
	 */
	public function test_build_zip_keeps_nested_readme_while_excluding_the_root_one(): void {
		$source = $this->make_source_fixture();
		$output = tempnam( sys_get_temp_dir(), 'renamer-' );

		$identity = PluginIdentity::from_request(
			array_merge(
				PluginIdentity::defaults(),
				array(
					'plugin_name'       => 'Acme Library',
					'plugin_slug'       => 'acme-library',
					'main_file'         => 'acme-library.php',
					'namespace_segment' => 'AcmeLibrary',
					'author'            => 'Acme Inc',
					'prefix'            => 'acme_library',
				)
			)
		);

		$renamer = new PluginRenamer( $source );
		$renamer->build_zip( $identity, $output );

		$zip = new ZipArchive();
		self::assertTrue( true === $zip->open( $output ) );

		self::assertFalse( $zip->locateName( 'acme-library/README.md' ), 'The root README describes the demo and must not ship.' );
		self::assertNotFalse( $zip->locateName( 'acme-library/schema/README.md' ), 'The schema package must keep its documentation.' );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	private function make_source_fixture( bool $with_composer = false ): string {
		$source = sys_get_temp_dir() . '/framework-demo-fixture-' . uniqid();
		mkdir( $source . '/src', 0777, true );
		mkdir( $source . '/vendor', 0777, true );
		mkdir( $source . '/vendor-prefixed', 0777, true );
		mkdir( $source . '/vendor-prefixed/composer', 0777, true );
		mkdir( $source . '/build', 0777, true );

		file_put_contents(
			$source . '/framework-demo.php',
			// The source PLUGIN_VERSION is deliberately a version the renamer knows nothing about.
			// It used to be '3.0.0', matching a hardcoded ORIGINAL_VERSION constant, so this test
			// agreed with itself and passed while the real plugin file (already at 3.1.0) was
			// silently not being rewritten at all.
			// `Requires at least` / `Requires PHP` are deliberately values the renamer cannot have
			// hardcoded. They used to be `REQUIRES_WP = '6.0'` / `REQUIRES_PHP = '8.3'` constants that
			// the test asserted verbatim, so the two agreed with each other while drifting from the real
			// plugin file — the identical trap `ORIGINAL_VERSION` fell into.
			"<?php\n/**\n * Source Header That Should Not Matter\n *\n * @wordpress-plugin\n * Plugin Name:       Unrelated Source Plugin\n * Plugin URI:        https://source.invalid/\n * Description:       Source description should not leak.\n * Version:           9.9.9\n * Requires at least: 6.4\n * Requires PHP:      8.9\n * Author:            Source Author\n * Author URI:        https://source-author.invalid/\n * Text Domain:       source-domain\n */\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo;\ndefine( 'PLUGIN_VERSION', '7.7.7' );\n"
		);

		// A nested README and a vendored manifest: both were collateral damage from basename matching.
		mkdir( $source . '/schema', 0777, true );
		mkdir( $source . '/vendor-prefixed/guzzlehttp', 0777, true );
		file_put_contents( $source . '/README.md', "# Saltus Framework Demo\n" );
		file_put_contents( $source . '/schema/README.md', "# saltus/model-schema\n" );
		file_put_contents(
			$source . '/schema/composer.json',
			"{\n\t\"name\": \"saltus/model-schema\",\n\t\"homepage\": \"https://saltus.dev/\",\n\t\"authors\": [\n\t\t{\n\t\t\t\"name\": \"Saltus\",\n\t\t\t\"email\": \"web@saltus.dev\"\n\t\t}\n\t]\n}\n"
		);
		file_put_contents(
			$source . '/vendor-prefixed/guzzlehttp/composer.json',
			"{\n\t\"name\": \"guzzlehttp/guzzle\",\n\t\"authors\": [\n\t\t{\n\t\t\t\"name\": \"Guzzle Contributor\"\n\t\t}\n\t]\n}\n"
		);
		file_put_contents( $source . '/src/Example.php', "<?php\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo;\n" );
		file_put_contents( $source . '/vendor/autoload.php', '<?php' );
		file_put_contents(
			$source . '/vendor-prefixed/autoload.php',
			"<?php\nrequire_once __DIR__ . '/composer/autoload_real.php';\nreturn ComposerAutoloaderInit0515b56810b0a42fc7dbe10cb02ee1c8::getLoader();\nreturn 'Saltus\\\\WP\\\\Plugin\\\\Saltus\\\\PluginFrameworkDemo';\n"
		);
		file_put_contents(
			$source . '/vendor-prefixed/composer/autoload_real.php',
			"<?php\nclass ComposerAutoloaderInit0515b56810b0a42fc7dbe10cb02ee1c8 {}\nfunction composerRequire0515b56810b0a42fc7dbe10cb02ee1c8(\$fileIdentifier, \$file) {}\n\\Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo\\Composer\\Autoload\\ComposerStaticInit0515b56810b0a42fc7dbe10cb02ee1c8::getInitializer();\n"
		);
		file_put_contents(
			$source . '/vendor-prefixed/composer/autoload_static.php',
			"<?php\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo\\Composer\\Autoload;\nclass ComposerStaticInit0515b56810b0a42fc7dbe10cb02ee1c8 {}\n"
		);
		file_put_contents( $source . '/build/Gruntfile.js', 'module.exports = {};' );

		if ( $with_composer ) {
			file_put_contents(
				$source . '/composer.json',
				"{\n\t\"name\": \"saltus/framework-demo\",\n\t\"type\": \"wordpress-plugin\",\n\t\"homepage\": \"https://saltus.dev/\",\n\t\"authors\": [\n\t\t{\n\t\t\t\"name\": \"Saltus\",\n\t\t\t\"email\": \"web@saltus.dev\",\n\t\t\t\"homepage\": \"https://saltus.dev\"\n\t\t}\n\t]\n}\n"
			);
		}

		return $source;
	}

	private function remove_dir( string $dir ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
				continue;
			}

			unlink( $file->getPathname() );
		}

		rmdir( $dir );
	}
}
