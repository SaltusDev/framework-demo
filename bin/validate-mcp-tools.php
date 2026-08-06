<?php
/**
 * Validate all MCP tools against a live WordPress site's REST API.
 *
 * Usage:
 *   php bin/validate-mcp-tools.php <site-url> [--user=<username>] [--app-pass=<password>] [--readonly] [--verbose]
 *
 * Examples:
 *   php bin/validate-mcp-tools.php https://example.com
 *   php bin/validate-mcp-tools.php https://example.com --user=admin --app-pass='xxxx xxxx xxxx xxxx'
 *   php bin/validate-mcp-tools.php https://example.com --readonly
 *   php bin/validate-mcp-tools.php https://example.com --verbose
 */

$args   = $argv;
$script = array_shift( $args );
$site_url = null;
$username = null;
$password = null;
$readonly = false;
$verbose  = false;

foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, '--user=' ) ) {
		$username = substr( $arg, 7 );
	} elseif ( str_starts_with( $arg, '--app-pass=' ) ) {
		$password = substr( $arg, 11 );
	} elseif ( $arg === '--readonly' ) {
		$readonly = true;
	} elseif ( $arg === '--verbose' ) {
		$verbose = true;
	} elseif ( $site_url === null && ! str_starts_with( $arg, '--' ) ) {
		$site_url = rtrim( $arg, '/' );
	}
}

if ( $site_url === null ) {
	fwrite( STDERR, "Usage: php bin/validate-mcp-tools.php <site-url> [--user=<username>] [--app-pass=<password>] [--readonly] [--verbose]\n" );
	exit( 1 );
}

$has_auth = $username !== null && $password !== null;

/**
 * HTTP helper using cURL.
 */
function http_request( string $method, string $url, ?string $body = null, ?string $auth = null ): array {
	$ch = curl_init();
	curl_setopt_array( $ch, [
		CURLOPT_URL            => $url,
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_SSL_VERIFYPEER => true,
	] );

	$headers = [ 'Accept: application/json' ];

	if ( $body !== null ) {
		$headers[] = 'Content-Type: application/json';
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}

	if ( $auth !== null ) {
		$headers[] = "Authorization: Basic {$auth}";
	}

	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

	$response  = curl_exec( $ch );
	$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	$error     = curl_error( $ch );
	curl_close( $ch );

	if ( $error !== '' ) {
		return [ 'code' => 0, 'body' => null, 'error' => $error ];
	}

	$headers_raw = substr( $response, 0, $header_size );
	$body_raw    = substr( $response, $header_size );

	return [ 'code' => $http_code, 'body' => json_decode( $body_raw, true ), 'error' => null, 'headers' => $headers_raw ];
}

/**
 * Discover available REST routes from the site.
 */
function discover_routes( string $site_url, ?string $auth ): array {
	$result = http_request( 'GET', "{$site_url}/wp-json/", null, $auth );

	if ( $result['error'] !== null ) {
		fwrite( STDERR, "  ERROR: Could not reach site: {$result['error']}\n" );
		return [ 'error' => $result['error'] ];
	}

	if ( $result['code'] !== 200 ) {
		fwrite( STDERR, "  ERROR: Site returned HTTP {$result['code']} (expected 200)\n" );
		return [ 'error' => "HTTP {$result['code']}" ];
	}

	$body = $result['body'];

	if ( ! is_array( $body ) || ! isset( $body['namespaces'] ) ) {
		fwrite( STDERR, "  ERROR: Response is not a valid WP REST API root\n" );
		return [ 'error' => 'Invalid WP REST API root' ];
	}

	$namespaces = $body['namespaces'] ?? [];

	echo "  REST API: {$site_url}/wp-json/";
	echo "  (" . implode( ', ', $namespaces ) . ")\n";

	$routes = [];

	foreach ( $namespaces as $ns ) {
		$ns_result = http_request( 'GET', "{$site_url}/wp-json/{$ns}", null, $auth );

		if ( $ns_result['code'] === 200 && is_array( $ns_result['body'] ) ) {
			$routes[ $ns ] = $ns_result['body'];
		}
	}

	return [ 'routes' => $routes, 'namespaces' => $namespaces ];
}

/**
 * Build the Basic auth header value.
 */
function build_auth( ?string $user, ?string $pass ): ?string {
	if ( $user === null || $pass === null ) {
		return null;
	}

	return base64_encode( "{$user}:{$pass}" );
}

/**
 * Find the first route key matching a prefix in the discovered namespace routes.
 */
function find_route_key( array $ns_routes, string $prefix ): ?string {
	if ( $ns_routes === null ) {
		return null;
	}
	foreach ( array_keys( $ns_routes ) as $key ) {
		if ( str_starts_with( $key, $prefix ) ) {
			return $key;
		}
	}
	return null;
}

/**
 * Fetch registered post types from wp/v2/types, excluding native WordPress types.
 */
function get_custom_post_types( string $site_url, ?string $auth ): array {
	$result = http_request( 'GET', "{$site_url}/wp-json/wp/v2/types?per_page=100", null, $auth );

	if ( $result['code'] !== 200 || ! is_array( $result['body'] ) ) {
		return [];
	}

	$native = [
		'post', 'page', 'attachment', 'revision', 'nav_menu_item',
		'custom_css', 'customize_changeset', 'oembed_response',
		'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
		'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_pattern_category',
	];

	$types = [];
	foreach ( $result['body'] as $slug => $type ) {
		if ( ! in_array( $slug, $native, true ) ) {
			$types[ $slug ] = $type['name'] ?? $slug;
		}
	}

	ksort( $types );
	return $types;
}

/**
 * Fetch the first post ID for a given post type, or null if none exist.
 */
function get_first_post_id( string $site_url, string $post_type, ?string $auth ): ?int {
	$result = http_request( 'GET', "{$site_url}/wp-json/wp/v2/{$post_type}?per_page=1&orderby=date&order=desc", null, $auth );

	if ( $result['code'] !== 200 || ! is_array( $result['body'] ) ) {
		return null;
	}

	$body = $result['body'];
	if ( ! empty( $body ) && isset( $body[0]['id'] ) ) {
		return (int) $body[0]['id'];
	}

	return null;
}

// ---------------------------------------------------------------------------
// Tool definitions (mirrors src/MCP/Tools/ metadata)
// ---------------------------------------------------------------------------

interface ToolValidator {
	public function get_name(): string;
	public function get_description(): string;
	public function get_method(): string;
	public function get_route_pattern(): string;
	public function get_parameters(): array;
	public function get_readonly(): bool;
	public function build_test_url( string $site_url, array $discovered_routes ): ?string;
	public function validate_response( array $response, array $routes, string $resolved_url ): array;
	public function get_validate_method(): string;
}

abstract class BaseToolValidator implements ToolValidator {
	protected string $method;
	protected string $route_pattern;
	protected ?string $post_type = null;

	public function __construct( string $method, string $route_pattern ) {
		$this->method        = $method;
		$this->route_pattern = $route_pattern;
	}

	public function get_method(): string {
		return $this->method;
	}

	public function get_route_pattern(): string {
		return $this->route_pattern;
	}

	public function get_validate_method(): string {
		return $this->get_readonly() ? 'GET' : 'OPTIONS';
	}

	public function set_post_type( string $slug ): void {
		$this->post_type = $slug;
	}

	public function is_cpt_scoped(): bool {
		return false;
	}
}

class GetHealthValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/health' ); }
	public function get_name(): string { return 'get_health'; }
	public function get_description(): string { return 'Get Saltus Framework health, version, audit error rate, latency, cache, and rate limit status'; }
	public function get_parameters(): array { return []; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		return "{$site_url}/wp-json/saltus-framework/v1/health";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has version', 'pass' => isset( $body['version'] ), 'actual' => isset( $body['version'] ) ? gettype( $body['version'] ) : 'missing' ];
			$checks[] = [ 'name' => 'Has status', 'pass' => isset( $body['status'] ), 'actual' => isset( $body['status'] ) ? gettype( $body['status'] ) : 'missing' ];
		} else {
			$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class ListModelsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/models' ); }
	public function get_name(): string { return 'list_models'; }
	public function get_description(): string { return 'List all registered Custom Post Types and Taxonomies on the WordPress site'; }
	public function get_parameters(): array { return [ 'type' => [ 'type' => 'string', 'enum' => [ 'post_types', 'taxonomies', 'all' ], 'default' => 'all' ] ]; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		return "{$site_url}/wp-json/saltus-framework/v1/models";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Returns array', 'pass' => array_is_list( $body ), 'actual' => gettype( $body ) ];
			if ( ! empty( $body ) && isset( $body[0] ) ) {
				$checks[] = [ 'name' => 'Model has name', 'pass' => isset( $body[0]['name'] ), 'actual' => isset( $body[0]['name'] ) ? 'string' : 'missing' ];
				$checks[] = [ 'name' => 'Model has type', 'pass' => isset( $body[0]['type'] ), 'actual' => isset( $body[0]['type'] ) ? 'string' : 'missing' ];
			}
		} else {
			$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class GetModelValidator extends BaseToolValidator {
	private ?array $model_names = null;

	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/models/{slug}' ); }
	public function get_name(): string { return 'get_model'; }
	public function get_description(): string { return 'Get details of a specific Custom Post Type or Taxonomy by slug'; }
	public function get_parameters(): array { return [ 'slug' => [ 'type' => 'string', 'required' => true, 'description' => 'The slug of the post type or taxonomy' ] ]; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/models' ) ) {
			return null;
		}
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$result = http_request( 'GET', "{$site_url}/wp-json/saltus-framework/v1/models?per_page=100", null, $auth );
		$body = $result['body'] ?? [];
		if ( is_array( $body ) ) {
			$this->model_names = [];
			foreach ( $body as $model ) {
				if ( isset( $model['name'] ) ) {
					$this->model_names[] = $model['name'];
				}
			}
		}
		return "{$site_url}/wp-json/saltus-framework/v1/models";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks  = [];
		$errors  = [];
		$auth    = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$site    = $GLOBALS['_site_url'] ?? '';

		if ( empty( $this->model_names ) ) {
			return [ 'pass' => false, 'errors' => [ 'No models returned by list_models' ], 'checks' => [] ];
		}

		$pass_count = 0;
		$fail_count = 0;

		foreach ( $this->model_names as $slug ) {
			$url      = "{$site}/wp-json/saltus-framework/v1/models/{$slug}";
			$sub_resp = http_request( 'GET', $url, null, $auth );

			if ( $sub_resp['error'] !== null ) {
				$errors[] = "{$slug}: cURL error - {$sub_resp['error']}";
				++$fail_count;
				continue;
			}

			if ( $sub_resp['code'] !== 200 ) {
				$msg = $sub_resp['body']['message'] ?? "HTTP {$sub_resp['code']}";
				$errors[] = "{$slug}: {$msg}";
				++$fail_count;
				continue;
			}

			$model = $sub_resp['body'];
			if ( ! is_array( $model ) ) {
				$errors[] = "{$slug}: response is not valid JSON";
				++$fail_count;
				continue;
			}

			$model_ok = true;
			foreach ( [ 'name', 'type', 'label_singular' ] as $key ) {
				if ( ! isset( $model[ $key ] ) ) {
					$errors[] = "{$slug}: missing '{$key}' field";
					$model_ok = false;
				}
			}

			if ( $model_ok ) {
				++$pass_count;
			} else {
				++$fail_count;
			}
		}

		$checks[] = [ 'name' => 'Models found', 'pass' => true, 'actual' => (string) count( $this->model_names ) ];
		$checks[] = [ 'name' => 'Passed', 'pass' => true, 'actual' => (string) $pass_count ];
		$checks[] = [ 'name' => 'Failed', 'pass' => $fail_count === 0, 'actual' => (string) $fail_count ];

		$all_pass = $fail_count === 0;

		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}
}

class ListPostsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/wp/v2/{post_type_rest_base}' ); }
	public function get_name(): string { return 'list_posts'; }
	public function get_description(): string { return 'Query posts from a Custom Post Type with optional filters'; }
	public function get_parameters(): array { return [ 'post_type' => [ 'type' => 'string', 'default' => 'posts' ], 'per_page' => [ 'type' => 'number', 'default' => 20 ] ]; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		return "{$site_url}/wp-json/wp/v2/posts?per_page=1";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Returns array', 'pass' => array_is_list( $body ), 'actual' => is_array( $body ) ? 'array' : gettype( $body ) ];
			if ( ! empty( $body ) && isset( $body[0] ) ) {
				$checks[] = [ 'name' => 'Post has id', 'pass' => isset( $body[0]['id'] ), 'actual' => isset( $body[0]['id'] ) ? 'number' : 'missing' ];
				$checks[] = [ 'name' => 'Post has title', 'pass' => isset( $body[0]['title'] ), 'actual' => isset( $body[0]['title'] ) ? 'object' : 'missing' ];
			}
		} else {
			$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class GetPostValidator extends BaseToolValidator {
	private ?int $found_id = null;

	public function __construct() { parent::__construct( 'GET', '/wp/v2/{post_type_rest_base}/{id}' ); }
	public function get_name(): string { return 'get_post'; }
	public function get_description(): string { return 'Get a single post by ID with all fields and meta data'; }
	public function get_parameters(): array { return [ 'post_id' => [ 'type' => 'number', 'required' => true ], 'post_type' => [ 'type' => 'string', 'default' => 'posts' ] ]; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$auth     = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$list_url = "{$site_url}/wp-json/wp/v2/posts?per_page=1";
		$result   = http_request( 'GET', $list_url, null, $auth );
		$body     = $result['body'] ?? [];

		if ( is_array( $body ) && ! empty( $body ) && isset( $body[0]['id'] ) ) {
			$this->found_id = (int) $body[0]['id'];
			return "{$site_url}/wp-json/wp/v2/posts/{$this->found_id}";
		}

		return "{$site_url}/wp-json/wp/v2/posts?per_page=1";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		if ( $this->found_id !== null ) {
			$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];
			$body = $response['body'];
			if ( is_array( $body ) ) {
				$checks[] = [ 'name' => 'Has id', 'pass' => isset( $body['id'] ), 'actual' => isset( $body['id'] ) ? 'number' : 'missing' ];
				$checks[] = [ 'name' => 'Has title', 'pass' => isset( $body['title']['rendered'] ), 'actual' => isset( $body['title']['rendered'] ) ? 'string' : 'missing' ];
				$checks[] = [ 'name' => 'Has content', 'pass' => isset( $body['content']['rendered'] ), 'actual' => isset( $body['content']['rendered'] ) ? 'string' : 'missing' ];
				$checks[] = [ 'name' => 'Has type', 'pass' => isset( $body['type'] ), 'actual' => isset( $body['type'] ) ? $body['type'] : 'missing' ];
			} else {
				$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
			}
		} else {
			$checks[] = [ 'name' => 'No posts to fetch', 'pass' => true, 'actual' => 'skipped GET, route exists per list_posts' ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class CreatePostValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'POST', '/wp/v2/{post_type_rest_base}' ); }
	public function get_name(): string { return 'create_post'; }
	public function get_description(): string { return 'Create a new post in any registered Custom Post Type'; }
	public function get_parameters(): array { return [ 'title' => [ 'type' => 'string', 'required' => true ], 'post_type' => [ 'type' => 'string', 'default' => 'posts' ] ]; }
	public function get_readonly(): bool { return false; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$result = http_request( 'OPTIONS', "{$site_url}/wp-json/wp/v2/posts", null, $auth );

		if ( $result['code'] === 200 ) {
			return "{$site_url}/wp-json/wp/v2/posts";
		}

		return null;
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'Endpoint exists (OPTIONS)', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has POST endpoint', 'pass' => isset( $body['endpoints'] ) && is_array( $body['endpoints'] ), 'actual' => isset( $body['endpoints'] ) ? 'array' : 'missing' ];

			if ( isset( $body['endpoints'] ) ) {
				$has_post = false;
				foreach ( $body['endpoints'] as $ep ) {
					if ( in_array( 'POST', (array) ( $ep['methods'] ?? [] ), true ) ) {
						$has_post = true;
						break;
					}
				}
				$checks[] = [ 'name' => 'POST method allowed', 'pass' => $has_post, 'actual' => $has_post ? 'yes' : 'no' ];
			}
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class UpdatePostValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'PUT', '/wp/v2/{post_type_rest_base}/{id}' ); }
	public function get_name(): string { return 'update_post'; }
	public function get_description(): string { return 'Update an existing post'; }
	public function get_parameters(): array { return [ 'post_id' => [ 'type' => 'number', 'required' => true ], 'title' => [ 'type' => 'string' ] ]; }
	public function get_readonly(): bool { return false; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$list_url = "{$site_url}/wp-json/wp/v2/posts?per_page=1";
		$result   = http_request( 'GET', $list_url, null, $auth );
		$body     = $result['body'] ?? [];

		$post_id = ( is_array( $body ) && ! empty( $body ) && isset( $body[0]['id'] ) ) ? $body[0]['id'] : 1;

		return "{$site_url}/wp-json/wp/v2/posts/{$post_id}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'Endpoint exists (OPTIONS)', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has endpoints', 'pass' => isset( $body['endpoints'] ) && is_array( $body['endpoints'] ), 'actual' => isset( $body['endpoints'] ) ? 'array' : 'missing' ];

			if ( isset( $body['endpoints'] ) ) {
				$has_post = false;
				foreach ( $body['endpoints'] as $ep ) {
					$methods = (array) ( $ep['methods'] ?? [] );
					if ( in_array( 'POST', $methods, true ) || in_array( 'PUT', $methods, true ) || in_array( 'PATCH', $methods, true ) ) {
						$has_post = true;
						break;
					}
				}
				$checks[] = [ 'name' => 'Write method allowed', 'pass' => $has_post, 'actual' => $has_post ? 'yes' : 'no' ];
			}
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class DeletePostValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'DELETE', '/wp/v2/{post_type_rest_base}/{id}' ); }
	public function get_name(): string { return 'delete_post'; }
	public function get_description(): string { return 'Delete (trash or force delete) a post by ID'; }
	public function get_parameters(): array { return [ 'post_id' => [ 'type' => 'number', 'required' => true ], 'force' => [ 'type' => 'boolean', 'default' => false ] ]; }
	public function get_readonly(): bool { return false; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$list_url = "{$site_url}/wp-json/wp/v2/posts?per_page=1";
		$result   = http_request( 'GET', $list_url, null, $auth );
		$body     = $result['body'] ?? [];

		$post_id = ( is_array( $body ) && ! empty( $body ) && isset( $body[0]['id'] ) ) ? $body[0]['id'] : 1;

		return "{$site_url}/wp-json/wp/v2/posts/{$post_id}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'Endpoint exists (OPTIONS)', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has endpoints', 'pass' => isset( $body['endpoints'] ) && is_array( $body['endpoints'] ), 'actual' => isset( $body['endpoints'] ) ? 'array' : 'missing' ];

			if ( isset( $body['endpoints'] ) ) {
				$has_delete = false;
				foreach ( $body['endpoints'] as $ep ) {
					if ( in_array( 'DELETE', (array) ( $ep['methods'] ?? [] ), true ) ) {
						$has_delete = true;
						break;
					}
				}
				$checks[] = [ 'name' => 'DELETE method allowed', 'pass' => $has_delete, 'actual' => $has_delete ? 'yes' : 'no' ];
			}
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class DuplicatePostValidator extends BaseToolValidator {
	private ?int $created_id = null;

	public function __construct() { parent::__construct( 'POST', '/saltus-framework/v1/duplicate/{id}' ); }
	public function get_name(): string { return 'duplicate_post'; }
	public function get_description(): string { return 'Duplicate a WordPress post, creating a copy with "(Copy)" appended'; }
	public function get_parameters(): array { return [ 'post_id' => [ 'type' => 'number', 'required' => true ] ]; }
	public function get_readonly(): bool { return false; }
	public function is_cpt_scoped(): bool { return true; }

	public function get_validate_method(): string {
		return 'POST';
	}

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/duplicate/' ) ) {
			return null;
		}
		if ( $this->post_type === null ) {
			return null;
		}
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$post_id = get_first_post_id( $site_url, $this->post_type, $auth );
		if ( $post_id === null ) {
			return null;
		}
		return "{$site_url}/wp-json/saltus-framework/v1/duplicate/{$post_id}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		$errors = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		if ( $response['code'] === 200 || $response['code'] === 201 ) {
			$checks[] = [ 'name' => 'Duplicate created', 'pass' => true, 'actual' => "HTTP {$response['code']}" ];
			$body = $response['body'];
			if ( is_array( $body ) && isset( $body['id'] ) ) {
				$this->created_id = (int) $body['id'];
				$checks[] = [ 'name' => 'New post ID', 'pass' => true, 'actual' => (string) $this->created_id ];
			}
		} elseif ( $response['code'] === 403 && isset( $response['body']['code'] ) ) {
			$checks[] = [ 'name' => 'Duplicate rejected', 'pass' => false, 'actual' => $response['body']['code'] . ': ' . ( $response['body']['message'] ?? '' ) ];
			if ( $response['body']['code'] === 'model_rest_capability_disabled' ) {
				return [ 'pass' => false, 'errors' => [], 'checks' => $checks, 'expected' => "Duplicate feature not enabled for post type '{$this->post_type}'" ];
			}
		} else {
			$checks[] = [ 'name' => 'HTTP response', 'pass' => false, 'actual' => (string) $response['code'] ];
		}

		$checks[] = [ 'name' => 'Post type', 'pass' => true, 'actual' => $this->post_type ?? '?' ];
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}

	public function cleanup( string $site_url, ?string $auth ): void {
		if ( $this->created_id === null ) {
			return;
		}
		http_request( 'DELETE', "{$site_url}/wp-json/wp/v2/{$this->post_type}/{$this->created_id}?force=true", null, $auth );
	}
}

class ExportPostValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/export/{id}' ); }
	public function get_name(): string { return 'export_post'; }
	public function get_description(): string { return 'Export a WordPress post as WXR'; }
	public function get_parameters(): array { return [ 'post_id' => [ 'type' => 'number', 'required' => true ] ]; }
	public function get_readonly(): bool { return true; }
	public function is_cpt_scoped(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/export/' ) ) {
			return null;
		}
		if ( $this->post_type === null ) {
			return null;
		}
		$auth = build_auth( $GLOBALS['_username'] ?? null, $GLOBALS['_password'] ?? null );
		$post_id = get_first_post_id( $site_url, $this->post_type, $auth );
		if ( $post_id === null ) {
			return null;
		}
		return "{$site_url}/wp-json/saltus-framework/v1/export/{$post_id}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		$errors = [];
		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}
		if ( $response['code'] === 200 ) {
			$checks[] = [ 'name' => 'Export successful', 'pass' => true, 'actual' => 'HTTP 200' ];
		} elseif ( $response['code'] === 403 && isset( $response['body']['code'] ) ) {
			$checks[] = [ 'name' => 'Export denied', 'pass' => false, 'actual' => $response['body']['code'] ];
			if ( $response['body']['code'] === 'model_rest_capability_disabled' ) {
				return [ 'pass' => false, 'errors' => [], 'checks' => $checks, 'expected' => "Export feature not enabled for post type '{$this->post_type}'" ];
			}
		} else {
			$checks[] = [ 'name' => 'HTTP status', 'pass' => false, 'actual' => (string) $response['code'] ];
		}
		$checks[] = [ 'name' => 'Post type', 'pass' => true, 'actual' => $this->post_type ?? '?' ];
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );
		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}
}

class ReorderPostsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'POST', '/saltus-framework/v1/reorder' ); }
	public function get_name(): string { return 'reorder_posts'; }
	public function get_description(): string { return 'Reorder multiple posts by updating their menu_order values'; }
	public function get_parameters(): array { return [ 'items' => [ 'type' => 'array', 'required' => true ] ]; }
	public function get_readonly(): bool { return false; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( $sf_route_keys && isset( $sf_route_keys['/saltus-framework/v1/reorder'] ) ) {
			return "{$site_url}/wp-json/saltus-framework/v1/reorder";
		}
		return null;
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}
		$checks[] = [ 'name' => 'Endpoint exists (OPTIONS)', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];
		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has POST endpoint', 'pass' => isset( $body['endpoints'] ) && is_array( $body['endpoints'] ), 'actual' => isset( $body['endpoints'] ) ? 'array' : 'missing' ];
		}
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );
		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class ListTermsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/wp/v2/{taxonomy_rest_base}' ); }
	public function get_name(): string { return 'list_terms'; }
	public function get_description(): string { return 'List terms from a taxonomy'; }
	public function get_parameters(): array { return [ 'taxonomy' => [ 'type' => 'string', 'required' => true ], 'per_page' => [ 'type' => 'number', 'default' => 50 ] ]; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		return "{$site_url}/wp-json/wp/v2/categories?per_page=1";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Returns array', 'pass' => array_is_list( $body ), 'actual' => is_array( $body ) ? 'array' : gettype( $body ) ];
			if ( ! empty( $body ) && isset( $body[0] ) ) {
				$checks[] = [ 'name' => 'Term has id', 'pass' => isset( $body[0]['id'] ), 'actual' => isset( $body[0]['id'] ) ? 'number' : 'missing' ];
				$checks[] = [ 'name' => 'Term has name', 'pass' => isset( $body[0]['name'] ), 'actual' => isset( $body[0]['name'] ) ? 'string' : 'missing' ];
			}
		} else {
			$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class CreateTermValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'POST', '/wp/v2/{taxonomy_rest_base}' ); }
	public function get_name(): string { return 'create_term'; }
	public function get_description(): string { return 'Create a new term in a taxonomy'; }
	public function get_parameters(): array { return [ 'taxonomy' => [ 'type' => 'string', 'required' => true ], 'name' => [ 'type' => 'string', 'required' => true ] ]; }
	public function get_readonly(): bool { return false; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		return "{$site_url}/wp-json/wp/v2/categories";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'Endpoint exists (OPTIONS)', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has POST endpoint', 'pass' => isset( $body['endpoints'] ) && is_array( $body['endpoints'] ), 'actual' => isset( $body['endpoints'] ) ? 'array' : 'missing' ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class ListMetaFieldsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/meta' ); }
	public function get_name(): string { return 'list_meta_fields'; }
	public function get_description(): string { return 'List model-defined meta field definitions for all registered Saltus post types'; }
	public function get_parameters(): array { return []; }
	public function get_readonly(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( $sf_route_keys && isset( $sf_route_keys['/saltus-framework/v1/meta'] ) ) {
			return "{$site_url}/wp-json/saltus-framework/v1/meta";
		}
		return null;
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		$checks[] = [ 'name' => 'HTTP 200', 'pass' => $response['code'] === 200, 'actual' => $response['code'] ];

		$body = $response['body'];
		if ( is_array( $body ) ) {
			$checks[] = [ 'name' => 'Has post_types', 'pass' => isset( $body['post_types'] ), 'actual' => isset( $body['post_types'] ) ? 'array' : 'missing' ];
		} else {
			$checks[] = [ 'name' => 'Valid JSON body', 'pass' => false, 'actual' => gettype( $body ) ];
		}

		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => [], 'checks' => $checks ];
	}
}

class GetMetaFieldsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/meta/{post_type}' ); }
	public function get_name(): string { return 'get_meta_fields'; }
	public function get_description(): string { return 'Get meta field definitions for a post type'; }
	public function get_parameters(): array { return [ 'post_type' => [ 'type' => 'string', 'required' => true ] ]; }
	public function get_readonly(): bool { return true; }
	public function is_cpt_scoped(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/meta/' ) ) {
			return null;
		}
		if ( $this->post_type === null ) {
			return null;
		}
		return "{$site_url}/wp-json/saltus-framework/v1/meta/{$this->post_type}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		$errors = [];

		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}

		if ( $response['code'] === 200 ) {
			$checks[] = [ 'name' => 'Meta fields fetched', 'pass' => true, 'actual' => 'HTTP 200' ];
			$body = $response['body'];
			if ( is_array( $body ) ) {
				$checks[] = [ 'name' => 'Has post_type', 'pass' => isset( $body['post_type'] ), 'actual' => isset( $body['post_type'] ) ? (string) $body['post_type'] : 'missing' ];
				$checks[] = [ 'name' => 'Has meta array', 'pass' => isset( $body['meta'] ) && is_array( $body['meta'] ), 'actual' => isset( $body['meta'] ) ? 'array(' . count( $body['meta'] ) . ')' : 'missing' ];
			}
		} elseif ( $response['code'] === 404 && isset( $response['body']['code'] ) ) {
			$checks[] = [ 'name' => 'Model not found', 'pass' => false, 'actual' => $response['body']['code'] ];
			if ( $response['body']['code'] === 'model_not_found' ) {
				return [ 'pass' => false, 'errors' => [], 'checks' => $checks, 'expected' => "Model '{$this->post_type}' not found in Saltus registry — no saltus_rest config" ];
			}
		} else {
			$checks[] = [ 'name' => 'HTTP status', 'pass' => false, 'actual' => (string) $response['code'] ];
		}

		$checks[] = [ 'name' => 'Post type', 'pass' => true, 'actual' => $this->post_type ?? '?' ];
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );

		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}
}

class GetSettingsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'GET', '/saltus-framework/v1/settings/{post_type}' ); }
	public function get_name(): string { return 'get_settings'; }
	public function get_description(): string { return 'Get the Saltus Framework settings for a specific post type'; }
	public function get_parameters(): array { return [ 'post_type' => [ 'type' => 'string', 'required' => true ] ]; }
	public function get_readonly(): bool { return true; }
	public function is_cpt_scoped(): bool { return true; }

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/settings/' ) ) {
			return null;
		}
		if ( $this->post_type === null ) {
			return null;
		}
		return "{$site_url}/wp-json/saltus-framework/v1/settings/{$this->post_type}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		$errors = [];
		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}
		if ( $response['code'] === 200 ) {
			$checks[] = [ 'name' => 'Settings fetched', 'pass' => true, 'actual' => 'HTTP 200' ];
		} elseif ( $response['code'] === 404 && isset( $response['body']['code'] ) ) {
			$checks[] = [ 'name' => 'Model not found', 'pass' => false, 'actual' => $response['body']['code'] ];
			if ( $response['body']['code'] === 'model_not_found' ) {
				return [ 'pass' => false, 'errors' => [], 'checks' => $checks, 'expected' => "Model '{$this->post_type}' not found in Saltus registry — no saltus_rest config" ];
			}
		} else {
			$checks[] = [ 'name' => 'HTTP status', 'pass' => false, 'actual' => (string) $response['code'] ];
		}
		$checks[] = [ 'name' => 'Post type', 'pass' => true, 'actual' => $this->post_type ?? '?' ];
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );
		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}
}

class UpdateSettingsValidator extends BaseToolValidator {
	public function __construct() { parent::__construct( 'PUT', '/saltus-framework/v1/settings/{post_type}' ); }
	public function get_name(): string { return 'update_settings'; }
	public function get_description(): string { return 'Update the Saltus Framework settings for a specific post type'; }
	public function get_parameters(): array { return [ 'post_type' => [ 'type' => 'string', 'required' => true ], 'settings' => [ 'type' => 'object', 'required' => true ] ]; }
	public function get_readonly(): bool { return false; }
	public function is_cpt_scoped(): bool { return true; }

	public function get_validate_method(): string {
		return 'GET';
	}

	public function build_test_url( string $site_url, array $discovered_routes ): ?string {
		$routes_sf     = $discovered_routes['saltus-framework/v1'] ?? null;
		$sf_route_keys = $routes_sf['routes'] ?? null;
		if ( ! $sf_route_keys || ! find_route_key( $sf_route_keys, '/saltus-framework/v1/settings/' ) ) {
			return null;
		}
		if ( $this->post_type === null ) {
			return null;
		}
		return "{$site_url}/wp-json/saltus-framework/v1/settings/{$this->post_type}";
	}

	public function validate_response( array $response, array $routes, string $resolved_url ): array {
		$checks = [];
		$errors = [];
		if ( $response['error'] !== null ) {
			return [ 'pass' => false, 'errors' => [ "cURL error: {$response['error']}" ], 'checks' => [] ];
		}
		if ( $response['code'] === 200 ) {
			$checks[] = [ 'name' => 'Settings fetched', 'pass' => true, 'actual' => 'HTTP 200' ];
		} elseif ( $response['code'] === 404 && isset( $response['body']['code'] ) ) {
			$checks[] = [ 'name' => 'Model not found', 'pass' => false, 'actual' => $response['body']['code'] ];
			if ( $response['body']['code'] === 'model_not_found' ) {
				return [ 'pass' => false, 'errors' => [], 'checks' => $checks, 'expected' => "Model '{$this->post_type}' not found in Saltus registry — no saltus_rest config" ];
			}
		} else {
			$checks[] = [ 'name' => 'HTTP status', 'pass' => false, 'actual' => (string) $response['code'] ];
		}
		$checks[] = [ 'name' => 'Post type', 'pass' => true, 'actual' => $this->post_type ?? '?' ];
		$all_pass = ! in_array( false, array_column( $checks, 'pass' ), true );
		return [ 'pass' => $all_pass, 'errors' => $errors, 'checks' => $checks ];
	}
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$tools = [
	new GetHealthValidator(),
	new ListModelsValidator(),
	new GetModelValidator(),
	new ListPostsValidator(),
	new GetPostValidator(),
	new CreatePostValidator(),
	new UpdatePostValidator(),
	new DeletePostValidator(),
	new DuplicatePostValidator(),
	new ExportPostValidator(),
	new ReorderPostsValidator(),
	new ListTermsValidator(),
	new CreateTermValidator(),
	new ListMetaFieldsValidator(),
	new GetMetaFieldsValidator(),
	new GetSettingsValidator(),
	new UpdateSettingsValidator(),
];

$GLOBALS['_username'] = $username;
$GLOBALS['_password'] = $password;
$GLOBALS['_site_url'] = $site_url;
$auth = build_auth( $username, $password );

echo "═══════════════════════════════════════════════════════════\n";
echo "  SECTION 1: Connection\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  Target: {$site_url}\n";
echo "  Auth:   " . ( $auth !== null ? 'Yes (Basic Auth)' : 'No (public)' ) . "\n";
$mode_parts = [];
$mode_parts[] = $readonly ? 'read-only' : 'full validation';
if ( $verbose ) {
	$mode_parts[] = 'verbose';
}
echo "  Mode:   " . implode( ', ', $mode_parts ) . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$discovery = discover_routes( $site_url, $auth );

if ( isset( $discovery['error'] ) ) {
	echo "\n❌ Site validation failed.\n";
	exit( 1 );
}

$routes    = $discovery['routes'];
$ns        = $discovery['namespaces'];

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  SECTION 2: Post Types\n";
echo "═══════════════════════════════════════════════════════════\n";
$custom_types = get_custom_post_types( $site_url, $auth );
$cpt_slugs = array_keys( $custom_types );
echo "  Custom: " . ( $cpt_slugs !== [] ? implode( ', ', $cpt_slugs ) : '(none)' ) . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "═══════════════════════════════════════════════════════════\n";
echo "  SECTION 3: Tool Validation\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$pass_count         = 0;
$fail_count         = 0;
$expected_fail_count = 0;
$skip_count         = 0;
$results            = [];

$global_tools = array_filter( $tools, fn( $t ) => ! $t->is_cpt_scoped() );
$cpt_tools    = array_filter( $tools, fn( $t ) => $t->is_cpt_scoped() );

// ── Global tools (run once) ──
$max_name_len = 18;

if ( $global_tools !== [] ) {
	echo "  ── Global Tools ──\n\n";

	foreach ( $global_tools as $tool ) {
		$name   = $tool->get_name();
		$method = $tool->get_method();

		$url = $tool->build_test_url( $site_url, $routes );

		if ( $url === null ) {
			echo "  ⚠ " . str_pad( $name, $max_name_len ) . " SKIP   route not found\n";
			++$skip_count;
			$results[] = [ 'name' => $name, 'status' => 'skipped', 'reason' => 'Route not discovered on site' ];
			continue;
		}

		if ( ! $tool->get_readonly() && $readonly ) {
			echo "  ⚠ " . str_pad( $name, $max_name_len ) . " SKIP   readonly mode\n";
			++$skip_count;
			$results[] = [ 'name' => $name, 'status' => 'skipped', 'reason' => 'Write tool skipped in readonly mode' ];
			continue;
		}

		$http_method = $tool->get_validate_method();
		$response    = http_request( $http_method, $url, null, $auth );
		$result      = $tool->validate_response( $response, $routes, $url );

		if ( $result['pass'] ) {
			$pass_checks = count( array_filter( $result['checks'] ?? [], fn( $c ) => $c['pass'] ) );
			$total_checks = count( $result['checks'] ?? [] );
			$detail = $total_checks > 0 ? "{$pass_checks}/{$total_checks} checks passed" : 'ok';
			echo "  ✅ " . str_pad( $name, $max_name_len ) . " PASS   {$detail}\n";
			++$pass_count;
		} elseif ( isset( $result['expected'] ) ) {
			echo "  ⚠ " . str_pad( $name, $max_name_len ) . " DENIED {$result['expected']}\n";
			++$expected_fail_count;
		} else {
			echo "  ❌ " . str_pad( $name, $max_name_len ) . " FAIL\n";
			++$fail_count;
			foreach ( $result['checks'] ?? [] as $check ) {
				if ( ! $check['pass'] ) {
					echo "       └ {$check['name']}: {$check['actual']}\n";
				}
			}
			foreach ( $result['errors'] as $err ) {
				echo "       └ {$err}\n";
			}
		}

		if ( $verbose ) {
			$resp_body = $response['body'] ?? null;
			if ( is_array( $resp_body ) ) {
				echo "    Response ({$http_method}):\n";
				if ( isset( $resp_body['code'] ) ) {
					echo "      error_code:  {$resp_body['code']}\n";
					echo "      message:     " . ( $resp_body['message'] ?? '(none)' ) . "\n";
					if ( isset( $resp_body['data']['status'] ) ) {
						echo "      http_status: {$resp_body['data']['status']}\n";
					}
					if ( isset( $resp_body['data']['hint'] ) ) {
						echo "      hint:        {$resp_body['data']['hint']}\n";
					}
					if ( isset( $resp_body['data']['params'] ) ) {
						$params_str = is_array( $resp_body['data']['params'] )
							? json_encode( $resp_body['data']['params'], JSON_UNESCAPED_SLASHES )
							: (string) $resp_body['data']['params'];
						echo "      params:      {$params_str}\n";
					}
				} else {
					$summary = [];
					foreach ( $resp_body as $k => $v ) {
						$val = is_array( $v ) ? 'array(' . count( $v ) . ')' : ( is_string( $v ) ? '"' . mb_substr( $v, 0, 60 ) . '"' : get_debug_type( $v ) );
						$summary[] = "{$k}: {$val}";
					}
					echo "      " . implode( ', ', $summary ) . "\n";
				}
			}
			echo "    URL: {$url}\n";
		}

		$intent  = "run {$name}";
		$outcome = $result['pass'] ? 'success' : ( isset( $result['expected'] ) ? 'denied (expected)' : 'error' );
		$results[] = [
			'name'    => $name,
			'status'  => $result['pass'] ? 'pass' : ( isset( $result['expected'] ) ? 'expected_fail' : 'fail' ),
			'intent'  => $intent,
			'outcome' => $outcome,
			'checks'  => $result['checks'] ?? [],
			'errors'  => $result['errors'] ?? [],
		];
	}
}

// ── Per-CPT tools ──
if ( $cpt_tools !== [] && $cpt_slugs !== [] ) {
	echo "  ── Per-CPT Tools ──\n\n";

	foreach ( $cpt_slugs as $slug ) {
		echo "  📦 {$slug}\n";

		foreach ( $cpt_tools as $tool ) {
			$name   = $tool->get_name();
			$method = $tool->get_method();

			$tool->set_post_type( $slug );
			$url = $tool->build_test_url( $site_url, $routes );

			if ( $url === null ) {
				echo "  ⚠   " . str_pad( $name, $max_name_len ) . " SKIP   no posts or route unavailable\n";
				++$skip_count;
				$results[] = [ 'name' => "{$name}[{$slug}]", 'status' => 'skipped', 'reason' => 'No posts available or route not found' ];
				continue;
			}

			if ( ! $tool->get_readonly() && $readonly ) {
				echo "  ⚠   " . str_pad( $name, $max_name_len ) . " SKIP   readonly mode\n";
				++$skip_count;
				$results[] = [ 'name' => "{$name}[{$slug}]", 'status' => 'skipped', 'reason' => 'Write tool skipped in readonly mode' ];
				continue;
			}

			$http_method = $tool->get_validate_method();
			$response    = http_request( $http_method, $url, null, $auth );
			$result      = $tool->validate_response( $response, $routes, $url );

			$intent = "run {$name} on {$slug}";

			if ( $result['pass'] ) {
				$pass_checks = count( array_filter( $result['checks'] ?? [], fn( $c ) => $c['pass'] ) );
				$total_checks = count( $result['checks'] ?? [] );
				$detail = $total_checks > 0 ? "{$pass_checks}/{$total_checks} checks passed" : 'ok';
				echo "  ✅   " . str_pad( $name, $max_name_len ) . " PASS   {$detail}\n";
				++$pass_count;
				$outcome = 'success';
			} elseif ( isset( $result['expected'] ) ) {
				echo "  ⚠   " . str_pad( $name, $max_name_len ) . " DENIED {$result['expected']}\n";
				++$expected_fail_count;
				$outcome = 'denied (expected)';
			} else {
				echo "  ❌   " . str_pad( $name, $max_name_len ) . " FAIL\n";
				++$fail_count;
				$outcome = 'error';
				foreach ( $result['checks'] ?? [] as $check ) {
					if ( ! $check['pass'] ) {
						echo "       └ {$check['name']}: {$check['actual']}\n";
					}
				}
				foreach ( $result['errors'] as $err ) {
					echo "       └ {$err}\n";
				}
			}

			if ( $verbose ) {
				$resp_body = $response['body'] ?? null;
				if ( is_array( $resp_body ) ) {
					echo "       Response ({$http_method}):\n";
					if ( isset( $resp_body['code'] ) ) {
						echo "         error_code:  {$resp_body['code']}\n";
						echo "         message:     " . ( $resp_body['message'] ?? '(none)' ) . "\n";
						if ( isset( $resp_body['data']['status'] ) ) {
							echo "         http_status: {$resp_body['data']['status']}\n";
						}
						if ( isset( $resp_body['data']['hint'] ) ) {
							echo "         hint:        {$resp_body['data']['hint']}\n";
						}
						if ( isset( $resp_body['data']['params'] ) ) {
							$params_str = is_array( $resp_body['data']['params'] )
								? json_encode( $resp_body['data']['params'], JSON_UNESCAPED_SLASHES )
								: (string) $resp_body['data']['params'];
							echo "         params:      {$params_str}\n";
						}
					} else {
						$summary = [];
						foreach ( $resp_body as $k => $v ) {
							$val = is_array( $v ) ? 'array(' . count( $v ) . ')' : ( is_string( $v ) ? '"' . mb_substr( $v, 0, 60 ) . '"' : get_debug_type( $v ) );
							$summary[] = "{$k}: {$val}";
						}
						echo "         " . implode( ', ', $summary ) . "\n";
					}
				}
			}

			$results[] = [
				'name'    => "{$name}[{$slug}]",
				'status'  => $result['pass'] ? 'pass' : ( isset( $result['expected'] ) ? 'expected_fail' : 'fail' ),
				'intent'  => $intent,
				'outcome' => $outcome,
				'checks'  => $result['checks'] ?? [],
				'errors'  => $result['errors'] ?? [],
			];

			if ( method_exists( $tool, 'cleanup' ) ) {
				$tool->cleanup( $site_url, $auth );
			}
		}

		echo "\n";
	}
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  SECTION 4: Results\n";
echo "═══════════════════════════════════════════════════════════\n";
$total = $pass_count + $fail_count + $expected_fail_count + $skip_count;
$pct   = fn( $n ) => $total > 0 ? ' (' . round( $n / $total * 100 ) . '%)' : '';
echo "  Total:   {$total}\n";
echo "  Passed:  {$pass_count}{$pct( $pass_count )}\n";
echo "  Denied (expected):  {$expected_fail_count}{$pct( $expected_fail_count )}\n";
echo "  Failed:  {$fail_count}{$pct( $fail_count )}\n";
echo "  Skipped: {$skip_count}{$pct( $skip_count )}\n";
echo "═══════════════════════════════════════════════════════════\n";

if ( $fail_count > 0 || $expected_fail_count > 0 ) {
	if ( $expected_fail_count > 0 ) {
		echo "\n  Denied (expected) — correct behavior:\n";
		foreach ( $results as $r ) {
			if ( $r['status'] === 'expected_fail' ) {
				echo "    ⚠ {$r['name']}\n";
				$reason = $r['outcome'] ?? 'denied (expected)';
				echo "       └ intent: {$r['intent']}\n";
				echo "       └ outcome: {$reason}\n";
			}
		}
	}
	if ( $fail_count > 0 ) {
		echo "\n  Failed tools (unexpected errors):\n";
		foreach ( $results as $r ) {
			if ( $r['status'] === 'fail' ) {
				echo "    ❌ {$r['name']}\n";
				foreach ( $r['errors'] as $err ) {
					echo "       └ {$err}\n";
				}
			}
		}
	}
}

echo "\n";

exit( $fail_count > 0 ? 1 : 0 );
