<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\CodestarCompat;

/**
 * Strauss's alias autoloader defines forwarding shims for Codestar's global `csf_*` functions but
 * fails to define the classes whose `enqueue()` would load those functions' real definitions. The
 * shim then points at nothing, and any screen rendering a `typography` field fatals.
 *
 * These tests pin the compat shim's contract. They deliberately do not assert on Codestar internals,
 * which are absent from the unit-test bootstrap — the live verification is a wp-load probe confirming
 * `csf_get_google_fonts()` returns fonts rather than throwing.
 */
class CodestarCompatTest extends TestCase {

	public function test_register_is_safe_without_codestar_present(): void {
		$compat = new CodestarCompat();

		$compat->register();

		$this->expectNotToPerformAssertions();
	}

	/**
	 * The loader must degrade quietly when the prefixed Codestar classes are not loaded, so a
	 * `--no-dev` install or a rebranded plugin with a different prefix cannot fatal on boot.
	 */
	public function test_loading_is_a_noop_when_codestar_root_cannot_be_resolved(): void {
		$compat = new CodestarCompat();

		$compat->load_deferred_function_files();

		$this->expectNotToPerformAssertions();
	}

	public function test_loading_is_idempotent(): void {
		$compat = new CodestarCompat();

		$compat->load_deferred_function_files();
		$compat->load_deferred_function_files();

		$this->expectNotToPerformAssertions();
	}
}
