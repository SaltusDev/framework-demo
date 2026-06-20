<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Renamer;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginIdentity;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\ValidationException;

class PluginIdentityTest extends TestCase {

	public function test_valid_identity_can_be_created_from_request(): void {
		$identity = PluginIdentity::from_request( PluginIdentity::defaults() );

		self::assertSame( 'My Saltus Plugin', $identity->plugin_name );
		self::assertSame( 'my-saltus-plugin', $identity->plugin_slug );
		self::assertSame( 'MySaltusPlugin', $identity->namespace_segment );
	}

	public function test_invalid_slug_fails_validation(): void {
		$data                = PluginIdentity::defaults();
		$data['plugin_slug'] = 'Bad Slug';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_invalid_namespace_fails_validation(): void {
		$data                       = PluginIdentity::defaults();
		$data['namespace_segment']  = 'bad-plugin';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_invalid_url_fails_validation(): void {
		$data               = PluginIdentity::defaults();
		$data['plugin_uri'] = 'not a url';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}
}
