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

	/**
	 * Header fields carried over from the source plugin rather than supplied by the user.
	 *
	 * These are *read out of `framework-demo.php`* at build time, not hardcoded. They used to be
	 * `REQUIRES_WP = '6.0'` / `REQUIRES_PHP = '8.3'` constants, which is the identical trap
	 * `ORIGINAL_VERSION` fell into twice: `bin/bump.php` rewrites only `* Version:`, so raising the
	 * demo's own floor to PHP 8.4 would leave every generated plugin advertising 8.3 with CI green,
	 * because the test asserted the same literals the class declared.
	 */
	private const CARRIED_HEADER_FIELDS = array( 'Requires at least', 'Requires PHP' );

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
				$contents = $this->rewrite_contents( $contents, $identity, $relative_path );

				if ( $identity->saltus_contributor && $this->is_root_manifest( $relative_path ) ) {
					$contents = $this->add_saltus_contributor( $contents );
				}

				$added = $zip->addFromString( $target_path, $contents );
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

		/*
		 * `.claude` and `docs` are development-only and were shipping into every generated plugin:
		 * `.claude/settings.local.json` is one developer's local agent permission config, and `docs/`
		 * holds this repo's internal planning material — ROADMAP, EPIC-STUDIO, CURRENT — which describes
		 * unshipped work, known upstream bugs and framework asks. A client's production plugin has no use
		 * for any of it, and the roadmap in particular reads as a list of this plugin's defects.
		 *
		 * `docs` is matched as a path segment rather than root-relative on purpose: the exclusion should
		 * hold wherever a docs directory appears, unlike `README.md`, whose basename match was the bug
		 * described below.
		 */
		$excluded_dirs = array( '.claude', '.git', '.github', '.codex', '.agents', '.phpunit.cache', 'bin', 'build', 'dist', 'docs', 'node_modules', 'release', 'reports', 'tests', 'vendor' );
		if ( array_intersect( $parts, $excluded_dirs ) ) {
			return true;
		}

		/*
		 * Matched root-relative, not by basename.
		 *
		 * `basename()` made the `README.md` entry exclude every README at any depth, so a generated plugin
		 * shipped `schema/composer.json`, `model.schema.json`, `model-enums.php` and `model.d.ts` — the
		 * extractable `saltus/model-schema` package — with its documentation stripped out. The `readme.txt`
		 * entry had the same shape and was harmless only because exactly one such file exists.
		 *
		 * Both root files are excluded because their prose describes *this* demo — its model list, its
		 * rebrand tool, its changelog. Copying them would hand every rebranded plugin a readme titled
		 * "Saltus Framework Demo" documenting features it may not have.
		 */
		$excluded_paths = array(
			'.gitignore',
			'.phpunit.result.cache',
			'composer.lock',
			// Internal handoff notes for whoever picks this repo up next; describes in-progress work.
			'HANDOFF.md',
			'package.json',
			'package-lock.json',
			'phpcs.xml',
			'phpunit.xml.dist',
			'postcss.config.js',
			'README.md',
			'readme.txt',
		);

		if ( in_array( $normalized, $excluded_paths, true ) ) {
			return true;
		}

		$basename = basename( $normalized );

		// These two are junk wherever they appear, so they stay basename-matched deliberately.
		if ( in_array( $basename, array( '.DS_Store', '.phpunit.result.cache' ), true ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(zip|log|map|cache)$/', $basename );
	}

	private function is_text_file( string $relative_path ): bool {
		return (bool) preg_match( '/\.(php|json|md|txt|xml|css|js|pot)$/', $relative_path );
	}

	/**
	 * Apply the identity rewrites to one file's contents.
	 *
	 * @param string         $contents      File contents.
	 * @param PluginIdentity $identity      Target identity.
	 * @param string         $relative_path Path within the source tree, '' for the main file.
	 */
	private function rewrite_contents( string $contents, PluginIdentity $identity, string $relative_path = '' ): string {
		$replacements = array(
			self::ORIGINAL_NAMESPACE_SEGMENT => $identity->namespace_segment,
			self::ORIGINAL_PACKAGE           => $this->package_name( $identity ),
			self::ORIGINAL_MAIN_FILE         => $identity->main_file,
			self::ORIGINAL_PREFIX_UPPER      => strtoupper( $identity->prefix ),
			self::ORIGINAL_PREFIX            => $identity->prefix,
			self::ORIGINAL_SLUG              => $identity->plugin_slug,
		);

		/*
		 * The author/homepage rewrites are scoped to the plugin's *own* root manifest.
		 *
		 * Unscoped, they ran on every text file in the tree: a built ZIP had Guzzle's `composer.json`
		 * rewritten, and `schema/composer.json` — the extractable `saltus/model-schema` package — got the
		 * user's name against the Saltus email address. Neither file describes the generated plugin, so
		 * neither should carry its author.
		 */
		if ( $this->is_root_manifest( $relative_path ) ) {
			$author = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', $identity->author ); // strip control chars incl. raw newlines
			$author = substr( $author, 0, 255 ); // sane length cap

			$replacements['"name": "Saltus"']                  = '"name": ' . \wp_json_encode( $author, JSON_UNESCAPED_UNICODE );
			$replacements['"homepage": "https://saltus.dev/"'] = '"homepage": "' . esc_url_raw( $identity->author_uri ) . '"';
			$replacements['"homepage": "https://saltus.dev"']  = '"homepage": "' . esc_url_raw( $identity->author_uri ) . '"';
		}

		$contents = strtr( $contents, $replacements );

		return $this->rewrite_composer_autoloader_classes( $contents, $identity );
	}

	/**
	 * Whether a path is the plugin's own root `composer.json`.
	 *
	 * A `basename()` test was the original check, which matched every vendored manifest in the tree and,
	 * once `schema/composer.json` existed, a second non-vendor one as well.
	 *
	 * @param string $relative_path Path within the source tree.
	 */
	private function is_root_manifest( string $relative_path ): bool {
		return str_replace( '\\', '/', $relative_path ) === 'composer.json';
	}

	private function rewrite_composer_autoloader_classes( string $contents, PluginIdentity $identity ): string {
		$suffix = substr( md5( $identity->plugin_slug . '|' . $identity->namespace_segment . '|' . $identity->prefix ), 0, 32 );

		$contents = preg_replace( '/ComposerAutoloaderInit[a-f0-9]{32}/', 'ComposerAutoloaderInit' . $suffix, $contents ) ?? $contents;
		$contents = preg_replace( '/ComposerStaticInit[a-f0-9]{32}/', 'ComposerStaticInit' . $suffix, $contents ) ?? $contents;
		$contents = preg_replace( '/composerRequire[a-f0-9]{32}/', 'composerRequire' . $suffix, $contents ) ?? $contents;

		return $contents;
	}

	/**
	 * Rewrite the generated plugin's main file.
	 *
	 * The `PLUGIN_VERSION` constant is matched by pattern rather than against a hardcoded source
	 * version. An `ORIGINAL_VERSION` constant has to be bumped in lockstep with the plugin header,
	 * `bin/bump.php` does not touch it, and it had already drifted twice (last resynced in
	 * 6be3384) — leaving rebranded plugins with the source version silently baked in while their
	 * header advertised the user's.
	 *
	 * The pattern is anchored to the `define()` statement, and deliberately so. Matching the bare
	 * `PLUGIN_VERSION', '…'` fragment anywhere in the file let *user input* capture the single
	 * replacement: a `plugin_name` of `Acme PLUGIN_VERSION', 'x` is spliced into the header comment
	 * above, and because `[^']*` crosses newlines the match ran from the header line down to the next
	 * quote — swallowing `@wordpress-plugin` and `Plugin Name:` (so WordPress no longer recognised the
	 * file as a plugin at all) *and* consuming the `limit=1` replacement, leaving the real constant at
	 * the demo's version.
	 *
	 * Two things prevent that now. Requiring a literal `define(` in front means a header comment cannot
	 * match, and `[^'\r\n]*` cannot span lines even if something else does. Both constant spellings are
	 * accepted — `define( __NAMESPACE__ . '\PLUGIN_VERSION', … )` as this plugin writes it, and the bare
	 * `define( 'PLUGIN_VERSION', … )` — so the rewrite does not depend on which form the source uses.
	 * `bin/bump.php:22` reads the same constant; keep the two in step.
	 */
	private function rewrite_main_file_contents( string $contents, PluginIdentity $identity ): string {
		// Read the carried fields before the header is replaced — afterwards they are gone.
		$carried  = $this->carried_header_fields( $contents );
		$contents = $this->replace_plugin_header( $contents, $identity, $carried );
		$contents = preg_replace(
			"/(define\(\s*(?:__NAMESPACE__\s*\.\s*)?'\\\\?PLUGIN_VERSION'\s*,\s*')[^'\r\n]*(')/",
			'${1}' . $this->escape_replacement( (string) preg_replace( '/[^0-9A-Za-z.\-+]/', '', $identity->version ) ) . '${2}',
			$contents,
			1
		) ?? $contents;

		$contents = $this->apply_studio_choice( $contents, $identity );

		return $this->rewrite_contents( $contents, $identity );
	}

	/**
	 * Disable Studio in the generated plugin unless the user opted in.
	 *
	 * Studio's classes ship either way — excluding `src/Plugin/Studio/` would leave `Core` referencing
	 * missing classes, and a half-removed feature is worse than a disabled one. Instead the generated
	 * main file defines the disable constant, which `Core::set_studio()` checks before registering any
	 * route. The constant is emitted rather than the filter because it cannot be overridden from
	 * plugin space, and because it is visible in the file a developer opens first.
	 *
	 * `rewrite_contents()` runs afterwards and rewrites `FRAMEWORK_DEMO` to the new prefix, so the
	 * emitted name matches whatever `Core` will look for.
	 */
	private function apply_studio_choice( string $contents, PluginIdentity $identity ): string {
		if ( $identity->include_studio ) {
			return $contents;
		}

		$block = "/*\n"
			. " * Saltus Studio — the model-authoring REST surface — is disabled in this plugin.\n"
			. " *\n"
			. " * Studio can write PHP into src/models/, which the framework loads on every request.\n"
			. " * That is the point of the demo it was generated from, and rarely what a production\n"
			. " * plugin wants. Delete this constant to enable it, or delete src/Plugin/Studio/ and the\n"
			. " * Core::set_studio() call to remove it entirely.\n"
			. " */\n"
			. "if ( ! defined( 'FRAMEWORK_DEMO_DISABLE_STUDIO' ) ) {\n"
			. "\tdefine( 'FRAMEWORK_DEMO_DISABLE_STUDIO', true );\n"
			. "}\n\n";

		// The block is literal text today, but it is spliced into three `preg_replace()` replacements
		// below, so it goes through the same escaping as the user-supplied fields. Otherwise adding a
		// `$` to this comment later would silently corrupt every generated plugin.
		$block = $this->escape_replacement( $block );

		// Anchor on the first activation hook, which follows the constants block in the main file.
		$anchored = preg_replace( '/^register_activation_hook\(/m', $block . 'register_activation_hook(', $contents, 1 );

		if ( $anchored !== null && $anchored !== $contents ) {
			return $anchored;
		}

		// Fall back to the end of the constants block, then to just after the namespace declaration.
		$after_constants = preg_replace(
			"/(define\(\s*__NAMESPACE__\s*\.\s*'\\\\PLUGIN_MINIMUM_PHP'[^;]*;\s*\}\s*\n)/",
			'${1}' . "\n" . $block,
			$contents,
			1
		);

		if ( $after_constants !== null && $after_constants !== $contents ) {
			return $after_constants;
		}

		return preg_replace( '/^(namespace\s+[^;]+;\s*\n)/m', '${1}' . "\n" . $block, $contents, 1 ) ?? $contents;
	}

	/**
	 * Read the header fields the generated plugin inherits from this one.
	 *
	 * Core's `$default_headers` map parses `Requires at least` and `Requires PHP`; `Tested up to` is
	 * deliberately absent, since it is not a plugin-header field at all — it lives in `readme.txt`, which
	 * is where WP.org reads it from.
	 *
	 * @param string $contents Source main file.
	 * @return array<string, string> Field name => value, omitting any the source does not declare.
	 */
	private function carried_header_fields( string $contents ): array {
		$found = array();

		foreach ( self::CARRIED_HEADER_FIELDS as $field ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', $contents, $matches ) === 1 ) {
				$value = trim( $matches[1] );

				if ( $value !== '' ) {
					$found[ $field ] = $value;
				}
			}
		}

		return $found;
	}

	/**
	 * @param array<string, string> $carried Header fields read from the source main file.
	 */
	private function replace_plugin_header( string $contents, PluginIdentity $identity, array $carried = array() ): string {
		$header = $this->escape_replacement( $this->plugin_header( $identity, $carried ) );
		$result = preg_replace( '/\/\*\*[\s\S]*?@wordpress-plugin[\s\S]*?\*\/\s*/', $header, $contents, 1 );

		if ( $result !== null && $result !== $contents ) {
			return $result;
		}

		return preg_replace( '/^<\?php\s*/', "<?php\n" . $header, $contents, 1 ) ?? $contents;
	}

	/**
	 * Neutralise backreference syntax in a `preg_replace()` replacement string.
	 *
	 * `preg_replace()` expands `$0`, `$1`, `${1}` and `\1` inside its *replacement* argument. Every
	 * identity field reaches one, and `sanitize_text_field()` strips neither `$` nor `\`, so a
	 * description of `Free forever, $0` spliced the demo's entire header inside the new one and produced
	 * an unparseable plugin, while `Only $5 per site` silently became `Only  per site`. A `$0` in
	 * `plugin_name` or `author` white-screened the generated plugin on activation.
	 *
	 * Escaping the replacement is preferred over `preg_replace_callback()` here because the same helper
	 * covers all four call sites uniformly, including the ones splicing a literal block.
	 *
	 * @param string $replacement Literal text to substitute in.
	 * @return string Text safe to pass as a replacement.
	 */
	private function escape_replacement( string $replacement ): string {
		return str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $replacement );
	}

	/**
	 * Build the generated plugin's file header.
	 *
	 * Keep the field list in step with `framework-demo.php`. This method *replaces* the source
	 * header wholesale rather than editing it, so any field missing here is missing from every
	 * rebranded plugin no matter what the demo declares.
	 */
	private function plugin_header( PluginIdentity $identity, array $carried = array() ): string {
		$requires = '';

		foreach ( self::CARRIED_HEADER_FIELDS as $field ) {
			if ( isset( $carried[ $field ] ) ) {
				// Width 19 keeps the values in the same column as every other field in the block
				// ("Plugin Name:" + 7 spaces), which is also what the previous hardcoded lines emitted.
				$requires .= ' * ' . str_pad( $field . ':', 19 ) . $carried[ $field ] . "\n";
			}
		}

		return "/**\n"
			. " * {$identity->plugin_name}\n"
			. " *\n"
			. " * @wordpress-plugin\n"
			. " * Plugin Name:       {$identity->plugin_name}\n"
			. " * Plugin URI:        {$identity->plugin_uri}\n"
			. " * Description:       {$identity->description}\n"
			. " * Version:           {$identity->version}\n"
			. $requires
			. " * Author:            {$identity->author}\n"
			. " * Author URI:        {$identity->author_uri}\n"
			. " * License:           GPL-2.0-or-later\n"
			. " * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt\n"
			. " * Text Domain:       {$identity->text_domain}\n"
			. " * Domain Path:       /languages\n"
			. " */\n\n";
	}

	private function package_name( PluginIdentity $identity ): string {
		$vendor = trim( strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', $identity->author ) ), '-' );

		if ( $vendor === '' ) {
			$vendor = 'local';
		}

		return $vendor . '/' . $identity->plugin_slug;
	}

	private function add_saltus_contributor( string $contents ): string {
		$data = json_decode( $contents, true );
		if ( is_array( $data ) && isset( $data['authors'] ) && is_array( $data['authors'] ) ) {
			$data['authors'][] = array(
				'name'     => 'Saltus',
				'email'    => 'web@saltus.dev',
				'homepage' => 'https://saltus.dev',
			);

			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if ( $json !== false ) {
				return $json;
			}
		}
		return $contents;
	}

	private function delete_file( string $path ): void {
		if ( is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
}
