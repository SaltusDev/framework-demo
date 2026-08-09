<?php
/**
 * Tests for the Studio REST layer.
 *
 * The permission callback is the subject: it is the only thing standing between a request and PHP
 * being written into a directory the framework `include`s on every request. Both halves are checked
 * independently, because either alone is insufficient — capability without a nonce leaves the route
 * open to CSRF, and a nonce without a capability lets any logged-in subscriber through.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Tests\Plugin\Studio;

use PHPUnit\Framework\TestCase;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelPrinter;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelReader;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\ModelWriter;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio\RestController;

final class RestControllerTest extends TestCase {

	private string $dir = '';

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 3 ) . '/bin/model-loader.php';
	}

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/saltus-rest-' . uniqid();
		mkdir( $this->dir, 0777, true );

		// Defaults: an administrator with a valid nonce. Individual tests narrow these.
		$GLOBALS['test_capabilities'] = array( 'manage_options' => true );
		$GLOBALS['test_valid_nonces'] = array( 'wp_rest' => 'good-nonce' );
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->dir . '/{,.}*', GLOB_BRACE ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file );
			}
		}

		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir );
		}

		unset( $GLOBALS['test_capabilities'], $GLOBALS['test_valid_nonces'] );
	}

	private function controller(): RestController {
		return new RestController(
			new ModelWriter( $this->dir, new ModelPrinter( 'framework-demo' ), new ModelReader() ),
			new ModelReader()
		);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function request( array $params = array(), ?string $nonce = 'good-nonce' ): \WP_REST_Request {
		$request = new \WP_REST_Request();

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		if ( $nonce !== null ) {
			$request->set_header( 'X-WP-Nonce', $nonce );
		}

		return $request;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function model(): array {
		return array(
			'type' => 'cpt',
			'name' => 'widget',
		);
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Permission callback
	 * ---------------------------------------------------------------------------
	 */

	public function test_an_administrator_with_a_valid_nonce_is_allowed(): void {
		self::assertTrue( $this->controller()->check_permission( $this->request() ) );
	}

	public function test_a_user_without_manage_options_is_refused(): void {
		$GLOBALS['test_capabilities'] = array( 'edit_posts' => true );

		$result = $this->controller()->check_permission( $this->request() );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rest_forbidden', $result->code );
	}

	/**
	 * Capability alone is not enough: without a nonce, an administrator visiting a hostile page could
	 * be made to write PHP into the plugin.
	 */
	public function test_a_missing_nonce_is_refused_even_for_an_administrator(): void {
		$result = $this->controller()->check_permission( $this->request( array(), null ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rest_cookie_invalid_nonce', $result->code );
	}

	public function test_a_stale_nonce_is_refused(): void {
		$result = $this->controller()->check_permission( $this->request( array(), 'stale' ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'rest_cookie_invalid_nonce', $result->code );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Preview
	 * ---------------------------------------------------------------------------
	 */

	public function test_preview_returns_source_and_writes_nothing(): void {
		$response = $this->controller()->preview( $this->request( array( 'model' => self::model() ) ) );

		self::assertInstanceOf( \WP_REST_Response::class, $response );

		$data = $response->get_data();

		self::assertStringContainsString( "'name'", $data['source'] );
		self::assertSame( strlen( $data['source'] ), $data['bytes'] );
		self::assertSame( array(), glob( $this->dir . '/*' ) ?: array() );
	}

	public function test_preview_rejects_a_non_object_model(): void {
		$result = $this->controller()->preview( $this->request( array( 'model' => 'nope' ) ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'invalid_model', $result->code );
	}

	/**
	 * 422, not 400: the request was well-formed and the config simply has no PHP representation.
	 */
	public function test_preview_reports_a_closure_as_unprocessable(): void {
		$model = array(
			'type'     => 'cpt',
			'name'     => 'z',
			'features' => array( 'admin_cols' => array( 'c' => array( 'function' => static fn() => 1 ) ) ),
		);

		$result = $this->controller()->preview( $this->request( array( 'model' => $model ) ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'unrepresentable_value', $result->code );
		self::assertSame( 422, $result->data['status'] );
		self::assertSame( 'features.admin_cols.c.function', $result->data['path'] );
	}

	/*
	 * ---------------------------------------------------------------------------
	 * Create
	 * ---------------------------------------------------------------------------
	 */

	public function test_create_writes_a_file_and_returns_201(): void {
		$response = $this->controller()->create(
			$this->request(
				array(
					'model'    => self::model(),
					'filename' => 'post-type-widget.php',
				)
			)
		);

		self::assertInstanceOf( \WP_REST_Response::class, $response );
		self::assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		self::assertTrue( $data['written'] );
		self::assertSame( 'post-type-widget.php', $data['filename'] );
		self::assertSame( 1, $data['models'] );
		self::assertFileExists( $this->dir . '/post-type-widget.php' );
	}

	public function test_create_maps_an_unsafe_filename_to_400(): void {
		$result = $this->controller()->create(
			$this->request(
				array(
					'model'    => self::model(),
					'filename' => '../evil.php',
				)
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'invalid_filename', $result->code );
		self::assertSame( 400, $result->data['status'] );
	}

	/**
	 * 409 rather than 400 or 500: the request is valid, it conflicts with what is on disk.
	 */
	public function test_create_maps_an_existing_file_to_409(): void {
		$controller = $this->controller();
		$params     = array(
			'model'    => self::model(),
			'filename' => 'a.php',
		);

		$controller->create( $this->request( $params ) );
		$result = $controller->create( $this->request( $params ) );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'file_exists', $result->code );
		self::assertSame( 409, $result->data['status'] );
	}

	public function test_create_refuses_to_clobber_a_closure_file(): void {
		$original = '<?php return array( "type" => "cpt", "name" => "c", "features" => array( "admin_cols" => array( "x" => array( "function" => function () {} ) ) ) );';
		file_put_contents( $this->dir . '/has-closure.php', $original );

		$result = $this->controller()->create(
			$this->request(
				array(
					'model'     => self::model(),
					'filename'  => 'has-closure.php',
					'overwrite' => true,
				)
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'target_has_closure', $result->code );
		self::assertSame( 409, $result->data['status'] );
		self::assertSame( $original, file_get_contents( $this->dir . '/has-closure.php' ) );
	}

	public function test_create_overwrites_when_asked_and_safe(): void {
		$controller = $this->controller();
		$controller->create(
			$this->request(
				array(
					'model'    => self::model(),
					'filename' => 'a.php',
				)
			)
		);

		$updated         = self::model();
		$updated['name'] = 'gadget';

		$response = $controller->create(
			$this->request(
				array(
					'model'     => $updated,
					'filename'  => 'a.php',
					'overwrite' => true,
				)
			)
		);

		self::assertInstanceOf( \WP_REST_Response::class, $response );

		$recovered = include $this->dir . '/a.php';
		self::assertSame( 'gadget', $recovered['name'] );
	}

	public function test_create_passes_the_gate_slug_through(): void {
		$this->controller()->create(
			$this->request(
				array(
					'model'     => self::model(),
					'filename'  => 'a.php',
					'gate_slug' => 'widget',
				)
			)
		);

		self::assertStringContainsString(
			"saltus_demo_model_enabled( 'widget' )",
			(string) file_get_contents( $this->dir . '/a.php' )
		);
	}
}
