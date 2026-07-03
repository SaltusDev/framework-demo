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
	private const ORIGINAL_VERSION           = '2.0.0';

	private string $source_dir;

	public function __construct( string $source_dir ) {
		$this->source_dir = rtrim( $source_dir, '/\\' );
	}

	public function stream_zip( PluginIdentity $identity ): void {
		if ( headers_sent() ) {
			throw new \RuntimeException( 'Headers were already sent before the ZIP download could start.' );
		}

		while ( ob_get_level() ) {
			if ( ob_end_clean() === false ) {
				break;
			}
		}

		$tmp_file = wp_tempnam( $identity->plugin_slug . '.zip' );
		if ( ! $tmp_file ) {
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
		if ( $zip->open( $destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			throw new \RuntimeException( 'Could not open the temporary ZIP file.' );
		}
		$this->add_main_file( $zip, $identity );
		$this->add_files( $zip, $identity );
		if ( ! $zip->close() ) {
			throw new \RuntimeException( 'Failed to write the ZIP file to disk.' );
		}
	}

	private function add_main_file( \ZipArchive $zip, PluginIdentity $identity ): void {
		$main_file_path = $this->source_dir . '/' . self::ORIGINAL_MAIN_FILE;
		if ( ! is_file( $main_file_path ) ) {
			throw new \RuntimeException( 'Main plugin file not found: ' . self::ORIGINAL_MAIN_FILE ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$contents = file_get_contents( $main_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $contents === false ) {
			throw new \RuntimeException( 'Could not read the main plugin file.' );
		}

		$contents = $this->rewrite_main_file_contents( $contents, $identity );

		$target_path = $identity->plugin_slug . '/' . $identity->main_file;
		if ( ! $zip->addFromString( $target_path, $contents ) ) {
			throw new \RuntimeException( 'Failed to add the main plugin file to the ZIP archive.' );
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

			if ( $relative_path === self::ORIGINAL_MAIN_FILE ) {
				continue;
			}

			$target_path = $identity->plugin_slug . '/' . $this->target_path( $relative_path, $identity );
			if ( $this->is_text_file( $relative_path ) ) {
				$contents = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( $contents === false ) {
					throw new \RuntimeException( 'Could not read ' . $relative_path ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				$contents = $this->rewrite_contents( $contents, $identity );
				$added    = $zip->addFromString( $target_path, $contents );
			} else {
				$added = $zip->addFile( $file->getPathname(), $target_path );
			}

			if ( ! $added ) {
				throw new \RuntimeException( 'Failed to add ' . $relative_path . ' to the ZIP archive.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
	}

	private function relative_path( string $path ): string {
		return substr( $path, strlen( $this->source_dir ) + 1 );
	}

	private function target_path( string $relative_path, PluginIdentity $identity ): string {
		$path = str_replace( self::ORIGINAL_MAIN_FILE, $identity->main_file, $relative_path );
		$path = str_replace( self::ORIGINAL_SLUG . '.pot', $identity->plugin_slug . '.pot', $path );

		return str_replace( '\\', '/', $path );
	}

	private function should_exclude( string $relative_path ): bool {
		$normalized = str_replace( '\\', '/', $relative_path );
		$parts      = explode( '/', $normalized );

		$excluded_dirs = array( '.git', '.codex', '.agents', 'node_modules', 'build', 'dist', 'release', 'reports', 'vendor' );
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
		return (bool) preg_match( '/\.(php|json|md|txt|xml|css|js|pot)$/', $relative_path );
	}

	private function rewrite_contents( string $contents, PluginIdentity $identity ): string {
		$replacements = array(
			self::ORIGINAL_NAMESPACE_SEGMENT => $identity->namespace_segment,
			self::ORIGINAL_PACKAGE           => $this->package_name( $identity ),
			self::ORIGINAL_MAIN_FILE         => $identity->main_file,
			self::ORIGINAL_PREFIX_UPPER      => strtoupper( $identity->prefix ),
			self::ORIGINAL_PREFIX            => $identity->prefix,
			self::ORIGINAL_SLUG              => $identity->plugin_slug,
		);

		$contents = strtr( $contents, $replacements );

		return $this->rewrite_composer_autoloader_classes( $contents, $identity );
	}

	private function rewrite_composer_autoloader_classes( string $contents, PluginIdentity $identity ): string {
		$suffix = substr( md5( $identity->plugin_slug . '|' . $identity->namespace_segment . '|' . $identity->prefix ), 0, 32 );

		$contents = preg_replace( '/ComposerAutoloaderInit[a-f0-9]{32}/', 'ComposerAutoloaderInit' . $suffix, $contents ) ?? $contents;
		$contents = preg_replace( '/ComposerStaticInit[a-f0-9]{32}/', 'ComposerStaticInit' . $suffix, $contents ) ?? $contents;
		$contents = preg_replace( '/composerRequire[a-f0-9]{32}/', 'composerRequire' . $suffix, $contents ) ?? $contents;

		return $contents;
	}

	private function rewrite_main_file_contents( string $contents, PluginIdentity $identity ): string {
		$contents = $this->replace_plugin_header( $contents, $identity );
		$contents = str_replace(
			"PLUGIN_VERSION', '" . self::ORIGINAL_VERSION . "'",
			"PLUGIN_VERSION', '" . $identity->version . "'",
			$contents
		);

		return $this->rewrite_contents( $contents, $identity );
	}

	private function replace_plugin_header( string $contents, PluginIdentity $identity ): string {
		$header = $this->plugin_header( $identity );
		$result = preg_replace( '/\/\*\*[\s\S]*?@wordpress-plugin[\s\S]*?\*\/\s*/', $header, $contents, 1 );

		if ( $result !== null && $result !== $contents ) {
			return $result;
		}

		return preg_replace( '/^<\?php\s*/', "<?php\n" . $header, $contents, 1 ) ?? $contents;
	}

	private function plugin_header( PluginIdentity $identity ): string {
		return "/**\n"
			. " * {$identity->plugin_name}\n"
			. " *\n"
			. " * @wordpress-plugin\n"
			. " * Plugin Name:       {$identity->plugin_name}\n"
			. " * Plugin URI:        {$identity->plugin_uri}\n"
			. " * Description:       {$identity->description}\n"
			. " * Version:           {$identity->version}\n"
			. " * Author:            {$identity->author}\n"
			. " * Author URI:        {$identity->author_uri}\n"
			. " * License:           GPL-2.0-or-later\n"
			. " * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt\n"
			. " * Text Domain:       {$identity->text_domain}\n"
			. " * Domain Path:       /languages\n"
			. " * Requires PHP:      8.3\n"
			. " */\n\n";
	}

	private function package_name( PluginIdentity $identity ): string {
		$vendor = trim( strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', $identity->author ) ), '-' );

		if ( $vendor === '' ) {
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
