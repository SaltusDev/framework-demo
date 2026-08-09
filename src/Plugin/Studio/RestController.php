<?php
/**
 * REST routes for authoring model configuration.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

/**
 * Exposes the reader, printer and writer over REST.
 *
 * ## Namespace
 *
 * `framework-demo/v1`, not `saltus-framework/v1`. Scaffolding is a build-time concern belonging to
 * the plugin being built, not a runtime feature of the framework — and a rebranded plugin gets its
 * own namespace for free, since the renamer rewrites the slug.
 *
 * ## Why the guards live here and in `ModelWriter`
 *
 * `ModelWriter` owns the filesystem boundary: path confinement, syntax validation, atomic writes,
 * refusing to clobber a callable. This class owns the *request* boundary: who is asking, and whether
 * the request is genuine. Neither can do the other's job — the writer is callable from CLI where
 * there is no request, and a permission check cannot know whether a path escapes a directory.
 *
 * Both are needed because writing here is arbitrary code execution: the framework `include`s every
 * file in `src/models/` on every request.
 *
 * ## Read-only by default
 *
 * `POST /models/preview` renders source and returns it. Nothing touches the filesystem unless the
 * caller explicitly hits `POST /models`, which is also the only route that can be disabled by
 * `DISALLOW_FILE_MODS`. Download-first is the safer default, and on many production installs the
 * plugin directory is not writable anyway.
 */
class RestController {

	private const REST_NAMESPACE = 'framework-demo/v1';

	private ModelWriter $writer;

	private ModelReader $reader;

	/**
	 * @param ModelWriter $writer Filesystem writer.
	 * @param ModelReader $reader Config reader.
	 */
	public function __construct( ModelWriter $writer, ModelReader $reader ) {
		$this->writer = $writer;
		$this->reader = $reader;
	}

	/**
	 * Hook the routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declare the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/models/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->route_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/models',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->write_route_args(),
			)
		);
	}

	/**
	 * Shared argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function route_args(): array {
		return array(
			'model'     => array(
				'type'        => 'object',
				'required'    => true,
				'description' => __( 'Model configuration, matching schema/model.schema.json.', 'framework-demo' ),
			),
			'docblock'  => array(
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Optional file-level description.', 'framework-demo' ),
				/*
				 * Rejected here as well as neutralised in the printer. `*​/` closes the docblock, so
				 * everything after it becomes code in a file the framework loads on every request. The
				 * printer escapes the sequence so it can never execute; rejecting it here means a caller
				 * is told rather than having their text silently rewritten. Both boundaries are needed:
				 * the printer also runs from CLI tooling that never passes through this route.
				 */
				'validate_callback' => static function ( $value ) {
					if ( is_string( $value ) && strpos( $value, '*/' ) !== false ) {
						return new \WP_Error(
							'rest_invalid_param',
							__( 'The docblock cannot contain "*/": it would close the comment and turn the rest of the description into executable code.', 'framework-demo' ),
							array( 'status' => 400 )
						);
					}

					return true;
				},
			),
			'gate_slug' => array(
				'type'        => 'string',
				'default'     => '',
				'description' => __( 'Optional demo-toggle slug, for an opt-in model.', 'framework-demo' ),
			),
		);
	}

	/**
	 * Argument schema for the writing route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function write_route_args(): array {
		return array_merge(
			$this->route_args(),
			array(
				'filename'  => array(
					'type'        => 'string',
					'required'    => true,
					'description' => __( 'Target filename inside src/models/.', 'framework-demo' ),
				),
				'overwrite' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Replace an existing file. Refused if the target holds a closure.', 'framework-demo' ),
				),
			)
		);
	}

	/**
	 * Gate every route on capability *and* nonce.
	 *
	 * `manage_options` because generating a model file is equivalent to editing plugin code, and the
	 * nonce because a capability check alone leaves the route open to CSRF: a logged-in administrator
	 * visiting a hostile page would otherwise be enough to write PHP into this plugin.
	 *
	 * WordPress verifies the `wp_rest` nonce itself for cookie-authenticated requests, but not for
	 * other auth schemes — application passwords among them. Checking explicitly means the route
	 * behaves the same however it was reached, and cannot be driven by a stolen app password.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Incoming request.
	 * @return true|\WP_Error
	 */
	public function check_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to author model configuration.', 'framework-demo' ),
				array(
					'status' => rest_authorization_required_code(),
					'hint'   => __( 'Generating a model file writes PHP the framework loads on every request, so it requires the manage_options capability.', 'framework-demo' ),
				)
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! is_string( $nonce ) || wp_verify_nonce( $nonce, 'wp_rest' ) === false ) {
			return new \WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Missing or stale security token.', 'framework-demo' ),
				array(
					'status' => 403,
					'hint'   => __( 'Send a current wp_rest nonce in the X-WP-Nonce header. wp_localize_script() supplies one to admin JavaScript.', 'framework-demo' ),
				)
			);
		}

		return true;
	}

	/**
	 * Render a model without writing anything.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( $request ) {
		$model = $request->get_param( 'model' );

		if ( ! is_array( $model ) ) {
			return $this->invalid_model();
		}

		try {
			$source = $this->writer->render(
				$model,
				(string) $request->get_param( 'docblock' ),
				(string) $request->get_param( 'gate_slug' )
			);
		} catch ( UnrepresentableValueException $exception ) {
			return $this->unrepresentable( $exception );
		} catch ( WriteRefusedException $exception ) {
			return $this->refused( $exception );
		}

		return rest_ensure_response(
			array(
				'source' => $source,
				'bytes'  => strlen( $source ),
			)
		);
	}

	/**
	 * Write a model file.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		$model = $request->get_param( 'model' );

		if ( ! is_array( $model ) ) {
			return $this->invalid_model();
		}

		try {
			$path = $this->writer->write(
				$model,
				(string) $request->get_param( 'filename' ),
				(bool) $request->get_param( 'overwrite' ),
				(string) $request->get_param( 'docblock' ),
				(string) $request->get_param( 'gate_slug' )
			);
		} catch ( UnrepresentableValueException $exception ) {
			return $this->unrepresentable( $exception );
		} catch ( WriteRefusedException $exception ) {
			return $this->refused( $exception );
		}

		$response = rest_ensure_response(
			array(
				'written'  => true,
				'filename' => basename( $path ),
				'models'   => count( $this->reader->read( $path )['models'] ),
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * @return \WP_Error
	 */
	private function invalid_model() {
		return new \WP_Error(
			'invalid_model',
			__( 'The "model" parameter must be an object.', 'framework-demo' ),
			array(
				'status' => 400,
				'hint'   => __( 'Send the model configuration as a JSON object with at least a "type" key.', 'framework-demo' ),
			)
		);
	}

	/**
	 * Map a printer refusal to a response.
	 *
	 * 422 rather than 400: the request was well-formed, but the config contains something that has no
	 * PHP representation. Nothing the caller can fix by reformatting.
	 *
	 * @param UnrepresentableValueException $exception Printer refusal.
	 * @return \WP_Error
	 */
	private function unrepresentable( UnrepresentableValueException $exception ) {
		return new \WP_Error(
			'unrepresentable_value',
			$exception->getMessage(),
			array(
				'status' => 422,
				'path'   => $exception->path,
				'type'   => $exception->value_type,
				'hint'   => __( 'Closures and objects cannot be written back to config. Remove the callable, or hand-edit the generated file.', 'framework-demo' ),
			)
		);
	}

	/**
	 * Map a writer refusal to a response.
	 *
	 * The reason code becomes the error code so a client can branch on it, and the status
	 * distinguishes "not allowed here" from "conflicts with what is on disk".
	 *
	 * @param WriteRefusedException $exception Writer refusal.
	 * @return \WP_Error
	 */
	private function refused( WriteRefusedException $exception ) {
		$statuses = array(
			WriteRefusedException::REASON_DISALLOWED     => 403,
			WriteRefusedException::REASON_BAD_NAME       => 400,
			WriteRefusedException::REASON_OUTSIDE_ROOT   => 400,
			WriteRefusedException::REASON_EXISTS         => 409,
			WriteRefusedException::REASON_HAS_CLOSURE    => 409,
			WriteRefusedException::REASON_INVALID_SYNTAX => 500,
			WriteRefusedException::REASON_IO             => 500,
		);

		return new \WP_Error(
			$exception->reason,
			$exception->getMessage(),
			array( 'status' => $statuses[ $exception->reason ] ?? 500 )
		);
	}
}
