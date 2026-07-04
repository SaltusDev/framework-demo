<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Admin;

use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Core;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginIdentity;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\PluginRenamer;
use Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin\Renamer\ValidationException;

/**
 * Admin UI for generating renamed plugin ZIP files.
 */
class RenamerPage {

	private const PAGE_SLUG       = 'framework-demo-renamer';
	private const TOOLS_PAGE_SLUG = 'framework-demo-renamer-tools';
	private const NONCE_ACTION    = 'framework_demo_generate_plugin';
	private const NONCE_NAME      = 'framework_demo_renamer_nonce';
	private const NOTICE_ACTION   = 'framework_demo_dismiss_rebrand_notice';
	private const NOTICE_NONCE    = 'framework_demo_rebrand_notice_nonce';

	private Core $core;

	public function __construct( Core $core ) {
		$this->core = $core;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_activation_notice' ) );
		add_action( 'admin_post_framework_demo_generate_plugin', array( $this, 'generate_plugin' ) );
		add_action( 'admin_post_framework_demo_dismiss_rebrand_notice', array( $this, 'dismiss_activation_notice' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->core->get_file_path() ),
			array( $this, 'add_plugin_action_link' )
		);
	}

	public function add_page(): void {
		add_submenu_page(
			'framework-demo-settings',
			__( 'Rebrand This Demo', 'framework-demo' ),
			__( 'Rebrand This Demo', 'framework-demo' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		add_management_page(
			__( 'Rebrand This Demo', 'framework-demo' ),
			__( 'Rebrand This Demo', 'framework-demo' ),
			'manage_options',
			self::TOOLS_PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a shortcut to the plugin row quick actions.
	 *
	 * @param array<string,string> $links Existing plugin action links.
	 * @return array<string,string>
	 */
	public function add_plugin_action_link( array $links ): array {
		$tool_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->tool_url() ),
			esc_html__( 'Rebrand This Demo', 'framework-demo' )
		);

		return array_merge( array( 'rename_plugin' => $tool_link ), $links );
	}

	public function render_activation_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( 'framework_demo_rebrand_notice_pending', false ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => self::NOTICE_ACTION ),
				admin_url( 'admin-post.php' )
			),
			self::NOTICE_ACTION,
			self::NOTICE_NONCE
		);
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Ready to make this demo yours?', 'framework-demo' ); ?></strong>
				<?php esc_html_e( 'Use Rebrand This Demo to generate your own installable plugin copy.', 'framework-demo' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->tool_url() ); ?>">
					<?php esc_html_e( 'Open Rebrand This Demo', 'framework-demo' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $dismiss_url ); ?>">
					<?php esc_html_e( 'Dismiss', 'framework-demo' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function dismiss_activation_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'framework-demo' ) );
		}

		check_admin_referer( self::NOTICE_ACTION, self::NOTICE_NONCE );
		update_option( 'framework_demo_rebrand_notice_dismissed', '1', false );
		delete_option( 'framework_demo_rebrand_notice_pending' );

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	// phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'framework-demo' ) );
		}

		$values          = PluginIdentity::defaults();
		$error_key       = sanitize_text_field( $_GET['framework_demo_error'] ?? '' );
		$success_key     = sanitize_text_field( $_GET['framework_demo_success'] ?? '' );
		$error_message   = '';
		$success_message = '';

		if (
			$error_key !== ''
			&& isset( $_GET['framework_demo_nonce'] )
			&& wp_verify_nonce( $_GET['framework_demo_nonce'], 'framework_demo_error_redirect' )
		) {
			$error = get_transient( 'framework_demo_error_' . $error_key );
			delete_transient( 'framework_demo_error_' . $error_key );

			if ( is_array( $error ) ) {
				$error_message = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : '';
				$form_values   = isset( $error['values'] ) && is_array( $error['values'] ) ? $error['values'] : array();
				$values        = array_merge( $values, array_intersect_key( $form_values, $values ) );
			} elseif ( is_string( $error ) ) {
				$error_message = $error;
			}
		}

		if ( $success_key !== '' ) {
			$success_message = get_transient( 'framework_demo_success_' . $success_key );
			delete_transient( 'framework_demo_success_' . $success_key );
			$success_message = is_string( $success_message ) ? $success_message : '';
		}
		?>
		<div class="wrap framework-demo-renamer">
			<h1><?php esc_html_e( 'Rebrand This Demo', 'framework-demo' ); ?></h1>
			<p><?php esc_html_e( 'Make it yours. Generate a customized copy of this demo plugin as a ZIP file, or copy and activate it directly.', 'framework-demo' ); ?></p>

			<?php if ( $error_message !== '' ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html( $error_message ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $success_message !== '' ) : ?>
				<div class="notice notice-success">
					<p><?php echo esc_html( $success_message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="framework-demo-renamer__layout">
				<form class="framework-demo-renamer__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="framework_demo_generate_plugin">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<table class="form-table" role="presentation">
						<?php $this->render_text_field( 'plugin_name', __( 'Plugin Name', 'framework-demo' ), $values['plugin_name'], true ); ?>
						<?php $this->render_text_field( 'plugin_slug', __( 'Plugin Slug', 'framework-demo' ), $values['plugin_slug'], true ); ?>
						<?php $this->render_text_field( 'main_file', __( 'Main Plugin File', 'framework-demo' ), $values['main_file'], true ); ?>
						<?php $this->render_text_field( 'namespace_segment', __( 'Namespace Segment', 'framework-demo' ), $values['namespace_segment'], true ); ?>
						<?php $this->render_text_field( 'description', __( 'Description', 'framework-demo' ), $values['description'], true ); ?>
						<?php $this->render_text_field( 'author', __( 'Author', 'framework-demo' ), $values['author'], true ); ?>
						<?php $this->render_text_field( 'author_uri', __( 'Author URI', 'framework-demo' ), $values['author_uri'], false, 'url' ); ?>
						<?php $this->render_text_field( 'plugin_uri', __( 'Plugin URI', 'framework-demo' ), $values['plugin_uri'], false, 'url' ); ?>
						<?php $this->render_text_field( 'version', __( 'Version', 'framework-demo' ), $values['version'], true ); ?>
						<?php $this->render_text_field( 'prefix', __( 'Code Prefix', 'framework-demo' ), $values['prefix'], true ); ?>
						<?php $this->render_checkbox_field( 'saltus_contributor', __( 'Credit Saltus as contributor', 'framework-demo' ), __( 'Include Saltus as a co-author in the generated plugin\'s composer.json', 'framework-demo' ) ); ?>
					</table>

					<p class="submit">
						<button class="button button-primary" type="submit" name="framework_demo_delivery" value="download">
							<?php esc_html_e( 'Download Rebranded Plugin ZIP', 'framework-demo' ); ?>
						</button>
						<button class="button" type="submit" name="framework_demo_delivery" value="activate">
							<?php esc_html_e( 'Copy & Activate Plugin', 'framework-demo' ); ?>
						</button>
					</p>
				</form>

				<?php $this->render_guide(); ?>
			</div>
		</div>
		<?php
	}

	public function generate_plugin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate plugins.', 'framework-demo' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$post_data = wp_unslash( $_POST );

		try {
			$identity = PluginIdentity::from_request( $post_data );
			$delivery = isset( $post_data['framework_demo_delivery'] ) && ! is_array( $post_data['framework_demo_delivery'] )
				? sanitize_key( (string) $post_data['framework_demo_delivery'] )
				: 'download';
			$renamer  = new PluginRenamer( $this->core->get_dir_path() );

			if ( $delivery === 'activate' ) {
				$this->copy_and_activate_plugin( $renamer, $identity );
			}

			$renamer->stream_zip( $identity );
		} catch ( ValidationException $exception ) {
			$this->redirect_with_error( $exception->getMessage(), $post_data );
		} catch ( \Throwable $exception ) {
			$this->redirect_with_error(
				sprintf(
					/* translators: %s: error message. */
					__( 'Could not generate the plugin ZIP: %s', 'framework-demo' ),
					$exception->getMessage()
				),
				$post_data
			);
		}
	}

	private function copy_and_activate_plugin( PluginRenamer $renamer, PluginIdentity $identity ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			throw new \RuntimeException( __( 'You do not have permission to activate plugins.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			throw new \RuntimeException( __( 'The WordPress plugin directory could not be found.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! wp_is_writable( WP_PLUGIN_DIR ) ) {
			throw new \RuntimeException( __( 'The WordPress plugin directory is not writable.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $identity->plugin_slug;
		if ( file_exists( $plugin_dir ) ) {
			throw new ValidationException( __( 'A plugin folder with that slug already exists. Choose a different slug or remove the existing plugin first.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$tmp_file = wp_tempnam( $identity->plugin_slug . '.zip' );
		if ( ! $tmp_file ) {
			throw new \RuntimeException( __( 'Could not create a temporary ZIP file.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		try {
			$renamer->build_zip( $identity, $tmp_file );
			$this->extract_zip_to_plugins_dir( $tmp_file, $plugin_dir );
			$this->activate_generated_plugin( $identity );
			$this->deactivate_source_plugin();
		} catch ( \Throwable $exception ) {
			$this->remove_path( $plugin_dir );
			throw $exception;
		} finally {
			$this->delete_file( $tmp_file );
		}

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	private function extract_zip_to_plugins_dir( string $zip_path, string $plugin_dir ): void {
		$zip = new \ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			throw new \RuntimeException( __( 'Could not open the generated ZIP file.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$extracted = $zip->extractTo( WP_PLUGIN_DIR );
		$zip->close();

		if ( ! $extracted || ! is_dir( $plugin_dir ) ) {
			throw new \RuntimeException( __( 'Could not copy the generated plugin into the plugins directory.', 'framework-demo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	private function activate_generated_plugin( PluginIdentity $identity ): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = activate_plugin( $identity->plugin_slug . '/' . $identity->main_file );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	private function deactivate_source_plugin(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( plugin_basename( $this->core->get_file_path() ) );
	}

	private function render_text_field( string $id, string $label, string $value, bool $required, string $type = 'text' ): void {
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input
					name="<?php echo esc_attr( $id ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					type="<?php echo esc_attr( $type ); ?>"
					class="regular-text"
					value="<?php echo esc_attr( $value ); ?>"
					<?php
					if ( $required ) :
						?>
						required<?php endif; ?>
				>
			</td>
		</tr>
		<?php
	}

	private function render_checkbox_field( string $id, string $label, string $description ): void {
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<fieldset>
					<label for="<?php echo esc_attr( $id ); ?>">
						<input
							name="<?php echo esc_attr( $id ); ?>"
							id="<?php echo esc_attr( $id ); ?>"
							type="checkbox"
							value="1"
						>
						<?php echo esc_html( $description ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	private function render_guide(): void {
		?>
		<aside class="framework-demo-renamer__guide">
			<h2><?php esc_html_e( 'How to rebrand this demo', 'framework-demo' ); ?></h2>

			<h3><?php esc_html_e( '1. Fill in your plugin identity', 'framework-demo' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Use the public plugin name users should see in WordPress.', 'framework-demo' ); ?></li>
				<li><?php esc_html_e( 'Choose a lowercase slug, then match the main file to it, such as my-plugin and my-plugin.php.', 'framework-demo' ); ?></li>
				<li><?php esc_html_e( 'Use a PHP-safe namespace segment like MyPlugin and a lowercase code prefix like my_plugin.', 'framework-demo' ); ?></li>
				<li><?php esc_html_e( 'Add the description, author details, URLs, and semver version for the first release.', 'framework-demo' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '2. Choose an install path', 'framework-demo' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Download the ZIP, then upload it from Plugins > Add New > Upload Plugin and activate it.', 'framework-demo' ); ?></li>
				<li><?php esc_html_e( 'Or use Copy & Activate Plugin to install it directly on this site when the slug is not already in use.', 'framework-demo' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '3. Continue from your new plugin', 'framework-demo' ); ?></h3>
			<p><?php esc_html_e( 'The generated plugin is a separate copy. When you use Copy & Activate Plugin, this demo deactivates itself after the new plugin activates successfully.', 'framework-demo' ); ?></p>
		</aside>
		<?php
	}

	private function tool_url(): string {
		return add_query_arg(
			array( 'page' => self::TOOLS_PAGE_SLUG ),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * @param array<string,mixed> $request Raw request values.
	 * @return array<string,string>
	 */
	private function form_values_from_request( array $request ): array {
		$values = array();

		foreach ( array_keys( PluginIdentity::defaults() ) as $key ) {
			$value          = isset( $request[ $key ] ) && ! is_array( $request[ $key ] ) ? (string) $request[ $key ] : '';
			$values[ $key ] = sanitize_text_field( $value );
		}

		return $values;
	}

	/**
	 * @param array<string,mixed> $form_data Submitted form values.
	 */
	private function redirect_with_error( string $message, array $form_data = array() ): void {
		$token = wp_generate_uuid4();
		set_transient(
			'framework_demo_error_' . $token,
			array(
				'message' => $message,
				'values'  => $this->form_values_from_request( $form_data ),
			),
			30
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => self::PAGE_SLUG,
					'framework_demo_error' => $token,
					'framework_demo_nonce' => wp_create_nonce( 'framework_demo_error_redirect' ),
				),
				$this->tool_url()
			)
		);
		exit;
	}

	private function delete_file( string $path ): void {
		if ( is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	private function remove_path( string $path ): void {
		if ( ! file_exists( $path ) ) {
			return;
		}

		if ( is_file( $path ) || is_link( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $item->isDir() && ! $item->isLink() ) {
					@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged
					continue;
				}

				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged
			}

			@rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fail silently to avoid masking the primary exception during cleanup.
		}
	}
}
