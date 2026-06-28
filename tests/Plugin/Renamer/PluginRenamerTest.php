<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Renamer;

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
		self::assertStringNotContainsString( "PLUGIN_VERSION', '2.0.0'", $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
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

	private function make_source_fixture( bool $with_composer = false ): string {
		$source = sys_get_temp_dir() . '/framework-demo-fixture-' . uniqid();
		mkdir( $source . '/src', 0777, true );
		mkdir( $source . '/vendor', 0777, true );
		mkdir( $source . '/vendor-prefixed', 0777, true );
		mkdir( $source . '/vendor-prefixed/composer', 0777, true );
		mkdir( $source . '/build', 0777, true );

		file_put_contents(
			$source . '/framework-demo.php',
			"<?php\n/**\n * Source Header That Should Not Matter\n *\n * @wordpress-plugin\n * Plugin Name:       Unrelated Source Plugin\n * Plugin URI:        https://source.invalid/\n * Description:       Source description should not leak.\n * Version:           9.9.9\n * Author:            Source Author\n * Author URI:        https://source-author.invalid/\n * Text Domain:       source-domain\n */\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo;\ndefine( 'PLUGIN_VERSION', '2.0.0' );\n"
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
				"{\n\t\"name\": \"saltus/framework-demo\",\n\t\"type\": \"wordpress-plugin\"\n}\n"
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
