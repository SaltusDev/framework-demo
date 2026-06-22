<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer;

/**
 * Generates renamed plugin ZIP packages.
 */
class PluginRenamer {

	private const ORIGINAL_SLUG              = 'framework-demo';
	private const ORIGINAL_MAIN_FILE         = 'framework-demo.php';
	private const ORIGINAL_NAMESPACE_SEGMENT = 'PluginFrameworkDemo';
	private const ORIGINAL_PACKAGE           = 'saltus/framework-demo';
	private const ORIGINAL_PREFIX            = 'framework_demo';
	private const ORIGINAL_PREFIX_UPPER      = 'FRAMEWORK_DEMO';
	private const ORIGINAL_NAME              = 'Saltus Framework Demo';
	private const ORIGINAL_DESCRIPTION       = 'Saltus Plugin Framework Demo.';
	private const ORIGINAL_AUTHOR            = 'Saltus';
	private const ORIGINAL_AUTHOR_URI        = 'https://saltus.io/';
	private const ORIGINAL_PLUGIN_URI        = 'https://saltus.io/';
	private const ORIGINAL_VERSION           = '2.0.0';

	private string $source_dir;

	public function __construct( string $source_dir ) {
		$this->source_dir = rtrim( $source_dir, DIRECTORY_SEPARATOR );
	}

	public function stream_zip( PluginIdentity $identity ): void {
		if ( headers_sent() ) {
			throw new \RuntimeException( 'Headers were already sent before the ZIP download could start.' );
		}

		while ( ob_get_level() ) {
			if ( false === ob_end_clean() ) {
				break;
			}
		}

		$tmp_file = wp_tempnam( $identity->plugin_slug . '.zip' );
		if ( '' === $tmp_file ) {
			throw new \RuntimeException( 'Could not create a temporary ZIP file.' );
		}

		register_shutdown_function( function () use ( $tmp_file ): void {
			if ( is_file( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		} );

		$this->build_zip( $identity, $tmp_file );

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $identity->plugin_slug . '.zip"' );
		header( 'Content-Length: ' . (string) filesize( $tmp_file ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->delete_file( $tmp_file );
		exit;
	}

	public function build_zip( PluginIdentity $identity, string $destination ): void {
		if ( ! class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'The PHP zip extension is required.' );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( 'Could not open the temporary ZIP file.' );
		}

		$this->add_files( $zip, $identity );
		if ( ! $zip->close() ) {
			throw new \RuntimeException( 'Failed to write the ZIP file to disk.' );
		}
	}

	private function add_files( \ZipArchive $zip, PluginIdentity $identity ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$this->source_dir,
				\FilesystemIterator::SKIP_DOTS
			)
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
				continue;
			}

			$relative_path = $this->relative_path( $file->getPathname() );
			if ( $this->should_exclude( $relative_path ) ) {
				continue;
			}

			$target_path = $this->target_path( $relative_path, $identity );
			$contents    = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $contents ) {
				throw new \RuntimeException( 'Could not read ' . $relative_path ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			if ( $this->is_text_file( $relative_path ) ) {
				$contents = $this->rewrite_contents( $contents, $identity );
			}

			if ( ! $zip->addFromString( $identity->plugin_slug . '/' . $target_path, $contents ) ) {
				throw new \RuntimeException( 'Failed to add ' . $relative_path . ' to the ZIP archive.' );
			}
		}
	}

	private function relative_path( string $path ): string {
		return substr( $path, strlen( $this->source_dir ) + 1 );
	}

	private function target_path( string $relative_path, PluginIdentity $identity ): string {
		$path = str_replace( self::ORIGINAL_MAIN_FILE, $identity->main_file, $relative_path );
		$path = str_replace( self::ORIGINAL_SLUG . '.pot', $identity->text_domain . '.pot', $path );

		return str_replace( '\\', '/', $path );
	}

	private function should_exclude( string $relative_path ): bool {
		$normalized = str_replace( '\\', '/', $relative_path );
		$parts      = explode( '/', $normalized );

		$excluded_dirs = array( '.git', '.codex', '.agents', 'vendor', 'node_modules', 'build', 'dist', 'release', 'reports' );
		if ( array_intersect( $parts, $excluded_dirs ) ) {
			return true;
		}

		$basename = basename( $normalized );
		if ( in_array( $basename, array( '.DS_Store', 'composer.lock', 'package-lock.json' ), true ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(zip|log|map|cache)$/', $basename );
	}

	private function is_text_file( string $relative_path ): bool {
		return (bool) preg_match( '/\.(php|json|md|txt|xml|css|pot)$/', $relative_path );
	}

	private function rewrite_contents( string $contents, PluginIdentity $identity ): string {
		$replacements = array(
			self::ORIGINAL_NAMESPACE_SEGMENT              => $identity->namespace_segment,
			self::ORIGINAL_PACKAGE                        => $this->package_name( $identity ),
			self::ORIGINAL_MAIN_FILE                      => $identity->main_file,
			self::ORIGINAL_PREFIX_UPPER                   => strtoupper( $identity->prefix ),
			self::ORIGINAL_PREFIX                         => $identity->prefix,
			'Plugin Name:       ' . self::ORIGINAL_NAME   => 'Plugin Name:       ' . $identity->plugin_name,
			'Description:       ' . self::ORIGINAL_DESCRIPTION => 'Description:       ' . $identity->description,
			'Plugin URI:        ' . self::ORIGINAL_PLUGIN_URI => 'Plugin URI:        ' . $identity->plugin_uri,
			'Author URI:        ' . self::ORIGINAL_AUTHOR_URI => 'Author URI:        ' . $identity->author_uri,
			'Author:            ' . self::ORIGINAL_AUTHOR => 'Author:            ' . $identity->author,
			self::ORIGINAL_SLUG                           => $identity->plugin_slug,
			"PLUGIN_VERSION', '" . self::ORIGINAL_VERSION . "'" => "PLUGIN_VERSION', '" . $identity->version . "'",
			self::ORIGINAL_VERSION                        => $identity->version,
		);

		return strtr( $contents, $replacements );
	}

	private function package_name( PluginIdentity $identity ): string {
		$vendor = trim( strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', $identity->author ) ), '-' );

		if ( '' === $vendor ) {
			$vendor = 'local';
		}

		return $vendor . '/' . $identity->plugin_slug;
	}

	private function delete_file( string $path ): void {
		if ( is_file( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
