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

	public function test_array_input_does_not_cause_php_warning(): void {
		$data              = PluginIdentity::defaults();
		$data['plugin_name'] = [ 'malicious', 'array' ];

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_invalid_url_fails_validation(): void {
		$data               = PluginIdentity::defaults();
		$data['plugin_uri'] = 'not a url';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_invalid_version_fails_validation(): void {
		$data             = PluginIdentity::defaults();
		$data['version']  = 'abc';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_version_without_patch_fails_validation(): void {
		$data             = PluginIdentity::defaults();
		$data['version']  = '1.0';

		$this->expectException( ValidationException::class );

		PluginIdentity::from_request( $data );
	}

	public function test_comment_breakout_is_sanitized(): void {
		$data                    = PluginIdentity::defaults();
		$data['plugin_name']     = 'Foo */ bar';
		$data['description']     = 'Desc */ breakout';
		$data['author']          = 'Auth */ test';

		$identity = PluginIdentity::from_request( $data );

		self::assertSame( 'Foo  bar', $identity->plugin_name );
		self::assertSame( 'Desc  breakout', $identity->description );
		self::assertSame( 'Auth  test', $identity->author );
	}

	public function test_uri_comment_breakout_is_sanitized(): void {
		$data                   = PluginIdentity::defaults();
		$data['author_uri']     = 'https://example.com?a=1*/system(current($_GET));/*';
		$data['plugin_uri']     = 'https://example.com?b=2*/phpinfo();/*';

		$identity = PluginIdentity::from_request( $data );

		self::assertStringNotContainsString( '*/', $identity->author_uri );
		self::assertStringNotContainsString( '*/', $identity->plugin_uri );
		self::assertSame( 'https://example.com?a=1system(current($_GET));/*', $identity->author_uri );
		self::assertSame( 'https://example.com?b=2phpinfo();/*', $identity->plugin_uri );
	}
}
