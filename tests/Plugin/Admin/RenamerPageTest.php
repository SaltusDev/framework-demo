<?php
/**
 * The renamer form's checkbox round trip.
 *
 * No test referenced `RenamerPage` at all, so three things were unverified: `CHECKBOX_FIELDS`, the
 * `array_fill_keys` seeding that lets `array_intersect_key` restore a checkbox from a failed
 * submission, and `form_values_from_request()`. The `checked()` mock in `tests/bootstrap.php` existed
 * and was never called by anything.
 *
 * That matters beyond tidiness. `render_checkbox_field()` originally never emitted `checked`, and the
 * redisplay loop is driven by `PluginIdentity::defaults()`, which holds only text fields — so any
 * checkbox silently reverted to unticked whenever the form came back with a validation error. Harmless
 * for the contributor credit; not harmless for `include_studio`, where a user who ticked the box,
 * fluffed another field and resubmitted would receive a plugin with Studio disabled and no indication
 * why.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Admin\RenamerPage;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginIdentity;

final class RenamerPageTest extends TestCase {

	private static function root(): string {
		return dirname( __DIR__, 3 );
	}

	private function page(): RenamerPage {
		return new RenamerPage( new Core( 'framework-demo', '3.1.0', self::root() . '/framework-demo.php' ) );
	}

	/**
	 * @return list<string>
	 */
	private static function checkbox_fields(): array {
		/** @var list<string> $fields */
		$fields = ( new ReflectionClass( RenamerPage::class ) )->getConstant( 'CHECKBOX_FIELDS' );

		return $fields;
	}

	/**
	 * @param array<string, mixed> $request Simulated $_POST.
	 * @return array<string, string>
	 */
	private function form_values( array $request ): array {
		$method = new ReflectionMethod( RenamerPage::class, 'form_values_from_request' );

		/** @var array<string, string> $values */
		$values = $method->invoke( $this->page(), $request );

		return $values;
	}

	private function render_checkbox( string $id, bool $checked ): string {
		$method = new ReflectionMethod( RenamerPage::class, 'render_checkbox_field' );

		ob_start();
		$method->invoke( $this->page(), $id, 'Label', 'Description', $checked );

		return (string) ob_get_clean();
	}

	public function test_both_opt_ins_are_declared_as_checkboxes(): void {
		self::assertSame( array( 'saltus_contributor', 'include_studio' ), self::checkbox_fields() );
	}

	/**
	 * The keys that carry a checkbox are absent from `defaults()`, which is why they must be seeded.
	 *
	 * If this ever stops being true the `array_fill_keys` seeding becomes redundant rather than wrong,
	 * but the assertion documents why it is there.
	 */
	public function test_checkbox_keys_are_not_part_of_the_text_field_defaults(): void {
		foreach ( self::checkbox_fields() as $field ) {
			self::assertArrayNotHasKey( $field, PluginIdentity::defaults() );
		}
	}

	#[DataProvider( 'checkbox_field_provider' )]
	public function test_a_ticked_box_round_trips_through_a_failed_submission( string $field ): void {
		$submitted = $this->form_values( array( $field => '1' ) );

		self::assertSame( '1', $submitted[ $field ] );

		// The redisplay path: seed the checkbox keys, then restore from the stored submission exactly
		// as `render_page()` does.
		$values   = array_merge( PluginIdentity::defaults(), array_fill_keys( self::checkbox_fields(), '' ) );
		$restored = array_merge( $values, array_intersect_key( $submitted, $values ) );

		self::assertSame( '1', $restored[ $field ], 'A ticked box must survive the error redirect.' );
		self::assertStringContainsString( "checked='checked'", $this->render_checkbox( $field, $restored[ $field ] === '1' ) );
	}

	/**
	 * The unchecked case, which is the one that hid the original bug.
	 *
	 * An unticked checkbox submits nothing at all, so the value must come back as `''` — not absent
	 * (which would leave `array_intersect_key` unable to restore it) and not `'0'` (which
	 * `render_page()`'s `=== '1'` test would read as off anyway, but which would misrepresent what the
	 * browser sent).
	 */
	#[DataProvider( 'checkbox_field_provider' )]
	public function test_an_unticked_box_round_trips_as_empty( string $field ): void {
		$submitted = $this->form_values( array() );

		self::assertArrayHasKey( $field, $submitted );
		self::assertSame( '', $submitted[ $field ] );

		$values   = array_merge( PluginIdentity::defaults(), array_fill_keys( self::checkbox_fields(), '' ) );
		$restored = array_merge( $values, array_intersect_key( $submitted, $values ) );

		self::assertSame( '', $restored[ $field ] );
		self::assertStringNotContainsString( 'checked', $this->render_checkbox( $field, $restored[ $field ] === '1' ) );
	}

	/**
	 * Only a real submitted value counts as ticked.
	 *
	 * `include_studio` gates whether a generated plugin can write PHP into its own models directory, so
	 * anything ambiguous must read as off.
	 */
	#[DataProvider( 'checkbox_field_provider' )]
	public function test_junk_values_do_not_tick_the_box( string $field ): void {
		foreach ( array( '', '0', 'false', 'yes', 'true', array( 'x' ) ) as $value ) {
			self::assertSame(
				'',
				$this->form_values( array( $field => $value ) )[ $field ],
				sprintf( 'A %s value must not tick %s.', get_debug_type( $value ), $field )
			);
		}
	}

	/**
	 * What the form redisplays must agree with what the plugin was actually built from.
	 *
	 * These were two independent parsers of the same request: `PluginIdentity::from_request()` requires
	 * the literal `'1'`, while this page used a bare `! empty()`. So a value of `yes` redisplayed as
	 * ticked while the generated plugin had the option *off* — for `include_studio` that is a security
	 * relevant opt-in reporting the opposite of what shipped.
	 */
	#[DataProvider( 'checkbox_field_provider' )]
	public function test_the_form_and_the_identity_agree_on_every_input( string $field ): void {
		foreach ( array( '1', '', '0', 'yes', 'true', 'false', array( 'x' ) ) as $value ) {
			$request = array_merge( PluginIdentity::defaults(), array( $field => $value ) );

			$form     = $this->form_values( $request )[ $field ] === '1';
			$identity = PluginIdentity::from_request( $request )->{$field};

			self::assertSame(
				$identity,
				$form,
				sprintf( 'The form and the identity disagree about %s for %s.', $field, var_export( $value, true ) )
			);
		}
	}

	/**
	 * Text fields keep working alongside the checkbox handling.
	 */
	public function test_text_fields_are_sanitized_and_preserved(): void {
		$values = $this->form_values(
			array(
				'plugin_name' => "  Acme <b>Library</b>\n",
				'prefix'      => 'acme_library',
			)
		);

		self::assertSame( 'Acme Library', $values['plugin_name'] );
		self::assertSame( 'acme_library', $values['prefix'] );

		// An array where a string is expected must not leak through.
		self::assertSame( '', $this->form_values( array( 'plugin_name' => array( 'x' ) ) )['plugin_name'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function checkbox_field_provider(): array {
		return array(
			'saltus_contributor' => array( 'saltus_contributor' ),
			'include_studio'     => array( 'include_studio' ),
		);
	}
}
