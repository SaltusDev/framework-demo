<?php
/**
 * Create a distributable ZIP for the demo plugin.
 */

$root     = dirname( __DIR__ );
$slug     = 'framework-demo';
$dist     = $root . '/dist';
$zip_path = $dist . '/' . $slug . '.zip';

if ( ! class_exists( ZipArchive::class ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

if ( ! is_dir( $dist ) && ! mkdir( $dist, 0775, true ) && ! is_dir( $dist ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( STDERR, "Could not create dist directory.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not open {$zip_path}.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit( 1 );
}

$excluded_dirs  = array( '.git', '.codex', '.agents', 'node_modules', 'build', 'dist', 'release', 'reports' );
$excluded_files = array( 'composer.lock', 'package-lock.json' );

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
		continue;
	}

	$relative = ltrim( str_replace( $root, '', $file->getPathname() ), DIRECTORY_SEPARATOR );
	$parts    = explode( DIRECTORY_SEPARATOR, $relative );

	if ( array_intersect( $parts, $excluded_dirs ) ) {
		continue;
	}

	if ( in_array( basename( $relative ), $excluded_files, true ) || preg_match( '/\.(zip|log|map|cache)$/', basename( $relative ) ) ) {
		continue;
	}

	$zip->addFile( $file->getPathname(), $slug . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relative ) );
}

$zip->close();

echo "Created {$zip_path}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
