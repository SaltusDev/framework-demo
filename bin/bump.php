<?php
/**
 * Bump the plugin version, commit, and tag.
 *
 * Usage: php bin/bump.php <patch|minor|major|<semver>>
 */

$root = dirname( __DIR__ );
$plugin_file = $root . '/framework-demo.php';
$slug = 'framework-demo';

/**
 * Read the current version from the plugin file header.
 */
function read_version( string $file ): string {
	$contents = file_get_contents( $file );

	if ( false === $contents ) {
		throw new RuntimeException( "Could not read file: {$file}" );
	}

	if ( 1 === preg_match( "/PLUGIN_VERSION', '([^']+)'/", $contents, $matches ) ) {
		return $matches[1];
	}

	if ( 1 === preg_match( '/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $contents, $matches ) ) {
		return trim( $matches[1] );
	}

	throw new RuntimeException( 'Could not determine the plugin version.' );
}

/**
 * Bump a semver version string.
 */
function bump_version( string $version, string $type ): string {
	if ( 1 !== preg_match( '/^(\d+)\.(\d+)\.(\d+)(?:-([a-zA-Z0-9.]+))?$/', $version, $parts ) ) {
		throw new RuntimeException( "Invalid semver version: {$version}" );
	}

	$major = (int) $parts[1];
	$minor = (int) $parts[2];
	$patch = (int) $parts[3];
	$pre   = $parts[4] ?? null;

	switch ( $type ) {
		case 'major':
			++$major;
			$minor = 0;
			$patch = 0;
			break;
		case 'minor':
			++$minor;
			$patch = 0;
			break;
		case 'patch':
			++$patch;
			break;
		default:
			if ( 1 === preg_match( '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?$/', $type ) ) {
				return $type;
			}
			throw new RuntimeException( "Unknown bump type: {$type}" );
	}

	$new = "{$major}.{$minor}.{$patch}";

	return $new;
}

/**
 * Update the version in the plugin file.
 */
function update_version( string $file, string $old_version, string $new_version ): void {
	$contents = file_get_contents( $file );

	if ( false === $contents ) {
		throw new RuntimeException( "Could not read file: {$file}" );
	}

	$contents = str_replace(
		"PLUGIN_VERSION', '{$old_version}'",
		"PLUGIN_VERSION', '{$new_version}'",
		$contents
	);

	$contents = preg_replace(
		'/^(\s*\*\s*Version:\s*)' . preg_quote( $old_version, '/' ) . '/m',
		'${1}' . $new_version,
		$contents
	);

	file_put_contents( $file, $contents );
}

try {
	$old_version = read_version( $plugin_file );
	echo "Current version: {$old_version}\n";

	if ( $argc < 2 ) {
		echo "Usage: php bin/bump.php <patch|minor|major|<semver>>\n";
		exit( 1 );
	}

	$type = $argv[1];
	$new_version = bump_version( $old_version, $type );

	echo "New version: {$new_version}\n";

	update_version( $plugin_file, $old_version, $new_version );

	$commands = array(
		'git add ' . escapeshellarg( $plugin_file ),
		'git commit -m ' . escapeshellarg( "release(v{$new_version}): bump version from {$old_version} to {$new_version}" ),
		'git tag -a ' . escapeshellarg( "v{$new_version}" ) . ' -m ' . escapeshellarg( "Version {$new_version}" ),
	);

	foreach ( $commands as $command ) {
		echo "+ {$command}\n";
		passthru( $command, $exit_code );

		if ( 0 !== $exit_code ) {
			throw new RuntimeException( "Command failed: {$command}" );
		}
	}

	echo "Done. Version bumped from {$old_version} to {$new_version} and tagged as v{$new_version}.\n";
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, $exception->getMessage() . "\n" );
	exit( 1 );
}
