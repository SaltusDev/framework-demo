<?php
/**
 * Uninstall cleanup for Saltus Framework Demo.
 *
 * Runs only when the user deletes the plugin from the Plugins screen — not on deactivation.
 *
 * ## What this removes
 *
 * The options this plugin writes directly, the two settings-page options the framework writes on its
 * behalf, the per-post-type `saltus_framework_settings_*` rows written by the framework's REST and MCP
 * surface, the rebrand transients, and the framework's MCP audit-cleanup cron event.
 *
 * Every option name deleted here is qualified by either this plugin's slug or one of its own post
 * types — re-checked, because that claim was previously false for `staff-options`, a generic key the
 * renamer left verbatim so that uninstalling a rebranded copy deleted the demo's live settings.
 *
 * ## What this deliberately leaves alone
 *
 * **Post content.** Deleting the plugin unregisters `book`, `movie`, and the opt-in models, but the
 * posts stay in `wp_posts`. Reinstalling brings them back. Destroying user content on uninstall is
 * not this plugin's call to make, and a demo plugin's least of all.
 *
 * **`{prefix}saltus_mcp_audit` and `{prefix}saltus_ai_proposals`.** These names are hardcoded
 * literals in the framework (`AuditLogger::TABLE_SUFFIX`, `ProposalStore::table_name()`), *not*
 * Strauss-prefixed — so every plugin built on the Saltus Framework shares the same two tables.
 * Dropping them here would delete another plugin's audit log and pending editorial reviews. The
 * framework owns that data and must clean it up itself.
 *
 * ## Why this file declares nothing global
 *
 * `delete_plugins()` loops over every selected plugin in one request, and `uninstall_plugin()`
 * `include_once`s each `uninstall.php` by path. This plugin exists to generate rebranded copies of
 * itself, so "delete the demo and my rebrand together" is an ordinary thing to do — and two copies
 * of this file would then run in the same process. Global functions would fatal on redeclaration;
 * `function_exists()` guards would be worse, silently running the *first* plugin's option list for
 * the second plugin. Everything below is therefore local to a closure.
 *
 * For the same reason nothing here reads `WP_UNINSTALL_PLUGIN`'s value: core `define()`s it
 * unguarded, so on the second pass it still names the first plugin.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

// Only ever run as WordPress's uninstall callback.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

(
	static function (): void {

		/*
		 * Options written by this plugin and by the framework's settings pages on its behalf.
		 * `framework-demo-settings` also holds the Demo Models toggles;
		 * `framework-demo-staff-options` belongs to the opt-in `staff` model.
		 *
		 * Every name here contains the plugin slug, which is the property that makes deleting them safe:
		 * the renamer rewrites `framework_demo` / `framework-demo`, so a rebranded plugin cleans up its
		 * own rows and never touches these. The staff settings page was originally declared as the bare
		 * `staff-options`, which the renamer left verbatim — so deleting a rebranded copy destroyed the
		 * demo's still-active staff settings, and any unrelated plugin using that generic key was
		 * collateral damage. Exactly the cross-plugin deletion the shared-table reasoning above avoids.
		 * Keep every entry slug-qualified.
		 */
		$options = array(
			'framework_demo_rebrand_notice_pending',
			'framework_demo_rebrand_notice_dismissed',
			'framework-demo-settings',
			'framework-demo-staff-options',
		);

		/*
		 * Per-post-type settings rows written by the framework's own REST and MCP surface.
		 *
		 * `SettingsManager::option_name()` is `saltus_framework_settings_{post_type}`, written by
		 * `SettingsController` and the `UpdateSettings` MCP tool — a confirmed write path, not a
		 * theoretical one. Unlike the shared audit tables these belong to this plugin, because the
		 * post-type segment is this plugin's own model.
		 *
		 * Enumerated per post type rather than deleted by prefix: a `saltus_framework_settings_%` query
		 * would also match rows belonging to other plugins built on the same framework.
		 */
		foreach ( array( 'book', 'movie', 'recipe', 'event', 'venue', 'staff', 'release', 'artwork', 'snippet', 'internal_note' ) as $saltus_post_type ) {
			$options[] = 'saltus_framework_settings_' . $saltus_post_type;
		}

		/**
		 * Delete the renamer's error/success transients.
		 *
		 * `RenamerPage` stores these under a `wp_generate_uuid4()` suffix with a 30-second TTL, so
		 * the names cannot be known ahead of time. They have almost certainly expired long before
		 * uninstall; this is belt-and-braces for a site whose cron never ran.
		 *
		 * Uses a direct query because no core API deletes transients by prefix. On object-cache
		 * installs transients live outside the options table, so nothing is found — harmless, and
		 * the TTL has already dealt with them.
		 */
		$delete_transients = static function (): void {
			global $wpdb;

			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
				return;
			}

			foreach ( array( 'framework_demo_error_', 'framework_demo_success_' ) as $prefix ) {
				$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No core API deletes transients by prefix; runs once at uninstall.
					$wpdb->prepare(
						"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( '_transient_' . $prefix ) . '%'
					)
				);

				if ( ! is_array( $names ) ) {
					continue;
				}

				foreach ( $names as $name ) {
					// Strip the storage prefix to get the key delete_transient() expects.
					delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
				}
			}
		};

		/**
		 * Remove every trace of this plugin from the current site.
		 */
		$clean_site = static function () use ( $options, $delete_transients ): void {
			foreach ( $options as $option ) {
				delete_option( $option );
			}

			$delete_transients();

			/*
			 * Scheduled by the framework's `MCP::activate()`. Its `deactivate()` clears it too, but
			 * deleting an already-inactive plugin never fires that path, which would leave an
			 * orphaned event pointing at a hook nothing answers.
			 */
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( 'saltus_framework_mcp_audit_cleanup' );
			}
		};

		/*
		 * Options are per-site, so a network uninstall has to visit every site. Capped so a very
		 * large network cannot run away mid-loop: an admin can simply delete again, whereas a fatal
		 * here would abort the uninstall entirely.
		 */
		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields'                 => 'ids',
					'number'                 => 10000,
					'update_site_meta_cache' => false,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				$clean_site();
				restore_current_blog();
			}

			return;
		}

		$clean_site();
	}
)();
