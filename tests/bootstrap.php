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

/**
 * Filter stub returning the unfiltered value, unless a test overrides one.
 *
 * $GLOBALS['test_filter_returns'] maps a hook name to the value `apply_filters()` should return,
 * which is how the Studio on/off switch is exercised without a real hook system.
 */
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( isset( $GLOBALS['test_filter_returns'] ) && array_key_exists( $hook, $GLOBALS['test_filter_returns'] ) ) {
			return $GLOBALS['test_filter_returns'][ $hook ];
		}

		return $value;
	}
}

/**
 * Action stub that *records* callbacks, so a test can fire a hook.
 *
 * Recording rather than discarding matters for anything registered indirectly: `RestController`
 * hooks `rest_api_init` and only registers its routes when that fires, so a stub that dropped the
 * callback made the routes untestable — and made a passing test mean nothing.
 *
 * $GLOBALS['test_actions'] maps a hook name to the callbacks added to it.
 * Fire one with `saltus_test_do_action( 'rest_api_init' )`.
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['test_actions'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'saltus_test_do_action' ) ) {
	/**
	 * Invoke every callback recorded for a hook.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments passed to each callback.
	 */
	function saltus_test_do_action( string $hook, ...$args ): void {
		foreach ( $GLOBALS['test_actions'][ $hook ] ?? array() as $callback ) {
			if ( is_callable( $callback ) ) {
				$callback( ...$args );
			}
		}
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

		/**
		 * Error payload. Real WP_Error carries one; the Studio REST layer puts the HTTP status,
		 * an actionable hint, and the offending config path in here.
		 *
		 * @var array<string, mixed>
		 */
		public $data = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = is_array( $data ) ? $data : array();
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
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

/**
 * REST stubs, for the Studio authoring routes.
 *
 * Driven by globals so a test can shape the caller:
 *
 * $GLOBALS['test_capabilities'] maps a capability to true.
 * $GLOBALS['test_valid_nonces'] maps a nonce action to the single token that verifies against it.
 *
 * Both default to denying, so a test that forgets to grant a capability fails closed rather than
 * silently passing a permission check it meant to exercise.
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return ! empty( $GLOBALS['test_capabilities'][ $capability ] );
	}
}

/**
 * Verify a nonce, accepting either an explicitly registered token or one this bootstrap issued.
 *
 * These two mocks used to *not* be inverses: `wp_create_nonce()` returned `'test-nonce-' . $action`
 * while this function compared only against `$GLOBALS['test_valid_nonces']`, so
 * `wp_verify_nonce( wp_create_nonce( 'wp_rest' ), 'wp_rest' )` returned false where real WordPress
 * returns 1. `StudioPage::boot_data()` issues exactly that token and
 * `RestController::check_permission()` verifies it, so the one integration that matters — the token
 * Studio hands to its JavaScript is accepted by the route that JavaScript calls — had no coverage at
 * all; `RestControllerTest` passed by hardcoding `'good-nonce'` on both sides.
 *
 * Still fail-closed: an unregistered token that this bootstrap did not issue verifies as false.
 */
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		$nonce = (string) $nonce;

		$expected = $GLOBALS['test_valid_nonces'][ $action ] ?? null;

		if ( is_string( $expected ) && hash_equals( $expected, $nonce ) ) {
			return 1;
		}

		return hash_equals( wp_create_nonce( $action ), $nonce ) ? 1 : false;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code() {
		return 401;
	}
}

/*
 * Plugin path and admin-menu stubs, so `Core::init()` can be booted in a test.
 *
 * Only what `init()` actually touches: locale loading, asset registration, the renamer's admin page,
 * and the Studio route registration.
 */
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( (string) $file ), '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.invalid/wp-content/plugins/' . basename( dirname( (string) $file ) ) . '/';
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = '' ) {
		return true;
	}
}

/**
 * Record submenu registrations so a test can assert what parent a page attached to.
 *
 * $GLOBALS['test_submenu_pages'] is a list of the argument arrays, in registration order.
 */
if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ) {
		$GLOBALS['test_submenu_pages'][] = $args;

		// Real WordPress returns the resulting hook suffix, which is what an `admin_enqueue_scripts`
		// callback is handed. Mirrored so a test can drive `enqueue()` the way WordPress would.
		$parent = isset( $args[0] ) ? (string) $args[0] : '';
		$slug   = isset( $args[4] ) ? (string) $args[4] : '';

		return ( $parent === '' ? 'toplevel' : 'saltus' ) . '_page_' . $slug;
	}
}

if ( ! function_exists( 'add_management_page' ) ) {
	function add_management_page( ...$args ) {
		return '';
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.invalid/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		// Deterministic so a test can assert against it; real nonces are time-based.
		return 'test-nonce-' . (string) $action;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		$GLOBALS['test_localized'][ $handle ][ $object_name ] = $l10n;

		return true;
	}
}

/**
 * Record enqueued scripts, so `StudioPage::enqueue()` can be tested through its real path.
 *
 * $GLOBALS['test_enqueued_scripts'] maps a handle to its src, deps and version.
 */
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {
		$handle = isset( $args[0] ) ? (string) $args[0] : '';

		$GLOBALS['test_enqueued_scripts'][ $handle ] = array(
			'src'     => $args[1] ?? '',
			'deps'    => $args[2] ?? array(),
			'version' => $args[3] ?? null,
		);

		return true;
	}
}

/*
 * `plugins_url()` and `wp_set_script_translations()` were both unstubbed, so `StudioPage::enqueue()`
 * fataled the moment a test called it — meaning it had zero coverage and `$GLOBALS['test_localized']`,
 * the entire reason `wp_localize_script` was mocked, was never read by anything. The boot-data contract
 * was checked only by reflection on the private `boot_data()`, bypassing the path that actually ships
 * the payload to the browser.
 */
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'https://example.invalid/wp-content/plugins/'
			. basename( dirname( (string) $plugin ) ) . '/'
			. ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
	function wp_set_script_translations( $handle, $domain = 'default', $path = null ) {
		$GLOBALS['test_script_translations'][ (string) $handle ] = $domain;

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed literal.
		}

		return $result;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $route_namespace, $route, $args = array(), $override = false ) {
		$GLOBALS['test_rest_routes'][ $route_namespace . $route ] = $args;

		return true;
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private $params = array();

		/** @var array<string, string> */
		private $headers = array();

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params() {
			return $this->params;
		}

		public function set_header( $key, $value ) {
			// Real WP_REST_Request normalises header names; match that so callers can use either form.
			$this->headers[ strtolower( str_replace( '_', '-', $key ) ) ] = $value;
		}

		public function get_header( $key ) {
			return $this->headers[ strtolower( str_replace( '_', '-', $key ) ) ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;

		/** @var int */
		private $status = 200;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function set_status( $status ) {
			$this->status = (int) $status;
		}

		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( $response instanceof \WP_Error || $response instanceof \WP_REST_Response ) {
			return $response;
		}

		return new \WP_REST_Response( $response );
	}
}

