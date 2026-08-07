<?php
/**
 * Shared gate for the opt-in demo models.
 *
 * ## Why this exists instead of the `active` key
 *
 * The framework's `active` config key does not work. `BaseModel::is_disabled()` reads:
 *
 *     if ( empty( $this->data['active'] ) || $this->data['active'] === true ) {
 *         return false; // not disabled
 *     }
 *
 * `empty( false )` is `true`, so `'active' => false` takes the early return and the model loads
 * anyway. Only a truthy-but-not-`true` value (`1`, `'no'`) actually disables a model, which is the
 * opposite of what the key implies. Verified behaviour:
 *
 *     active => true   -> enabled
 *     active => false  -> ENABLED  (bug)
 *     active => 1      -> disabled (bug)
 *
 * Tracked as ROADMAP Phase 4 task 5.
 *
 * The `saltus/framework/models/extra_models` filter is no help either: `Modeler::load()` passes it
 * an empty array purely so plugins can *append* models, so a callback never sees the file-loaded
 * ones and cannot flip a key on them.
 *
 * ## How the gate works
 *
 * `ModelFactory::create()` soft-fails any config without a `type` key, so a model file that returns
 * an empty array is skipped cleanly. Each opt-in model file therefore opens with:
 *
 *     if ( ! saltus_demo_model_enabled( 'recipe' ) ) {
 *         return array();
 *     }
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! function_exists( 'saltus_demo_model_enabled' ) ) {
	/**
	 * Whether an opt-in demo model is enabled in the plugin settings.
	 *
	 * Models are off by default so the demo stays legible with ten CPTs available. Toggles live in
	 * **Books → Settings → Demo Models**, stored in the `framework-demo-settings` option written by
	 * the framework's Codestar settings page.
	 *
	 * @param string $model Model slug, matching the `enable_{slug}` settings field.
	 * @return bool
	 */
	function saltus_demo_model_enabled( string $model ): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$settings = get_option( 'framework-demo-settings', array() );
		if ( ! is_array( $settings ) ) {
			return false;
		}

		return ! empty( $settings[ 'enable_' . $model ] );
	}
}

/*
 * This file only defines the helper — it registers no model, so it returns an empty array for
 * ModelFactory to soft-fail. The leading underscore keeps it first in the Modeler's ascending
 * filename sort, so the helper is defined before any model file calls it.
 */
return array();
