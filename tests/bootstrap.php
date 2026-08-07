<?php
require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[\x00-\x20<>"\']/', '', $value );
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( $filename = '' ) {
		return tempnam( sys_get_temp_dir(), sanitize_file_name( (string) $filename ) );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9_.-]/', '-', (string) $filename );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		$options |= JSON_UNESCAPED_UNICODE;

		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( (string) $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( $content ) {
		return preg_replace( '/\[\/?[a-zA-Z0-9_\-]+[^\]]*\]/', '', (string) $content );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

/**
 * Minimal stand-ins for the WordPress classes and taxonomy helpers the AI assistant
 * provider touches. Only the properties the provider reads are modelled.
 */
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID           = 0;
		public $post_title   = '';
		public $post_content = '';
		public $post_excerpt = '';
		public $post_type    = 'post';

		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public $term_id  = 0;
		public $name     = '';
		public $slug     = '';
		public $taxonomy = '';

		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code    = '';
		public $message = '';

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
	}
}

/**
 * Taxonomy stubs driven by globals so individual tests can shape the fixture.
 *
 * $GLOBALS['test_taxonomies'] maps a post type to its taxonomy slugs.
 * $GLOBALS['test_terms']      maps a taxonomy slug to a list of WP_Term objects.
 */
if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $object_type, $output = 'names' ) {
		return $GLOBALS['test_taxonomies'][ $object_type ] ?? array();
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return $GLOBALS['test_terms'][ $args['taxonomy'] ?? '' ] ?? array();
	}
}

