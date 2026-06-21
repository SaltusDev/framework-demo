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

	private const PAGE_SLUG    = 'framework-demo-renamer';
	private const NONCE_ACTION = 'framework_demo_generate_plugin';
	private const NONCE_NAME   = 'framework_demo_renamer_nonce';

	private Core $core;

	public function __construct( Core $core ) {
		$this->core = $core;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_post_framework_demo_generate_plugin', array( $this, 'generate_plugin' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			'framework-demo-settings',
			__( 'Rename Plugin', 'framework-demo' ),
			__( 'Rename Plugin', 'framework-demo' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'framework-demo' ) );
		}

		$defaults = PluginIdentity::defaults();
		$error    = isset( $_GET['framework_demo_error'] ) && is_string( $_GET['framework_demo_error'] ) ? sanitize_text_field( wp_unslash( $_GET['framework_demo_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap framework-demo-renamer">
			<h1><?php esc_html_e( 'Rename Plugin', 'framework-demo' ); ?></h1>
			<p><?php esc_html_e( 'Generate a renamed copy of this demo plugin as a ZIP file. The installed demo plugin is not changed.', 'framework-demo' ); ?></p>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="framework_demo_generate_plugin">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<table class="form-table" role="presentation">
					<?php $this->render_text_field( 'plugin_name', __( 'Plugin Name', 'framework-demo' ), $defaults['plugin_name'], true ); ?>
					<?php $this->render_text_field( 'plugin_slug', __( 'Plugin Slug', 'framework-demo' ), $defaults['plugin_slug'], true ); ?>
					<?php $this->render_text_field( 'main_file', __( 'Main Plugin File', 'framework-demo' ), $defaults['main_file'], true ); ?>
					<?php $this->render_text_field( 'namespace_segment', __( 'Namespace Segment', 'framework-demo' ), $defaults['namespace_segment'], true ); ?>
					<?php $this->render_text_field( 'text_domain', __( 'Text Domain', 'framework-demo' ), $defaults['text_domain'], true ); ?>
					<?php $this->render_text_field( 'description', __( 'Description', 'framework-demo' ), $defaults['description'], true ); ?>
					<?php $this->render_text_field( 'author', __( 'Author', 'framework-demo' ), $defaults['author'], true ); ?>
					<?php $this->render_text_field( 'author_uri', __( 'Author URI', 'framework-demo' ), $defaults['author_uri'], false, 'url' ); ?>
					<?php $this->render_text_field( 'plugin_uri', __( 'Plugin URI', 'framework-demo' ), $defaults['plugin_uri'], false, 'url' ); ?>
					<?php $this->render_text_field( 'version', __( 'Version', 'framework-demo' ), $defaults['version'], true ); ?>
					<?php $this->render_text_field( 'prefix', __( 'Code Prefix', 'framework-demo' ), $defaults['prefix'], true ); ?>
				</table>

				<?php submit_button( __( 'Download Renamed Plugin ZIP', 'framework-demo' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function generate_plugin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate plugins.', 'framework-demo' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		try {
			$post_data = wp_unslash( $_POST );
			$identity  = PluginIdentity::from_request( $post_data );
			$renamer   = new PluginRenamer( $this->core->get_dir_path() );
			$renamer->stream_zip( $identity );
		} catch ( ValidationException $exception ) {
			$this->redirect_with_error( $exception->getMessage() );
		} catch ( \Throwable $exception ) {
			$this->redirect_with_error(
				sprintf(
					/* translators: %s: error message. */
					__( 'Could not generate the plugin ZIP: %s', 'framework-demo' ),
					$exception->getMessage()
				)
			);
		}
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
					<?php required( $required ); ?>
				>
			</td>
		</tr>
		<?php
	}

	private function redirect_with_error( string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => self::PAGE_SLUG,
					'framework_demo_error' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
