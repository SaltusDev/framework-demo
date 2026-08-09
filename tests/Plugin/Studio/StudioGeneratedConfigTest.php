<?php
/**
 * Round-trips configs shaped the way the Studio UI builds them.
 *
 * The UI assembles config in TypeScript (`toModelConfig`, `toMetaConfig`, `toAdminColsConfig`) and POSTs
 * it as JSON. These tests take the *result* of that assembly and put it through the real printer, so the
 * contract between the browser and PHP is covered by the PHP suite — which runs in CI, where the
 * TypeScript does not.
 *
 * The payloads below are copied from what the UI produces rather than invented: metabox with
 * dependencies, admin columns keyed in order, options maps, and the demo-toggle gate.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelPrinter;

final class StudioGeneratedConfigTest extends TestCase {

	private ?string $dir = null;

	private int $counter = 0;

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 3 ) . '/bin/model-loader.php';

		// The gate helper a generated file calls. Normally defined by src/models/_demo-toggle.php,
		// which the Modeler loads first thanks to its leading underscore.
		if ( ! function_exists( 'saltus_demo_model_enabled' ) ) {
			function saltus_demo_model_enabled( string $model ): bool {
				return true;
			}
		}
	}

	protected function tearDown(): void {
		if ( $this->dir === null ) {
			return;
		}

		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file );
			}
		}

		rmdir( $this->dir );
		$this->dir     = null;
		$this->counter = 0;
	}

	private function temp_path(): string {
		if ( $this->dir === null ) {
			$this->dir = sys_get_temp_dir() . '/saltus-studio-cfg-' . uniqid();
			mkdir( $this->dir, 0777, true );
		}

		++$this->counter;

		return sprintf( '%s/model-%d.php', $this->dir, $this->counter );
	}

	/**
	 * @return mixed
	 */
	private function evaluate( string $source ) {
		$file = $this->temp_path();
		file_put_contents( $file, $source );

		return include $file;
	}

	private function printer(): ModelPrinter {
		return new ModelPrinter( 'framework-demo' );
	}

	/**
	 * A metabox with a dependency rule, as the MetaFields editor and DependencyBuilder produce it.
	 *
	 * @return array<string, mixed>
	 */
	private static function studio_payload(): array {
		return array(
			'type'     => 'cpt',
			'name'     => 'widget',
			'labels'   => array(
				'has_one'  => 'Widget',
				'has_many' => 'Widgets',
			),
			'options'  => array(
				'public'       => true,
				'show_in_rest' => true,
			),
			'features' => array(
				'admin_cols' => array(
					'cover' => array(
						'title'          => 'Cover',
						'featured_image' => 'thumbnail',
					),
					'sku'   => array(
						'title'    => 'SKU',
						'meta_key' => 'widget_sku',
					),
				),
			),
			'meta'     => array(
				'widget_info' => array(
					'id'     => 'widget_info',
					'title'  => 'Widget Info',
					'fields' => array(
						'enabled' => array(
							'type'  => 'switcher',
							'title' => 'Show extras',
						),
						'colour'  => array(
							'type'       => 'color',
							'title'      => 'Colour',
							'dependency' => array( 'enabled', '==', 'true' ),
						),
						'size'    => array(
							'type'    => 'select',
							'title'   => 'Size',
							'options' => array(
								's' => 'Small',
								'l' => 'Large',
							),
						),
					),
				),
			),
		);
	}

	public function test_a_studio_payload_round_trips_exactly(): void {
		$model     = self::studio_payload();
		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		self::assertIsArray( $recovered );

		$expected = $model;
		$actual   = $recovered;
		ksort( $expected );
		ksort( $actual );

		self::assertEquals( $expected, $actual );
	}

	/**
	 * The pipe-delimited positional array is the format Codestar reads; losing it silently would make
	 * a conditional field either always or never visible.
	 */
	public function test_dependency_rules_survive_the_round_trip(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::studio_payload() ) );

		self::assertSame(
			array( 'enabled', '==', 'true' ),
			$recovered['meta']['widget_info']['fields']['colour']['dependency']
		);
	}

	/**
	 * Multi-field dependencies pipe-delimit every slot, and the positions must stay aligned.
	 */
	public function test_multi_field_dependency_positions_stay_aligned(): void {
		$model = self::studio_payload();
		$model['meta']['widget_info']['fields']['colour']['dependency'] = array( 'enabled|size', '==|any', 'true|s,l' );

		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );
		$rule      = $recovered['meta']['widget_info']['fields']['colour']['dependency'];

		self::assertCount( 3, $rule );
		self::assertSame( 2, count( explode( '|', $rule[0] ) ) );
		self::assertSame( 2, count( explode( '|', $rule[1] ) ) );
		self::assertSame( 2, count( explode( '|', $rule[2] ) ) );
	}

	/**
	 * Admin column order is the rendered column order, so key order has to survive.
	 */
	public function test_admin_column_order_is_preserved(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::studio_payload() ) );

		self::assertSame(
			array( 'cover', 'sku' ),
			array_keys( $recovered['features']['admin_cols'] )
		);
	}

	public function test_field_order_within_a_metabox_is_preserved(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::studio_payload() ) );

		self::assertSame(
			array( 'enabled', 'colour', 'size' ),
			array_keys( $recovered['meta']['widget_info']['fields'] )
		);
	}

	/**
	 * A choice field's options map must stay a map, not become a list.
	 */
	public function test_option_maps_stay_associative(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::studio_payload() ) );

		self::assertSame(
			array(
				's' => 'Small',
				'l' => 'Large',
			),
			$recovered['meta']['widget_info']['fields']['size']['options']
		);
	}

	/**
	 * Even with `meta` and `features` present, the first emitted key must be scalar or
	 * `Modeler::is_multiple()` drops the whole model.
	 */
	public function test_first_key_is_scalar_with_nested_sections_present(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::studio_payload() ) );

		self::assertFalse( is_array( current( (array) $recovered ) ) );
		self::assertSame( 'type', array_key_first( (array) $recovered ) );
	}

	public function test_the_gate_wraps_a_studio_generated_model(): void {
		$source = $this->printer()->print_file( self::studio_payload(), 'Generated by Saltus Studio.', 'widget' );

		self::assertStringContainsString( "if ( ! saltus_demo_model_enabled( 'widget' ) ) {", $source );
		self::assertStringContainsString( 'Generated by Saltus Studio.', $source );

		// Still a working model file once the gate passes.
		self::assertIsArray( $this->evaluate( $source ) );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Sectioned metaboxes
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * A two-section metabox, as the editor emits it once a second section exists.
	 *
	 * @return array<string, mixed>
	 */
	private static function sectioned_payload(): array {
		return array(
			'type' => 'cpt',
			'name' => 'widget',
			'meta' => array(
				'widget_info' => array(
					'id'       => 'widget_info',
					'title'    => 'Widget Info',
					'sections' => array(
						'details' => array(
							'title'  => 'Details',
							'icon'   => 'fa fa-cog',
							'fields' => array(
								'enabled' => array(
									'type'  => 'switcher',
									'title' => 'Show extras',
								),
							),
						),
						'extras'  => array(
							'title'  => 'Extras',
							'fields' => array(
								// Depends on a field in the *other* section.
								'colour' => array(
									'type'       => 'color',
									'title'      => 'Colour',
									'dependency' => array( 'enabled', '==', 'true' ),
								),
							),
						),
					),
				),
			),
		);
	}

	public function test_a_sectioned_metabox_round_trips(): void {
		$model     = self::sectioned_payload();
		$recovered = $this->evaluate( $this->printer()->print_file( $model ) );

		$expected = $model;
		$actual   = (array) $recovered;
		ksort( $expected );
		ksort( $actual );

		self::assertEquals( $expected, $actual );
	}

	/**
	 * Section order is tab order, so it has to survive.
	 */
	public function test_section_order_is_preserved(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::sectioned_payload() ) );

		self::assertSame(
			array( 'details', 'extras' ),
			array_keys( $recovered['meta']['widget_info']['sections'] )
		);
	}

	/**
	 * Codestar evaluates dependencies against the whole option set, so a field on one tab may depend on
	 * a field on another. This is what makes the cross-section move safe.
	 */
	public function test_a_cross_section_dependency_survives(): void {
		$recovered = $this->evaluate( $this->printer()->print_file( self::sectioned_payload() ) );

		self::assertSame(
			array( 'enabled', '==', 'true' ),
			$recovered['meta']['widget_info']['sections']['extras']['fields']['colour']['dependency']
		);
	}

	/**
	 * The framework's own provider must resolve both sections and attribute each field to its section —
	 * the check that proves the emitted shape is one the framework actually reads.
	 */
	public function test_the_framework_parses_both_sections(): void {
		/*
		 * Prefixed first because that is what ships, unprefixed as fallback: `vendor-prefixed/` only
		 * exists after `composer prefix-namespaces`, so a plain `composer install` — all CI does before
		 * the test gate — leaves only `vendor/`. Strauss rewrites the namespace and nothing else, so
		 * either class normalizes identically. Resolving only the prefixed name made this test skip on
		 * every CI run, which is coverage lost to an environment detail.
		 */
		$root           = dirname( __DIR__, 3 );
		$provider_class = null;

		/*
		 * Keyed by the tree that must exist on disk before the class name is probed. Asking for the
		 * unprefixed name when no package is installed hits Strauss's alias autoloader, which throws
		 * `Error: Class not found` instead of letting `class_exists()` return false (ROADMAP P4.1). The
		 * disk check keeps that bug from turning a legitimate skip into a test error.
		 */
		$candidates = array(
			'vendor-prefixed' => 'Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Saltus\WP\Framework\Features\Meta\MetaFieldProvider',
			'vendor'          => 'Saltus\WP\Framework\Features\Meta\MetaFieldProvider',
		);

		foreach ( $candidates as $tree => $candidate ) {
			if ( ! is_dir( $root . '/' . $tree . '/saltus/framework' ) ) {
				continue;
			}

			if ( class_exists( $candidate ) ) {
				$provider_class = $candidate;
				break;
			}
		}

		if ( $provider_class === null ) {
			self::markTestSkipped( 'Framework not installed; run composer install.' );
		}

		$provider   = new $provider_class();
		$normalized = $provider->normalize_meta_fields( self::sectioned_payload()['meta'] );

		self::assertCount( 2, $normalized['fields'] );
		self::assertSame(
			array( 'details', 'extras' ),
			array_column( $normalized['fields'], 'section_id' )
		);
		self::assertSame( 'boolean', $normalized['fields'][0]['type'] );
	}

	/**
	 * The generated file must satisfy this repo's own ruleset: developers commit it.
	 */
	public function test_generated_output_is_phpcs_clean(): void {
		$root = dirname( __DIR__, 3 );
		$file = $this->temp_path();
		file_put_contents( $file, $this->printer()->print_file( self::studio_payload() ) );

		$output = array();
		$status = 0;
		exec(
			sprintf(
				'%s --standard=%s %s 2>&1',
				escapeshellarg( $root . '/vendor/bin/phpcs' ),
				escapeshellarg( $root . '/phpcs.xml' ),
				escapeshellarg( $file )
			),
			$output,
			$status
		);

		self::assertSame( 0, $status, implode( "\n", $output ) );
	}
}
