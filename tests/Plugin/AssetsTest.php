<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Assets;

/**
 * The framework hardcodes its asset base to `vendor/saltus/framework/assets/`, which does not
 * exist once Strauss moves the package to `vendor-prefixed/` and deletes the original. These
 * tests pin the URL correction so framework scripts and styles keep resolving.
 */
class AssetsTest extends TestCase {

	private Assets $assets;

	protected function setUp(): void {
		parent::setUp();

		$this->assets = new Assets( new Core( 'framework-demo', '3.0.0', '/plugins/framework-demo/framework-demo.php' ) );
	}

	public function test_framework_asset_url_is_repointed_to_the_prefixed_directory(): void {
		$src = 'https://example.test/wp-content/plugins/framework-demo/vendor/saltus/framework/assets/Feature/DragAndDrop/order.js';

		self::assertSame(
			'https://example.test/wp-content/plugins/framework-demo/vendor-prefixed/saltus/framework/assets/Feature/DragAndDrop/order.js',
			$this->assets->correct_framework_asset_url( $src )
		);
	}

	public function test_already_prefixed_url_is_left_alone(): void {
		$src = 'https://example.test/plugins/framework-demo/vendor-prefixed/saltus/framework/assets/Feature/Blocks/editor.js';

		self::assertSame( $src, $this->assets->correct_framework_asset_url( $src ) );
	}

	public function test_unrelated_urls_are_untouched(): void {
		$src = 'https://example.test/wp-includes/js/jquery/jquery.min.js';

		self::assertSame( $src, $this->assets->correct_framework_asset_url( $src ) );
	}

	public function test_another_plugins_vendor_assets_are_untouched(): void {
		$src = 'https://example.test/plugins/other/vendor/acme/toolkit/assets/app.js';

		self::assertSame( $src, $this->assets->correct_framework_asset_url( $src ) );
	}

	/**
	 * WordPress passes `false` through these filters when an asset is deregistered.
	 */
	public function test_non_string_input_is_returned_unchanged(): void {
		self::assertFalse( $this->assets->correct_framework_asset_url( false ) );
	}
}
