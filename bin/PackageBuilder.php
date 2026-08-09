<?php
/**
 * Create a distributable ZIP for the demo plugin.
 */

class PackageBuilder {

	private string $root;
	private string $slug;
	private string $stage;
	private string $dist;

	public function __construct( ?string $root = null ) {
		$this->root  = $root ?? dirname( __DIR__ );
		$this->slug  = 'framework-demo';
		$this->stage = $this->root . '/build/release/' . $this->slug;
		$this->dist  = $this->root . '/dist';
	}

	public function run(): void {
		try {
			$this->validateEnvironment();
			$version = $this->readPluginVersion();
			$this->validateVersion( $version );

			$zip_path = $this->dist . '/' . $this->slug . '-' . $version . '.zip';

			$this->removePath( $this->stage );
			$this->ensureDirectoryExists( $this->stage );

			$this->copyReleaseFiles( $this->root, $this->stage );
			$this->minifyStageCss( $this->stage );
			$this->installReleaseDependencies( $this->stage );
			$this->removePath( $this->stage . '/composer.json' );
			$this->removePath( $this->stage . '/composer.lock' );
			$this->removePath( $zip_path );

			$this->createZip( $zip_path );

			echo "Created {$zip_path}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		} catch ( RuntimeException $exception ) {
			fwrite( STDERR, $exception->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit( 1 );
		}
	}

	private function validateEnvironment(): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( 'The PHP zip extension is required.' );
		}
	}

	private function ensureDirectoryExists( string $path ): void {
		if ( ! is_dir( $path ) && ! mkdir( $path, 0775, true ) && ! is_dir( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			throw new RuntimeException( "Could not create {$path}." );
		}
	}

	private function readPluginVersion(): string {
		$plugin_file = $this->root . '/' . $this->slug . '.php';
		$contents    = file_get_contents( $plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $contents ) {
			throw new RuntimeException( "Could not read {$plugin_file}." );
		}

		if ( 1 === preg_match( "/PLUGIN_VERSION'\\s*,\\s*'([^']+)'/", $contents, $matches ) ) {
			return $matches[1];
		}

		if ( 1 === preg_match( '/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $contents, $matches ) ) {
			return trim( $matches[1] );
		}

		throw new RuntimeException( 'Could not determine the plugin version.' );
	}

	private function validateVersion( string $version ): void {
		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?(?:\+[a-zA-Z0-9.]+)?$/', $version ) ) {
			throw new RuntimeException( "Invalid plugin version {$version}." );
		}
	}

	private function removePath( string $path ): void {
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

	private function isExcludedFromRelease( string $relative ): bool {
		$parts = explode( DIRECTORY_SEPARATOR, $relative );

		/*
		 * `.claude` and `docs` are development-only. `.claude/settings.local.json` is a local agent
		 * permission config, and `docs/` is this repo's internal planning material (ROADMAP, EPIC-STUDIO,
		 * CURRENT) describing unshipped work and known upstream bugs. Neither belongs in a distributed
		 * ZIP. Kept in step with `PluginRenamer::should_exclude()`, which excludes the same three.
		 */
		$excluded_dirs = array(
			'.agents',
			'.claude',
			'.codex',
			'.git',
			'.github',
			'.phpunit.cache',
			'bin',
			'build',
			'dist',
			'docs',
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

		// Matched by basename. `readme.txt` is deliberately absent: WP.org reads the stable tag,
		// tested-up-to and changelog from it, so it must ship.
		$basename       = basename( $relative );
		$excluded_files = array(
			'.gitignore',
			'.phpunit.result.cache',
			'HANDOFF.md',
			'package.json',
			'package-lock.json',
			'phpcs.xml',
			'phpunit.xml.dist',
			'postcss.config.js',
			'README.md',
		);

		return in_array( $basename, $excluded_files, true )
			|| 1 === preg_match( '/\.(zip|log|map|cache)$/', $basename );
	}

	private function copyReleaseFiles( string $source, string $target ): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative = substr( $item->getPathname(), strlen( $source ) + 1 );

			if ( $this->isExcludedFromRelease( $relative ) ) {
				continue;
			}

			$destination = $target . DIRECTORY_SEPARATOR . $relative;

			if ( $item->isDir() ) {
				$this->ensureDirectoryExists( $destination );
				continue;
			}

			$destination_dir = dirname( $destination );
			$this->ensureDirectoryExists( $destination_dir );

			if ( ! copy( $item->getPathname(), $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				throw new RuntimeException( "Could not copy {$relative}." );
			}
		}
	}

	private function minifyStageCss( string $working_dir ): void {
		$css_dir = $working_dir . '/assets/css';

		if ( ! is_dir( $css_dir ) ) {
			return;
		}

		$css_files = glob( $css_dir . '/*.css' );

		if ( empty( $css_files ) ) {
			return;
		}

		$npx     = PHP_OS_FAMILY === 'WINNT' ? 'npx.cmd' : 'npx';
		$files   = implode( ' ', array_map( 'escapeshellarg', $css_files ) );
		$command = sprintf(
			'%s postcss %s --dir %s --no-map 2>&1',
			escapeshellcmd( $npx ),
			$files,
			escapeshellarg( $css_dir )
		);

		passthru( $command, $exit_code );

		if ( 0 !== $exit_code ) {
			throw new RuntimeException( 'CSS minification failed.' );
		}
	}

	private function installReleaseDependencies( string $working_dir ): void {
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

		$this->removePath( $working_dir . '/vendor' );
	}

	private function createZip( string $zip_path ): void {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( "Could not open {$zip_path}." );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->stage, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
				continue;
			}

			$relative = substr( $file->getPathname(), strlen( $this->stage ) + 1 );
			$zip->addFile( $file->getPathname(), $this->slug . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relative ) );
		}

		$zip->close();
	}
}
