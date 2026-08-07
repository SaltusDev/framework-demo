<?php
/**
 * Demo handler for the framework's in-editor AI assistant.
 *
 * The Saltus Framework registers the assistant UI and defines the action contract, but
 * deliberately leaves the actual work to the consuming plugin: brand voice, content rules and
 * the choice of whether to call a language model at all are product decisions, not framework
 * ones. When no plugin answers `saltus/framework/ai/assistant_actions`, every assistant action
 * returns `ai_assistant_no_provider` (HTTP 501).
 *
 * This implementation is an offline stub. It performs no network calls and uses no language
 * model — it derives its suggestions from the post content and the model's own `ai_context`
 * config so the demo works on an air-gapped install. Replace it in a production plugin.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Ai;

/**
 * Answers the framework's assistant action filter with deterministic, offline suggestions.
 */
class AssistantProvider {

	/**
	 * Maximum number of term suggestions returned by `suggest_terms`.
	 *
	 * @var int
	 */
	private const MAX_TERM_SUGGESTIONS = 5;

	/**
	 * Approximate character budget for a generated excerpt.
	 *
	 * @var int
	 */
	private const EXCERPT_TARGET_LENGTH = 160;

	/**
	 * Register the filter callback.
	 */
	public function register(): void {
		add_filter( 'saltus/framework/ai/assistant_actions', array( $this, 'handle' ), 10, 6 );
	}

	/**
	 * Handle one assistant action.
	 *
	 * Returning a non-array value leaves the action unhandled, which the framework reports as
	 * `ai_assistant_no_provider`. The framework clamps the returned `target` to one of
	 * post_title, post_excerpt, post_content or terms.
	 *
	 * @param mixed                $value     Result from an earlier filter callback, if any.
	 * @param string               $action    Action name requested by the editor.
	 * @param array<string, mixed> $payload   Request payload, including `post_id`.
	 * @param array<string, mixed> $context   Normalized AI governance context for the model.
	 * @param string               $post_type Post type slug.
	 * @param \WP_Post|null        $post      The post being edited, when it exists.
	 * @return mixed Action result array, or the untouched `$value` when unhandled.
	 */
	public function handle( $value, string $action, array $payload, array $context, string $post_type, ?\WP_Post $post ) {
		// Let an earlier callback win, and never invent content without a post to work from.
		if ( is_array( $value ) || ! $post instanceof \WP_Post ) {
			return $value;
		}

		switch ( $action ) {
			case 'improve_title':
				return $this->improve_title( $post );

			case 'summarize':
			case 'generate_excerpt':
				return $this->summarize( $post );

			case 'suggest_terms':
				return $this->suggest_terms( $post, $post_type );

			case 'validate_content':
				return $this->validate_content( $post, $context );
		}

		return $value;
	}

	/**
	 * Propose a tidied version of the post title.
	 *
	 * Collapses whitespace and trims trailing punctuation. Capitalisation is left alone: the
	 * demo's `ai_context` asks to preserve author intent, and re-casing a book title fights that.
	 *
	 * @param \WP_Post $post The post being edited.
	 * @return array<string, mixed>
	 */
	private function improve_title( \WP_Post $post ): array {
		$title = trim( preg_replace( '/\s+/u', ' ', (string) $post->post_title ) ?? '' );
		$title = rtrim( $title, " \t\n\r\0\x0B.,;:-" );

		$suggestions = array();
		if ( $title !== '' && $title !== $post->post_title ) {
			$suggestions[] = $title;
		}

		return array(
			'target'      => 'post_title',
			'value'       => $title !== '' ? $title : (string) $post->post_title,
			'suggestions' => $suggestions,
		);
	}

	/**
	 * Build a spoiler-free summary from the opening of the post content.
	 *
	 * @param \WP_Post $post The post being edited.
	 * @return array<string, mixed>
	 */
	private function summarize( \WP_Post $post ): array {
		$text = $this->plain_text( (string) $post->post_content );

		return array(
			'target' => 'post_excerpt',
			'value'  => $this->first_sentences( $text, self::EXCERPT_TARGET_LENGTH ),
		);
	}

	/**
	 * Suggest existing terms whose names appear in the post title or content.
	 *
	 * Only registered terms are proposed, so an editor can never be nudged toward inventing
	 * taxonomy entries from a machine suggestion.
	 *
	 * @param \WP_Post $post      The post being edited.
	 * @param string   $post_type Post type slug.
	 * @return array<string, mixed>
	 */
	private function suggest_terms( \WP_Post $post, string $post_type ): array {
		$haystack = $this->plain_text( $post->post_title . ' ' . $post->post_content );
		if ( $haystack === '' ) {
			return array(
				'target'      => 'terms',
				'suggestions' => array(),
			);
		}

		$haystack    = $this->casefold( $haystack );
		$suggestions = array();

		foreach ( get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}

				$needle = $this->casefold( $term->name );
				if ( $needle === '' || ! str_contains( $haystack, $needle ) ) {
					continue;
				}

				$suggestions[] = $taxonomy . ':' . $term->slug;

				if ( count( $suggestions ) >= self::MAX_TERM_SUGGESTIONS ) {
					break 2;
				}
			}
		}

		return array(
			'target'      => 'terms',
			'suggestions' => $suggestions,
		);
	}

	/**
	 * Check the post against the model's configured field rules.
	 *
	 * Reports structural gaps only — an empty field that carries a rule, or an excerpt that
	 * runs long. Judging tone is out of scope for an offline stub.
	 *
	 * @param \WP_Post             $post    The post being edited.
	 * @param array<string, mixed> $context Normalized AI governance context.
	 * @return array<string, mixed>
	 */
	private function validate_content( \WP_Post $post, array $context ): array {
		$field_rules = isset( $context['field_rules'] ) && is_array( $context['field_rules'] )
			? $context['field_rules']
			: array();

		$violations = array();

		foreach ( array_keys( $field_rules ) as $field ) {
			if ( ! is_string( $field ) || ! isset( $post->$field ) ) {
				continue;
			}

			if ( trim( $this->plain_text( (string) $post->$field ) ) === '' ) {
				$violations[] = sprintf(
					/* translators: %s: post field name, such as post_excerpt. */
					__( '%s is empty but has content rules configured for this model.', 'framework-demo' ),
					$field
				);
			}
		}

		$excerpt = $this->plain_text( (string) $post->post_excerpt );
		if ( $excerpt !== '' && mb_strlen( $excerpt ) > self::EXCERPT_TARGET_LENGTH * 2 ) {
			$violations[] = __( 'The excerpt is long for a one- or two-sentence summary.', 'framework-demo' );
		}

		return array(
			'target'     => 'post_content',
			'violations' => $violations,
		);
	}

	/**
	 * Reduce post content to comparable plain text.
	 *
	 * @param string $content Raw post content.
	 * @return string
	 */
	private function plain_text( string $content ): string {
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		return trim( preg_replace( '/\s+/u', ' ', $content ) ?? '' );
	}

	/**
	 * Lowercase a string, preferring the multibyte implementation when available.
	 *
	 * @param string $value Value to fold.
	 * @return string
	 */
	private function casefold( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Take whole sentences from the start of a string, up to a character budget.
	 *
	 * Falls back to a word-boundary trim when the text has no sentence punctuation, so the
	 * result never ends mid-word.
	 *
	 * @param string $text  Plain text to shorten.
	 * @param int    $limit Approximate maximum length.
	 * @return string
	 */
	private function first_sentences( string $text, int $limit ): string {
		if ( $text === '' || mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text ) ?: array();
		$summary   = '';

		foreach ( $sentences as $sentence ) {
			$candidate = $summary === '' ? $sentence : $summary . ' ' . $sentence;
			if ( $summary !== '' && mb_strlen( $candidate ) > $limit ) {
				break;
			}
			$summary = $candidate;
		}

		if ( $summary !== '' && mb_strlen( $summary ) <= $limit ) {
			return $summary;
		}

		// No usable sentence break — trim on a word boundary instead.
		$truncated = mb_substr( $text, 0, $limit );
		$last      = mb_strrpos( $truncated, ' ' );

		if ( $last !== false && $last > 0 ) {
			$truncated = mb_substr( $truncated, 0, $last );
		}

		return rtrim( $truncated, " \t\n\r\0\x0B,;:" ) . '…';
	}
}
