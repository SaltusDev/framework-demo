<?php
/**
 * Venue Type — taxonomy opt-out.
 *
 * Demonstrates a taxonomy with `show_in_rest: false` and a single association, contrasting the
 * genre/writer/country trio (all REST-enabled, some multi-association).
 *
 * Shows that taxonomy defaults (`show_in_rest => true` inferred, hierarchy from `type`) are
 * overridable. This is the taxonomy equivalent of `internal_note` for CPTs.
 *
 * Enable this model from the "Demo Models" settings section. Requires the `venue` CPT also enabled,
 * since this associates to it.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'venue_type' ) ) {
	return array();
}

return array(
	'type'         => 'tag',
	'name'         => 'venue_type',
	'labels'       => array(
		'has_one'  => 'Venue Type',
		'has_many' => 'Venue Types',
	),
	'options'      => array(
		'show_in_rest' => false, // Explicit opt-out
	),
	'associations' => array( 'venue' ),
);
