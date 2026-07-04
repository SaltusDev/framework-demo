<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer;

/**
 * Value object for a generated plugin identity.
 */
class PluginIdentity {

	public string $plugin_name;
	public string $plugin_slug;
	public string $main_file;
	public string $namespace_segment;
	public string $text_domain;
	public string $description;
	public string $author;
	public string $author_uri;
	public string $plugin_uri;
	public string $version;
	public string $prefix;
	public bool $saltus_contributor = false;

	/**
	 * @param array<string,string> $data Renamer field values.
	 */
	public function __construct( array $data ) {
		$this->plugin_name        = $data['plugin_name'];
		$this->plugin_slug        = $data['plugin_slug'];
		$this->main_file          = $data['main_file'];
		$this->namespace_segment  = $data['namespace_segment'];
		$this->text_domain        = $this->plugin_slug;
		$this->description        = $data['description'];
		$this->author             = $data['author'];
		$this->author_uri         = $data['author_uri'];
		$this->plugin_uri         = $data['plugin_uri'];
		$this->version            = $data['version'];
		$this->prefix             = $data['prefix'];
		$this->saltus_contributor = ! empty( $data['saltus_contributor'] );
	}

	/**
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
			'plugin_name'       => 'My Saltus Plugin',
			'plugin_slug'       => 'my-saltus-plugin',
			'main_file'         => 'my-saltus-plugin.php',
			'namespace_segment' => 'MySaltusPlugin',
			'description'       => 'A WordPress plugin built with the Saltus Framework.',
			'author'            => 'Your Name',
			'author_uri'        => 'https://saltus.dev/',
			'plugin_uri'        => 'https://saltus.dev/my-saltus-plugin/',
			'version'           => '1.0.0',
			'prefix'            => 'my_saltus_plugin',
		);
	}

	/**
	 * @param array<string,mixed> $request Raw request values.
	 */
	public static function from_request( array $request ): self {
		$data = array();

		foreach ( array_keys( self::defaults() ) as $key ) {
			$value        = isset( $request[ $key ] ) && ! is_array( $request[ $key ] ) ? (string) $request[ $key ] : '';
			$data[ $key ] = sanitize_text_field( $value );
		}

		$data['saltus_contributor'] = ! empty( $request['saltus_contributor'] ) && (string) $request['saltus_contributor'] === '1';
		$data['author_uri']         = trim( $data['author_uri'] );
		$data['plugin_uri']         = trim( $data['plugin_uri'] );

		$dangerous_tokens = array( '*/', '?>', '<?php', '<?=', '<?' );
		$fields           = [ 'plugin_name', 'description', 'author', 'author_uri', 'plugin_uri' ];
		foreach ( $fields as $comment_field ) {
			do {
				$before = $data[ $comment_field ];

				$data[ $comment_field ] = str_ireplace( $dangerous_tokens, '', $before );
			} while ( $data[ $comment_field ] !== $before );
		}

		$data['author_uri'] = esc_url_raw( $data['author_uri'] );
		$data['plugin_uri'] = esc_url_raw( $data['plugin_uri'] );

		self::validate( $data );
		return new self( $data );
	}

	/**
	 * @param array<string,string> $data Renamer field values.
	 */
	private static function validate( array $data ): void {
		$required = array( 'plugin_name', 'plugin_slug', 'main_file', 'namespace_segment', 'description', 'author', 'version', 'prefix' );

		foreach ( $required as $field ) {
			if ( trim( $data[ $field ] ) === '' ) {
				/* translators: %s: field label */
				throw new ValidationException( sprintf( __( '%s is required.', 'framework-demo' ), self::label( $field ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['plugin_slug'] ) ) {
			throw new ValidationException( __( 'Plugin slug must contain only lowercase letters, numbers, and single dashes.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*\.php$/', $data['main_file'] ) ) {
			throw new ValidationException( __( 'Main plugin file must be a lowercase dash-safe PHP filename.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! preg_match( '/^[A-Z][A-Za-z0-9]*$/', $data['namespace_segment'] ) ) {
			throw new ValidationException( __( 'Namespace segment must be a valid PHP namespace segment, such as MyPlugin.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?(?:\+[a-zA-Z0-9.]+)?$/', $data['version'] ) ) {
			throw new ValidationException( __( 'Version must be a valid semver string like 1.0.0 or 2.0.0-beta.1.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $data['prefix'] ) ) {
			throw new ValidationException( __( 'Code prefix must contain only lowercase letters, numbers, and underscores, and start with a letter.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		foreach ( array( 'author_uri', 'plugin_uri' ) as $url_field ) {
			if ( $data[ $url_field ] !== '' && filter_var( $data[ $url_field ], FILTER_VALIDATE_URL ) === false ) {
				/* translators: %s: field label */
				throw new ValidationException( sprintf( __( '%s must be a valid URL or empty.', 'framework-demo' ), self::label( $url_field ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
	}

	private static function label( string $field ): string {
		$labels = array(
			'plugin_name'       => __( 'Plugin Name', 'framework-demo' ),
			'plugin_slug'       => __( 'Plugin Slug', 'framework-demo' ),
			'main_file'         => __( 'Main Plugin File', 'framework-demo' ),
			'namespace_segment' => __( 'Namespace Segment', 'framework-demo' ),
			'description'       => __( 'Description', 'framework-demo' ),
			'author'            => __( 'Author', 'framework-demo' ),
			'author_uri'        => __( 'Author URI', 'framework-demo' ),
			'plugin_uri'        => __( 'Plugin URI', 'framework-demo' ),
			'version'           => __( 'Version', 'framework-demo' ),
			'prefix'            => __( 'Code Prefix', 'framework-demo' ),
		);
		return $labels[ $field ] ?? ucwords( str_replace( '_', ' ', $field ) );
	}
}
