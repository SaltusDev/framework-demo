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

	/**
	 * @param array<string,string> $data Renamer field values.
	 */
	public function __construct( array $data ) {
		$this->plugin_name       = $data['plugin_name'];
		$this->plugin_slug       = $data['plugin_slug'];
		$this->main_file         = $data['main_file'];
		$this->namespace_segment = $data['namespace_segment'];
		$this->text_domain       = $data['text_domain'];
		$this->description       = $data['description'];
		$this->author            = $data['author'];
		$this->author_uri        = $data['author_uri'];
		$this->plugin_uri        = $data['plugin_uri'];
		$this->version           = $data['version'];
		$this->prefix            = $data['prefix'];
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
			'text_domain'       => 'my-saltus-plugin',
			'description'       => 'A WordPress plugin built with the Saltus Framework.',
			'author'            => 'Your Name',
			'author_uri'        => 'https://example.com/',
			'plugin_uri'        => 'https://example.com/my-saltus-plugin/',
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
			$value        = isset( $request[ $key ] ) ? (string) $request[ $key ] : '';
			$data[ $key ] = sanitize_text_field( $value );
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
		$required = array( 'plugin_name', 'plugin_slug', 'main_file', 'namespace_segment', 'text_domain', 'description', 'author', 'version', 'prefix' );

		foreach ( $required as $field ) {
			if ( '' === trim( $data[ $field ] ) ) {
				throw new ValidationException( sprintf( '%s is required.', self::label( $field ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['plugin_slug'] ) ) {
			throw new ValidationException( 'Plugin slug must contain only lowercase letters, numbers, and single dashes.' );
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*\.php$/', $data['main_file'] ) ) {
			throw new ValidationException( 'Main plugin file must be a lowercase dash-safe PHP filename.' );
		}

		if ( ! preg_match( '/^[A-Z][A-Za-z0-9]*$/', $data['namespace_segment'] ) ) {
			throw new ValidationException( 'Namespace segment must be a valid PHP namespace segment, such as MyPlugin.' );
		}

		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['text_domain'] ) ) {
			throw new ValidationException( 'Text domain must contain only lowercase letters, numbers, and single dashes.' );
		}

		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $data['prefix'] ) ) {
			throw new ValidationException( 'Code prefix must contain only lowercase letters, numbers, and underscores, and start with a letter.' );
		}

		foreach ( array( 'author_uri', 'plugin_uri' ) as $url_field ) {
			if ( '' !== $data[ $url_field ] && false === filter_var( $data[ $url_field ], FILTER_VALIDATE_URL ) ) {
				throw new ValidationException( sprintf( '%s must be a valid URL or empty.', self::label( $url_field ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
	}

	private static function label( string $field ): string {
		return ucwords( str_replace( '_', ' ', $field ) );
	}
}
