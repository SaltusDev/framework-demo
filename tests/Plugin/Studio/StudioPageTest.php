<?php
/**
 * The boot-data contract between the plugin and the React app.
 *
 * This is the seam where the generated enums cross into JavaScript. The app deliberately hardcodes
 * none of them — a TypeScript copy would be a second source of truth, which is the drift this whole
 * epic exists to eliminate — so these tests pin that the payload actually carries them.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\StudioPage;

final class StudioPageTest extends TestCase {

	private static function root(): string {
		return dirname( __DIR__, 3 );
	}

	private function page(): StudioPage {
		return new StudioPage( new Core( 'framework-demo', '3.1.0', self::root() . '/framework-demo.php' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function boot_data(): array {
		// No setAccessible(): it has had no effect since PHP 8.1 and is deprecated in 8.5.
		$method = new ReflectionMethod( StudioPage::class, 'boot_data' );

		/** @var array<string, mixed> $data */
		$data = $method->invoke( $this->page() );

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function enums(): array {
		return require self::root() . '/schema/model-enums.php';
	}

	public function test_boot_data_carries_every_key_the_app_reads(): void {
		$data = $this->boot_data();

		foreach ( array(
			'restRoot',
			'restNamespace',
			'nonce',
			'typeAliases',
			'fieldTypes',
			'maxNameLength',
			'reservedPostTypes',
			'reservedTaxonomies',
			'schemaVersion',
			'canWrite',
		) as $key ) {
			self::assertArrayHasKey( $key, $data, "boot data is missing {$key}" );
		}
	}

	/**
	 * The REST namespace must match what `RestController` registers, or every request 404s.
	 */
	public function test_rest_namespace_matches_the_controller(): void {
		$controller = (string) file_get_contents( self::root() . '/src/Plugin/Studio/RestController.php' );

		self::assertSame( 1, preg_match( "/REST_NAMESPACE\s*=\s*'([^']+)'/", $controller, $matches ) );
		self::assertSame( $matches[1], $this->boot_data()['restNamespace'] );
	}

	/**
	 * Field types come from the generated map, not from a hardcoded list.
	 */
	public function test_field_types_come_from_the_generated_enums(): void {
		$enums = self::enums();
		$data  = $this->boot_data();

		self::assertSame( $enums['field_types'], $data['fieldTypes'] );
		self::assertCount( 44, $data['fieldTypes'] );
	}

	public function test_type_aliases_and_ceilings_come_from_the_generated_enums(): void {
		$enums = self::enums();
		$data  = $this->boot_data();

		self::assertSame( $enums['type_aliases'], $data['typeAliases'] );
		self::assertSame( $enums['max_name_length'], $data['maxNameLength'] );
		self::assertSame( $enums['schema_version'], $data['schemaVersion'] );
	}

	/**
	 * Reserved names ship in the enum map so the linter, the browser and rebranded plugins share one
	 * list. `bin/` is excluded from generated plugins; `schema/` is not.
	 */
	public function test_reserved_names_come_from_the_generated_enums(): void {
		$enums = self::enums();
		$data  = $this->boot_data();

		self::assertSame( $enums['reserved_post_types'], $data['reservedPostTypes'] );
		self::assertSame( $enums['reserved_taxonomies'], $data['reservedTaxonomies'] );
		self::assertContains( 'page', $data['reservedPostTypes'] );
		self::assertContains( 'category', $data['reservedTaxonomies'] );
	}

	/**
	 * `canWrite` lets the UI disable saving up front instead of after a failed request.
	 */
	public function test_can_write_is_true_when_no_constant_forbids_it(): void {
		self::assertTrue( $this->boot_data()['canWrite'] );
	}

	/**
	 * Under `DISALLOW_FILE_MODS` the payload must report writes as unavailable. Subprocess, because a
	 * constant cannot be undefined once set.
	 */
	public function test_can_write_is_false_under_disallow_file_mods(): void {
		$root = self::root();

		$script = sprintf(
			'define("DISALLOW_FILE_MODS", true);'
			. 'require %s; require %s;'
			. '$p = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\StudioPage('
			. ' new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core("framework-demo", "3.1.0", %s));'
			. '$m = new ReflectionMethod($p, "boot_data"); $m->setAccessible(true);'
			. 'echo $m->invoke($p)["canWrite"] ? "true" : "false";',
			var_export( $root . '/vendor/autoload.php', true ),
			var_export( $root . '/tests/bootstrap.php', true ),
			var_export( $root . '/framework-demo.php', true )
		);

		$output = array();
		exec( sprintf( 'php -r %s 2>&1', escapeshellarg( $script ) ), $output );

		self::assertSame( 'false', trim( implode( '', $output ) ) );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Schema location
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * Consumers must resolve the schema through `Schema`, which prefers an installed
	 * `saltus/model-schema` package over the in-repo directory. That is what makes publishing the
	 * package a change to one class instead of a hunt through call sites.
	 */
	public function test_schema_resolves_the_in_repo_directory_by_default(): void {
		$schema = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\Schema( self::root() );

		self::assertSame( self::root() . '/schema', $schema->directory() );
		self::assertFileExists( $schema->json_path() );
		self::assertFileExists( $schema->enums_path() );
		self::assertSame( self::enums(), $schema->enums() );
	}

	/**
	 * With the package installed, the same lookup finds it instead — no consumer changes.
	 */
	public function test_schema_prefers_an_installed_package(): void {
		$root = sys_get_temp_dir() . '/saltus-schema-' . uniqid();
		mkdir( $root . '/vendor/saltus/model-schema', 0777, true );
		mkdir( $root . '/schema', 0777, true );

		try {
			$schema = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\Schema( $root );

			self::assertSame( $root . '/vendor/saltus/model-schema', $schema->directory() );
		} finally {
			rmdir( $root . '/schema' );
			rmdir( $root . '/vendor/saltus/model-schema' );
			rmdir( $root . '/vendor/saltus' );
			rmdir( $root . '/vendor' );
			rmdir( $root );
		}
	}

	/**
	 * A missing enum map degrades validation rather than fataling: the Studio screen already reports
	 * when the schema has not been generated, and a fatal would be the worse failure.
	 */
	public function test_schema_returns_an_empty_map_when_not_generated(): void {
		$root = sys_get_temp_dir() . '/saltus-schema-empty-' . uniqid();
		mkdir( $root, 0777, true );

		try {
			$schema = new \Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\Schema( $root );

			self::assertSame( array(), $schema->enums() );
		} finally {
			rmdir( $root );
		}
	}

	/**
	 * The package is described well enough to extract: metadata present, version independent of the
	 * plugin, and a README explaining the move.
	 */
	public function test_the_schema_directory_is_packaged(): void {
		$manifest = json_decode( (string) file_get_contents( self::root() . '/schema/composer.json' ), true );

		self::assertIsArray( $manifest );
		self::assertSame( 'saltus/model-schema', $manifest['name'] );
		self::assertFileExists( self::root() . '/schema/README.md' );

		// The schema version must not track the plugin version: consumers pin against the schema.
		$plugin_version = '3.1.0';
		self::assertNotSame( $plugin_version, self::enums()['schema_version'] );
	}

	/**
	 * The menu parent must be a top-level entry, not another submenu's slug.
	 *
	 * `framework-demo-settings` is itself a Codestar submenu of `edit.php?post_type=book`, so parenting to
	 * it populated `$submenu['framework-demo-settings']` and rendered nothing —
	 * `wp-admin/menu-header.php` only walks submenus of entries present in `$menu`. The page still
	 * resolved when reached directly at `admin.php?page=framework-demo-studio`, which is why manual
	 * testing missed it entirely.
	 */
	public function test_the_page_attaches_to_a_top_level_menu_entry(): void {
		$GLOBALS['test_submenu_pages'] = array();

		$this->page()->add_page();

		self::assertCount( 1, $GLOBALS['test_submenu_pages'] );

		$parent = $GLOBALS['test_submenu_pages'][0][0];

		self::assertSame( 'edit.php?post_type=book', $parent );

		// The settings page is a submenu of that same parent, so using its slug renders nothing.
		self::assertNotSame( 'framework-demo-settings', $parent );

		// And the parent really is how the framework registers the settings page, not a guess.
		$model = (string) file_get_contents( self::root() . '/src/models/post-type-all.php' );
		self::assertSame( 1, preg_match( "/'name'\s*=>\s*'book'/", $model ) );
		self::assertStringNotContainsString( "'menu_type'", $model, 'A menu_type override would change the parent this test pins.' );
	}

	/**
	 * The boot payload reaches the browser through `enqueue()`, not just through `boot_data()`.
	 *
	 * `plugins_url()` and `wp_set_script_translations()` were unstubbed, so calling `enqueue()` fataled
	 * and it had no coverage at all — while the boot-data assertions all went through reflection on the
	 * private `boot_data()`, bypassing the path that actually ships the payload.
	 */
	public function test_enqueue_ships_the_boot_payload_on_its_own_screen(): void {
		$GLOBALS['test_enqueued_scripts']    = array();
		$GLOBALS['test_localized']           = array();
		$GLOBALS['test_script_translations'] = array();

		$page = $this->page();

		// A different screen must enqueue nothing.
		$page->enqueue( 'edit.php' );
		self::assertSame( array(), $GLOBALS['test_enqueued_scripts'] );

		$page->enqueue( 'saltus_page_framework-demo-studio' );

		self::assertArrayHasKey( 'framework-demo-studio', $GLOBALS['test_enqueued_scripts'] );

		$script = $GLOBALS['test_enqueued_scripts']['framework-demo-studio'];
		self::assertStringContainsString( 'assets/studio/saltus-studio.js', (string) $script['src'] );

		// React comes from WordPress's own handles rather than being bundled a second time.
		self::assertContains( 'react', $script['deps'] );
		self::assertContains( 'react-dom', $script['deps'] );

		$boot = $GLOBALS['test_localized']['framework-demo-studio']['saltusStudioBoot'] ?? null;
		self::assertIsArray( $boot );
		self::assertArrayHasKey( 'nonce', $boot );
		self::assertArrayHasKey( 'fieldTypes', $boot );

		self::assertSame( 'framework-demo', $GLOBALS['test_script_translations']['framework-demo-studio'] ?? null );
	}

	/**
	 * The token Studio issues must be accepted by the route Studio calls.
	 *
	 * This is the integration the mocks previously made untestable: `wp_create_nonce()` and
	 * `wp_verify_nonce()` were not inverses, so `RestControllerTest` had to hardcode `'good-nonce'` on
	 * both sides and never exercised the real pairing.
	 */
	public function test_the_issued_nonce_verifies_against_the_rest_route(): void {
		$GLOBALS['test_enqueued_scripts'] = array();
		$GLOBALS['test_localized']        = array();
		// Deliberately empty: the token must verify because it was *issued*, not because a test
		// registered it by hand.
		$GLOBALS['test_valid_nonces']     = array();

		$this->page()->enqueue( 'saltus_page_framework-demo-studio' );

		$boot = $GLOBALS['test_localized']['framework-demo-studio']['saltusStudioBoot'] ?? array();
		self::assertIsArray( $boot );
		self::assertNotEmpty( $boot['nonce'] );

		self::assertSame( 1, wp_verify_nonce( $boot['nonce'], 'wp_rest' ) );

		// And it is scoped: the same token must not verify against a different action.
		self::assertFalse( wp_verify_nonce( $boot['nonce'], 'some_other_action' ) );
	}

	/**
	 * The compiled app must live where the release ZIP and the renamer will actually carry it.
	 *
	 * `build/` is excluded from both, so an app placed there would never reach a user — the page would
	 * render its "not built" notice forever. This asserts the path stays under `assets/`.
	 */
	public function test_the_build_path_is_inside_a_shipped_directory(): void {
		$source = (string) file_get_contents( self::root() . '/src/Plugin/Studio/StudioPage.php' );

		self::assertSame( 1, preg_match( "/BUILD_RELATIVE_PATH\s*=\s*'([^']+)'/", $source, $matches ) );
		self::assertStringStartsWith( 'assets/', $matches[1] );

		foreach ( array( 'bin/PackageBuilder.php', 'src/Plugin/Renamer/PluginRenamer.php' ) as $excluder ) {
			$contents = (string) file_get_contents( self::root() . '/' . $excluder );

			self::assertStringNotContainsString(
				"'assets'",
				$contents,
				$excluder . ' must not exclude assets/, or the compiled app would never ship.'
			);
		}
	}
}
