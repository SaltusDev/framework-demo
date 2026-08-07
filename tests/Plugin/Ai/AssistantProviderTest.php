<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Ai;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Ai\AssistantProvider;

/**
 * The framework clamps the returned `target` to post_title, post_excerpt, post_content or
 * terms, and only reads `value`, `suggestions` and `violations` off the result. These tests
 * pin that contract so a change here cannot silently produce a result the framework drops.
 */
class AssistantProviderTest extends TestCase {

	private AssistantProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->provider                = new AssistantProvider();
		$GLOBALS['test_taxonomies']    = array();
		$GLOBALS['test_terms']         = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['test_taxonomies'], $GLOBALS['test_terms'] );

		parent::tearDown();
	}

	private function post( array $data = array() ): \WP_Post {
		return new \WP_Post(
			array_merge(
				array(
					'ID'           => 7,
					'post_title'   => 'The Long Way Home',
					'post_content' => 'A quiet novel about return. It follows one winter in a coastal town. The prose stays plain throughout.',
					'post_excerpt' => '',
					'post_type'    => 'book',
				),
				$data
			)
		);
	}

	/** @return mixed */
	private function handle( string $action, ?\WP_Post $post, array $context = array() ) {
		return $this->provider->handle( null, $action, array( 'post_id' => 7 ), $context, 'book', $post );
	}

	public function test_unknown_action_is_left_unhandled(): void {
		self::assertNull( $this->handle( 'translate_everything', $this->post() ) );
	}

	public function test_missing_post_is_left_unhandled(): void {
		self::assertNull( $this->handle( 'summarize', null ) );
	}

	public function test_earlier_filter_result_is_not_overridden(): void {
		$existing = array( 'target' => 'post_title', 'value' => 'Set by another plugin' );

		$result = $this->provider->handle( $existing, 'improve_title', array(), array(), 'book', $this->post() );

		self::assertSame( $existing, $result );
	}

	public function test_improve_title_collapses_whitespace_and_trailing_punctuation(): void {
		$result = $this->handle( 'improve_title', $this->post( array( 'post_title' => "The   Long  Way\nHome." ) ) );

		self::assertSame( 'post_title', $result['target'] );
		self::assertSame( 'The Long Way Home', $result['value'] );
		self::assertContains( 'The Long Way Home', $result['suggestions'] );
	}

	public function test_improve_title_preserves_author_capitalisation(): void {
		$result = $this->handle( 'improve_title', $this->post( array( 'post_title' => 'the long way home' ) ) );

		self::assertSame( 'the long way home', $result['value'] );
	}

	public function test_improve_title_on_clean_title_suggests_nothing(): void {
		$result = $this->handle( 'improve_title', $this->post( array( 'post_title' => 'Already Tidy' ) ) );

		self::assertSame( 'Already Tidy', $result['value'] );
		self::assertSame( array(), $result['suggestions'] );
	}

	public function test_summarize_targets_the_excerpt_and_ends_on_a_sentence(): void {
		$result = $this->handle( 'summarize', $this->post() );

		self::assertSame( 'post_excerpt', $result['target'] );
		self::assertStringEndsWith( '.', $result['value'] );
		self::assertStringStartsWith( 'A quiet novel about return.', $result['value'] );
	}

	public function test_generate_excerpt_strips_markup_and_shortcodes(): void {
		$post = $this->post(
			array(
				'post_content' => '<p>Plain <strong>enough</strong>.</p>[gallery ids="1,2"] Second sentence.',
			)
		);

		$result = $this->handle( 'generate_excerpt', $post );

		self::assertStringNotContainsString( '<', $result['value'] );
		self::assertStringNotContainsString( '[gallery', $result['value'] );
	}

	public function test_summarize_shorter_than_budget_is_returned_whole(): void {
		$result = $this->handle( 'summarize', $this->post( array( 'post_content' => 'One short line.' ) ) );

		self::assertSame( 'One short line.', $result['value'] );
	}

	public function test_summarize_without_sentence_breaks_trims_on_a_word_boundary(): void {
		$post = $this->post( array( 'post_content' => str_repeat( 'alpha beta ', 40 ) ) );

		$result = $this->handle( 'summarize', $post );

		self::assertStringEndsWith( '…', $result['value'] );
		// A word-boundary trim must not leave a partial word before the ellipsis.
		self::assertMatchesRegularExpression( '/(alpha|beta)…$/', $result['value'] );
	}

	public function test_suggest_terms_only_proposes_existing_terms(): void {
		$GLOBALS['test_taxonomies']['book'] = array( 'genre' );
		$GLOBALS['test_terms']['genre']     = array(
			new \WP_Term( array( 'name' => 'Winter', 'slug' => 'winter', 'taxonomy' => 'genre' ) ),
			new \WP_Term( array( 'name' => 'Spacefaring', 'slug' => 'spacefaring', 'taxonomy' => 'genre' ) ),
		);

		$result = $this->handle( 'suggest_terms', $this->post() );

		self::assertSame( 'terms', $result['target'] );
		self::assertSame( array( 'genre:winter' ), $result['suggestions'] );
	}

	public function test_suggest_terms_matches_case_insensitively(): void {
		$GLOBALS['test_taxonomies']['book'] = array( 'writer' );
		$GLOBALS['test_terms']['writer']    = array(
			new \WP_Term( array( 'name' => 'COASTAL', 'slug' => 'coastal', 'taxonomy' => 'writer' ) ),
		);

		$result = $this->handle( 'suggest_terms', $this->post() );

		self::assertSame( array( 'writer:coastal' ), $result['suggestions'] );
	}

	public function test_suggest_terms_with_no_taxonomies_returns_empty_suggestions(): void {
		$result = $this->handle( 'suggest_terms', $this->post() );

		self::assertSame( 'terms', $result['target'] );
		self::assertSame( array(), $result['suggestions'] );
	}

	public function test_suggest_terms_survives_a_wp_error_from_get_terms(): void {
		$GLOBALS['test_taxonomies']['book'] = array( 'genre' );
		$GLOBALS['test_terms']['genre']     = new \WP_Error( 'invalid_taxonomy' );

		$result = $this->handle( 'suggest_terms', $this->post() );

		self::assertSame( array(), $result['suggestions'] );
	}

	public function test_validate_content_flags_empty_fields_that_carry_rules(): void {
		$context = array(
			'field_rules' => array(
				'post_excerpt' => array( 'Use a spoiler-free summary.' ),
				'post_content' => array( 'Preserve quotations.' ),
			),
		);

		$result = $this->handle( 'validate_content', $this->post( array( 'post_excerpt' => '' ) ), $context );

		self::assertSame( 'post_content', $result['target'] );
		self::assertCount( 1, $result['violations'] );
		self::assertStringContainsString( 'post_excerpt', $result['violations'][0] );
	}

	public function test_validate_content_with_no_rules_reports_nothing(): void {
		$result = $this->handle( 'validate_content', $this->post( array( 'post_excerpt' => 'Fine.' ) ) );

		self::assertSame( array(), $result['violations'] );
	}

	public function test_validate_content_flags_an_overlong_excerpt(): void {
		$result = $this->handle( 'validate_content', $this->post( array( 'post_excerpt' => str_repeat( 'long ', 100 ) ) ) );

		self::assertNotSame( array(), $result['violations'] );
	}

	public function test_every_result_uses_a_target_the_framework_accepts(): void {
		$GLOBALS['test_taxonomies']['book'] = array( 'genre' );
		$GLOBALS['test_terms']['genre']     = array();

		$allowed = array( 'post_title', 'post_excerpt', 'post_content', 'terms' );

		foreach ( array( 'improve_title', 'summarize', 'generate_excerpt', 'suggest_terms', 'validate_content' ) as $action ) {
			$result = $this->handle( $action, $this->post(), array( 'field_rules' => array() ) );

			self::assertIsArray( $result, "{$action} must return an array" );
			self::assertContains( $result['target'], $allowed, "{$action} returned an unsupported target" );
		}
	}

	public function test_result_values_are_the_scalar_types_the_framework_expects(): void {
		$result = $this->handle( 'summarize', $this->post() );

		// normalize_result() only copies `value` when it is a string, and filters
		// suggestions/violations to strings, so anything else is silently dropped.
		self::assertIsString( $result['value'] );
	}
}
