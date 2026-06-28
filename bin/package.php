<?php
/**
 * Create a distributable ZIP for the demo plugin.
 */

$root     = dirname( __DIR__ );
$slug     = 'framework-demo';
$stage    = $root . '/build/release/' . $slug;
$dist     = $root . '/dist';

/**
 * Read the plugin version from the main plugin file.
 *
 * @param string $plugin_file Main plugin file.
 * @return string
 */
function read_plugin_version( string $plugin_file ): string {
	$contents = file_get_contents( $plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( false === $contents ) {
		throw new RuntimeException( "Could not read {$plugin_file}." );
	}

	if ( 1 === preg_match( "/PLUGIN_VERSION', '([^']+)'/", $contents, $matches ) ) {
		return $matches[1];
	}

	if ( 1 === preg_match( '/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $contents, $matches ) ) {
		return trim( $matches[1] );
	}

	throw new RuntimeException( 'Could not determine the plugin version.' );
}

try {
	$version  = read_plugin_version( $root . '/' . $slug . '.php' );
	if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?(?:\+[a-zA-Z0-9.]+)?$/', $version ) ) {
		throw new RuntimeException( "Invalid plugin version {$version}." );
	}
	$zip_path = $dist . '/' . $slug . '-' . $version . '.zip';
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, $exception->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

if ( ! class_exists( ZipArchive::class ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

if ( ! is_dir( $dist ) && ! mkdir( $dist, 0775, true ) && ! is_dir( $dist ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( STDERR, "Could not create dist directory.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

/**
 * Recursively remove a path.
 *
 * @param string $path Path to remove.
 */
function remove_path( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}

	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * Determine whether a relative path should be excluded from the release stage.
 *
 * @param string $relative Relative path.
 * @return bool
 */
function is_excluded_from_release( string $relative ): bool {
	$parts = explode( DIRECTORY_SEPARATOR, $relative );

	$excluded_dirs = array(
		'.agents',
		'.codex',
		'.git',
		'build',
		'dist',
		'node_modules',
		'release',
		'reports',
		'tests',
		'vendor',
		'vendor-prefixed',
	);

	if ( array_intersect( $parts, $excluded_dirs ) ) {
		return true;
	}

	$basename       = basename( $relative );
	$excluded_files = array(
		'.gitignore',
		'.phpunit.result.cache',
		'package-lock.json',
		'phpcs.xml',
		'phpunit.xml.dist',
	);

	return in_array( $basename, $excluded_files, true )
		|| 1 === preg_match( '/\.(zip|log|map|cache)$/', $basename );
}

/**
 * Copy repository files into the release stage.
 *
 * @param string $source Source directory.
 * @param string $target Target directory.
 */
function copy_release_files( string $source, string $target ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$relative = substr( $item->getPathname(), strlen( $source ) + 1 );

		if ( is_excluded_from_release( $relative ) ) {
			continue;
		}

		$destination = $target . DIRECTORY_SEPARATOR . $relative;

		if ( $item->isDir() ) {
			if ( ! is_dir( $destination ) && ! mkdir( $destination, 0775, true ) && ! is_dir( $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				throw new RuntimeException( "Could not create {$destination}." );
			}
			continue;
		}

		$destination_dir = dirname( $destination );
		if ( ! is_dir( $destination_dir ) && ! mkdir( $destination_dir, 0775, true ) && ! is_dir( $destination_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			throw new RuntimeException( "Could not create {$destination_dir}." );
		}

		if ( ! copy( $item->getPathname(), $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			throw new RuntimeException( "Could not copy {$relative}." );
		}
	}
}

/**
 * Run Composer in the release stage and prefix production dependencies.
 *
 * @param string $working_dir Composer working directory.
 */
function install_release_dependencies( string $working_dir ): void {
	$composer = getenv( 'COMPOSER_BINARY' ) ?: 'composer';
	if ( false === getenv( 'COMPOSER_CACHE_DIR' ) ) {
		putenv( 'COMPOSER_CACHE_DIR=' . sys_get_temp_dir() . '/framework-demo-composer-cache' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}

	$commands = array(
		sprintf(
			'%s install --optimize-autoloader --no-interaction --no-progress --working-dir=%s',
			escapeshellcmd( $composer ),
			escapeshellarg( $working_dir )
		),
		sprintf(
			'%s prefix-namespaces --working-dir=%s',
			escapeshellcmd( $composer ),
			escapeshellarg( $working_dir )
		),
	);

	foreach ( $commands as $command ) {
		passthru( $command, $exit_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		if ( 0 !== $exit_code ) {
			throw new RuntimeException( 'Composer command failed while building the release package.' );
		}
	}

	remove_path( $working_dir . '/vendor' );
}

/**
 * Add staged files to the release ZIP.
 *
 * @param ZipArchive $zip ZIP archive.
 * @param string     $source Source directory.
 * @param string     $prefix ZIP path prefix.
 */
function zip_release_files( ZipArchive $zip, string $source, string $prefix ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}

		$relative = substr( $file->getPathname(), strlen( $source ) + 1 );
		$zip->addFile( $file->getPathname(), $prefix . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relative ) );
	}
}

try {
	remove_path( $stage );

	if ( ! mkdir( $stage, 0775, true ) && ! is_dir( $stage ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		throw new RuntimeException( 'Could not create release staging directory.' );
	}

	copy_release_files( $root, $stage );
	install_release_dependencies( $stage );
	remove_path( $stage . '/composer.json' );
	remove_path( $stage . '/composer.lock' );
	remove_path( $zip_path );
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, $exception->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not open {$zip_path}.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

zip_release_files( $zip, $stage, $slug );
$zip->close();

echo "Created {$zip_path}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
