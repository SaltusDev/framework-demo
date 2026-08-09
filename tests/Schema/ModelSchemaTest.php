<?php
/**
 * Guards the generated model-configuration schema.
 *
 * These tests deliberately avoid a JSON Schema validator: none is a direct dependency, and the
 * point here is that the *generated artifacts match the framework they describe*. Structural
 * validation of model files against the schema belongs to `bin/lint-models.php`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Schema;

use PHPUnit\Framework\TestCase;

final class ModelSchemaTest extends TestCase {

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function schema(): array {
		$decoded = json_decode(
			(string) file_get_contents( self::root() . '/schema/model.schema.json' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		self::assertIsArray( $decoded );

		return $decoded;
	}

	/**
	 * Resolve a framework-relative path from whichever tree is installed.
	 *
	 * Mirrors `SALTUS_FRAMEWORK_CANDIDATES` in `bin/generate-model-schema.php`: `vendor-prefixed/` is
	 * what ships, but it only exists after `composer prefix-namespaces`, so a plain `composer install`
	 * leaves only `vendor/`. Returns null when neither tree holds the path, so callers can skip rather
	 * than fail on an environment problem.
	 *
	 * Either tree answers these tests identically — Strauss rewrites namespaces inside PHP files, and
	 * everything read here is either a directory listing or a regex over source text.
	 *
	 * @param string $relative Path beneath the framework root.
	 */
	private static function framework_path( string $relative ): ?string {
		foreach ( array( 'vendor-prefixed', 'vendor' ) as $tree ) {
			$path = self::root() . '/' . $tree . '/saltus/framework/' . $relative;

			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * The framework's Codestar fields directory, from whichever tree is installed.
	 */
	private static function framework_fields_dir(): ?string {
		return self::framework_path( 'lib/codestar-framework/fields' );
	}

	/**
	 * Every Codestar field type is one directory under the framework's fields directory.
	 *
	 * @return list<string>
	 */
	private static function framework_field_types(): array {
		$dir = self::framework_fields_dir();

		if ( $dir === null ) {
			self::markTestSkipped( 'Framework not installed; run composer install.' );
		}

		$types = array();

		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( $entry === '.' || $entry === '..' || ! is_dir( $dir . '/' . $entry ) ) {
				continue;
			}
			$types[] = (string) $entry;
		}

		sort( $types );

		return $types;
	}

	public function test_schema_artifacts_are_committed(): void {
		foreach ( array( 'model.schema.json', 'model-enums.php', 'model.d.ts' ) as $artifact ) {
			self::assertFileExists(
				self::root() . '/schema/' . $artifact,
				"Missing generated artifact: schema/{$artifact}. Run: composer schema"
			);
		}
	}

	/**
	 * The generator is the single source of truth, so re-running it must be a no-op.
	 *
	 * This is the same guarantee CI enforces via `composer schema:check`, asserted here so a local
	 * `phpunit` run catches drift too.
	 *
	 * Skips when no framework tree is installed. Staleness is a claim about whether the committed
	 * artifacts match the framework; with no framework to compare against there is nothing to assert,
	 * and failing would report an environment problem as a drift failure. The other three schema tests
	 * here already skip on the same condition — this one used to fail instead, which is what made
	 * `composer test:unit` red on a bare checkout.
	 */
	public function test_generated_artifacts_are_not_stale(): void {
		if ( self::framework_fields_dir() === null ) {
			self::markTestSkipped( 'Framework not installed; run composer install.' );
		}

		$generator = self::root() . '/bin/generate-model-schema.php';
		self::assertFileExists( $generator );

		$command = sprintf( 'php %s --check 2>&1', escapeshellarg( $generator ) );
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status );

		self::assertSame(
			0,
			$status,
			"Generated schema artifacts are stale. Run: composer schema\n" . implode( "\n", $output )
		);
	}

	public function test_schema_declares_draft_2020_12(): void {
		$schema = self::schema();

		self::assertSame( 'https://json-schema.org/draft/2020-12/schema', $schema['$schema'] );
		self::assertArrayHasKey( '$id', $schema );
		self::assertArrayHasKey( 'x-schema-version', $schema );
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', (string) $schema['x-schema-version'] );
	}

	/**
	 * The field-type enum must match the framework exactly.
	 *
	 * OmensUI registers 78 field types; the framework ships 44. Studio may only offer the 44,
	 * because the framework's `matched_fields` map silently ignores anything else — so a drift in
	 * either direction is a bug worth failing a build over.
	 */
	public function test_field_type_enum_matches_the_framework(): void {
		$schema   = self::schema();
		$expected = self::framework_field_types();
		$actual   = $schema['$defs']['fieldType']['enum'] ?? null;

		self::assertIsArray( $actual );
		self::assertSame( $expected, $actual );
		self::assertCount( 44, $actual, 'The framework is expected to ship 44 Codestar field types.' );
	}

	/**
	 * The `type` enum must match `ModelFactory::create()`'s accepted aliases.
	 */
	public function test_type_enum_matches_the_model_factory(): void {
		$factory = self::framework_path( 'src/Models/ModelFactory.php' );

		if ( $factory === null ) {
			self::markTestSkipped( 'Framework not installed; run composer install.' );
		}

		$contents = (string) file_get_contents( $factory );
		self::assertSame( 1, preg_match( '/\$type_map\s*=\s*\[(.*?)\];/s', $contents, $block ) );

		preg_match_all( "/'([a-z_-]+)'\s*=>\s*self::(POST|TAXONOMY)\b/", $block[1], $matches, PREG_SET_ORDER );
		self::assertNotEmpty( $matches );

		$expected = array();

		foreach ( $matches as $match ) {
			$expected[ $match[1] ] = $match[2] === 'POST' ? 'post' : 'taxonomy';
		}

		ksort( $expected );

		$schema = self::schema();

		self::assertSame( $expected, $schema['x-model-type-map'] );
		self::assertSame(
			array_keys( $expected ),
			$schema['oneOf'][0]['properties']['type']['enum'] ?? null
		);
	}

	/**
	 * `type` is the one genuinely required key: `ModelFactory::create()` returns null without it,
	 * silently, which is the failure mode this whole schema exists to make visible.
	 */
	public function test_type_is_the_only_required_property(): void {
		$schema = self::schema();

		self::assertSame( array( 'type' ), $schema['oneOf'][0]['required'] ?? null );
	}

	/**
	 * The PHP enum map must agree with the JSON Schema, since the linter reads the former and
	 * editors read the latter.
	 */
	public function test_php_enum_map_agrees_with_the_schema(): void {
		$map = require self::root() . '/schema/model-enums.php';

		self::assertIsArray( $map );

		$schema = self::schema();

		self::assertSame( $schema['x-schema-version'], $map['schema_version'] );
		self::assertSame( $schema['$defs']['fieldType']['enum'], $map['field_types'] );
		self::assertSame( $schema['x-model-type-map'], $map['type_aliases'] );
		self::assertSame(
			array(
				'post'     => 20,
				'taxonomy' => 32,
			),
			$map['max_name_length'],
			'Name ceilings must match BaseModel::get_registration_name().'
		);
	}

	/**
	 * The TypeScript union must carry the same field types, so Studio cannot offer a type the
	 * framework will ignore.
	 */
	public function test_typescript_union_carries_every_field_type(): void {
		$contents = (string) file_get_contents( self::root() . '/schema/model.d.ts' );
		$expected = self::framework_field_types();

		foreach ( $expected as $type ) {
			self::assertStringContainsString( "  | '{$type}'", $contents );
		}

		self::assertSame(
			1,
			preg_match( '/SALTUS_FIELD_TYPES.*?\[(.*?)\] as const;/s', $contents, $list )
		);
		self::assertSame( count( $expected ), preg_match_all( "/'[a-z_]+'/", $list[1] ) );
	}
}
