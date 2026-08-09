<?php
/**
 * Admin page hosting the Saltus Studio app.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Studio;

use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;

/**
 * Registers the Studio screen and hands the React app its boot data.
 *
 * The page itself is a mount point and nothing more. Everything the app needs to know about the
 * framework — accepted `type` aliases, the 44 field types, registration name ceilings, reserved names —
 * is read from `schema/model-enums.php`, which `bin/generate-model-schema.php` derives from the
 * framework's own source.
 *
 * That indirection is the point. A TypeScript copy of those enums would be a second source of truth,
 * and the whole epic exists because config drifting from the code it describes fails silently. Passing
 * generated values at runtime means a framework update that adds a field type cannot leave Studio
 * offering one the framework has stopped shipping.
 */
class StudioPage {

	private const PAGE_SLUG = 'framework-demo-studio';

	private const SCRIPT_HANDLE = 'framework-demo-studio';

	/**
	 * Where the compiled app lives, relative to the plugin root.
	 *
	 * Under `assets/`, not `build/`. `build/` is the legacy Grunt directory and is excluded from *both*
	 * the release ZIP (`bin/PackageBuilder.php`) and rebranded plugins (`PluginRenamer`), so a compiled
	 * app placed there would never reach a user — the page would render its "not built" notice forever.
	 * Verified against a real ZIP: `assets/` ships, `build/` does not.
	 *
	 * @var string
	 */
	private const BUILD_RELATIVE_PATH = 'assets/studio/saltus-studio.js';

	private Core $core;

	/**
	 * @param Core $core Plugin core.
	 */
	public function __construct( Core $core ) {
		$this->core = $core;
	}

	/**
	 * Hook the page and its assets.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Menu parent for the Studio screen.
	 *
	 * `edit.php?post_type=book`, not `framework-demo-settings`.
	 *
	 * The settings page is itself a Codestar *submenu* of this same parent — `post-type-all.php` declares
	 * it without `menu_type`, and `CodestarSettings::create_settings_page()` defaults `menu_type` to
	 * `submenu` and `menu_parent` to `edit.php?post_type={name}`. Parenting to a submenu slug populated
	 * `$submenu['framework-demo-settings']` and rendered nothing: `wp-admin/menu-header.php` only walks
	 * submenus of entries that exist in `$menu`. The page still resolved at
	 * `admin.php?page=framework-demo-studio`, which is why manual testing never caught it.
	 */
	private const MENU_PARENT = 'edit.php?post_type=book';

	/**
	 * Add the Studio submenu beside the plugin's settings page.
	 */
	public function add_page(): void {
		add_submenu_page(
			self::MENU_PARENT,
			__( 'Model Studio', 'framework-demo' ),
			__( 'Model Studio', 'framework-demo' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue the built app, but only on its own screen.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( string $hook_suffix = '' ): void {
		if ( ! str_contains( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		$build = $this->build_path();

		if ( ! is_file( $build ) ) {
			return;
		}

		/*
		 * `react` and `react-dom` are declared dependencies rather than bundled: the app externalises
		 * them onto the globals WordPress already registers. `wp-components` brings its stylesheet in
		 * via `wp-components` style handle, so the controls look native without shipping any CSS.
		 */
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( self::BUILD_RELATIVE_PATH, $this->core->get_file_path() ),
			array( 'react', 'react-dom', 'wp-components', 'wp-i18n' ),
			(string) filemtime( $build ),
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_localize_script( self::SCRIPT_HANDLE, 'saltusStudioBoot', $this->boot_data() );

		// So JS strings can be translated once a .json language file exists for the handle.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::SCRIPT_HANDLE, 'framework-demo' );
		}
	}

	/**
	 * Assemble the boot payload from the generated enum map.
	 *
	 * @return array<string, mixed>
	 */
	private function boot_data(): array {
		$enums = $this->enums();

		return array(
			'restRoot'           => esc_url_raw( rest_url() ),
			'restNamespace'      => 'framework-demo/v1',
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'typeAliases'        => $enums['type_aliases'] ?? array(),
			'fieldTypes'         => $enums['field_types'] ?? array(),
			'maxNameLength'      => $enums['max_name_length'] ?? array(),
			'reservedPostTypes'  => $enums['reserved_post_types'] ?? array(),
			'reservedTaxonomies' => $enums['reserved_taxonomies'] ?? array(),
			'schemaVersion'      => $enums['schema_version'] ?? 'unknown',
			// Surfaced so the UI can disable saving up front rather than after a failed request.
			'canWrite'           => ! $this->writes_disallowed(),
		);
	}

	/**
	 * Absolute path to the compiled app.
	 */
	private function build_path(): string {
		return $this->core->get_dir_path() . self::BUILD_RELATIVE_PATH;
	}

	/**
	 * Load the generated enum map.
	 *
	 * Resolved through `Schema` rather than a literal path, so publishing `saltus/model-schema` as a
	 * package needs no change here.
	 *
	 * @return array<string, mixed>
	 */
	private function enums(): array {
		return ( new Schema( $this->core->get_dir_path() ) )->enums();
	}

	/**
	 * Whether this install forbids writing plugin code.
	 */
	private function writes_disallowed(): bool {
		foreach ( array( 'DISALLOW_FILE_MODS', 'DISALLOW_FILE_EDIT' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the mount point.
	 *
	 * Deliberately minimal: a heading for screen readers and page context, then the div the app takes
	 * over. The no-JS message is real content rather than a spinner, because a broken enqueue should say
	 * so instead of showing an empty page forever.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'framework-demo' ) );
		}

		$build = $this->build_path();
		?>
		<div class="wrap">
			<div id="saltus-studio-root">
				<?php if ( ! is_file( $build ) ) : ?>
					<h1><?php esc_html_e( 'Model Studio', 'framework-demo' ); ?></h1>
					<div class="notice notice-warning">
						<p>
							<?php esc_html_e( 'The Studio app has not been built. Build it from the Omens UI repository and copy the output into assets/studio/ in this plugin.', 'framework-demo' ); ?>
						</p>
						<p>
							<code>npm run build -w @omens-ui/saltus-studio</code>
						</p>
					</div>
					<p>
						<?php esc_html_e( 'The command-line tools work without it: run composer lint:models to validate every model file.', 'framework-demo' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
