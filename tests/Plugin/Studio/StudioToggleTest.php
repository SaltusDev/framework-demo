<?php
/**
 * The Studio on/off switch.
 *
 * Studio's runtime code ships into rebranded plugins (verified by building a ZIP), so a generated
 * plugin would otherwise register a model-authoring REST surface nobody asked for. These tests pin
 * both ways to turn it off, and the precedence between them.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;

final class StudioToggleTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['test_rest_routes']    = array();
		$GLOBALS['test_filter_returns'] = array();
		$GLOBALS['test_actions']        = array();
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['test_rest_routes'],
			$GLOBALS['test_filter_returns'],
			$GLOBALS['test_actions']
		);
	}

	/**
	 * Boot the plugin, then fire `rest_api_init`.
	 *
	 * `RestController::register()` only hooks the action; routes are declared when it fires. Booting
	 * without firing would record zero routes whether Studio is enabled or not, so the test would
	 * pass for the wrong reason.
	 */
	private function boot(): void {
		$core = new Core( 'framework-demo', '3.1.0', dirname( __DIR__, 3 ) . '/framework-demo.php' );
		$core->init();

		\saltus_test_do_action( 'rest_api_init' );
	}

	/**
	 * Route keys recorded by the `register_rest_route()` stub.
	 *
	 * @return list<string>
	 */
	private static function studio_routes(): array {
		return array_values(
			array_filter(
				array_keys( (array) ( $GLOBALS['test_rest_routes'] ?? array() ) ),
				static fn( string $route ): bool => str_starts_with( $route, 'framework-demo/v1' )
			)
		);
	}

	public function test_studio_routes_are_registered_by_default(): void {
		$this->boot();

		self::assertSame(
			array( 'framework-demo/v1/models/preview', 'framework-demo/v1/models' ),
			self::studio_routes()
		);
	}

	public function test_the_filter_can_disable_studio(): void {
		$GLOBALS['test_filter_returns']['framework_demo/studio/enabled'] = false;

		$this->boot();

		self::assertSame( array(), self::studio_routes() );
	}

	/**
	 * A truthy filter return is the default path, so an explicit `true` must not change anything.
	 */
	public function test_an_explicit_true_filter_leaves_studio_enabled(): void {
		$GLOBALS['test_filter_returns']['framework_demo/studio/enabled'] = true;

		$this->boot();

		self::assertCount( 2, self::studio_routes() );
	}

	/**
	 * The constant wins over the filter.
	 *
	 * A host that has decided this plugin must not author code should not be overridable from plugin
	 * space, so the constant is checked first and a `true` filter cannot re-enable Studio. Runs in a
	 * subprocess because a constant cannot be undefined once set.
	 */
	public function test_the_constant_cannot_be_overridden_by_the_filter(): void {
		$root = dirname( __DIR__, 3 );

		$script = sprintf(
			'define("FRAMEWORK_DEMO_DISABLE_STUDIO", true);'
			. 'require %s; require %s;'
			. '$GLOBALS["test_rest_routes"] = array();'
			// Filter says yes; the constant must still win.
			. '$GLOBALS["test_filter_returns"] = array("framework_demo/studio/enabled" => true);'
			. '$c = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core("framework-demo", "3.1.0", %s);'
			. '$c->init();'
			. 'echo count(array_filter(array_keys($GLOBALS["test_rest_routes"]),'
			. ' fn($r) => str_starts_with($r, "framework-demo/v1")));',
			var_export( $root . '/vendor/autoload.php', true ),
			var_export( $root . '/tests/bootstrap.php', true ),
			var_export( $root . '/framework-demo.php', true )
		);

		$output = array();
		exec( sprintf( 'php -r %s 2>&1', escapeshellarg( $script ) ), $output );

		self::assertSame( '0', trim( implode( '', $output ) ) );
	}
}
