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
					'text_domain'       => 'acme-library',
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
		self::assertFalse( $zip->locateName( 'acme-library/build/Gruntfile.js' ) );

		$contents = $zip->getFromName( 'acme-library/acme-library.php' );
		self::assertIsString( $contents );
		self::assertStringContainsString( 'Plugin Name:       Acme Library', $contents );
		self::assertStringContainsString( 'namespace Saltus\\WP\\Plugin\\Saltus\\AcmeLibrary;', $contents );
		self::assertStringNotContainsString( 'framework-demo', $contents );

		$zip->close();
		@unlink( $output );
		$this->remove_dir( $source );
	}

	private function make_source_fixture(): string {
		$source = sys_get_temp_dir() . '/framework-demo-fixture-' . uniqid();
		mkdir( $source . '/src', 0777, true );
		mkdir( $source . '/vendor', 0777, true );
		mkdir( $source . '/build', 0777, true );

		file_put_contents(
			$source . '/framework-demo.php',
			"<?php\n/**\n * Plugin Name:       Saltus Framework Demo\n * Description:       Saltus Plugin Framework Demo.\n * Version:           2.0.0\n * Author:            Saltus\n * Text Domain:       framework-demo\n */\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo;\ndefine( 'FRAMEWORK_DEMO_EXAMPLE', 'framework-demo' );\n"
		);
		file_put_contents( $source . '/src/Example.php', "<?php\nnamespace Saltus\\WP\\Plugin\\Saltus\\PluginFrameworkDemo;\n" );
		file_put_contents( $source . '/vendor/autoload.php', '<?php' );
		file_put_contents( $source . '/build/Gruntfile.js', 'module.exports = {};' );

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
