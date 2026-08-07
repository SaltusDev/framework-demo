<?php
/**
 * Venue — frontend without overrides.
 *
 * Demonstrates shortcode + blocks with **no template overrides**, falling through to the
 * framework's own default templates. This is the path a new plugin takes on day one, before
 * writing custom rendering.
 *
 * **BLOCKED:** The framework does not currently ship `templates/{list,single}.php` or
 * `templates/blocks/{list,single}.php`, so both renderers return an empty string. Tracked as
 * ROADMAP Phase 4 task 4. This model is deliberately kept as the regression test for that fix —
 * it is the only model with no overrides, so it's the only one that would catch the absence.
 *
 * **Theme override layer:** Even without plugin templates, dropping `saltus/venue/list.php` into
 * the active theme takes precedence over the framework default. The resolution order is:
 * config `templates` key → theme `saltus/{post_type}/` → framework `templates/`.
 *
 * Enable this model from the "Demo Models" settings section. For custom templates, see `book`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'venue' ) ) {
	return array();
}

return array(
	'type'     => 'cpt',
	'name'     => 'venue',
	'labels'   => array(
		'has_one'  => 'Venue',
		'has_many' => 'Venues',
	),
	'options'  => array(
		'menu_icon' => 'dashicons-location',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	),
	'meta'     => array(
		'venue_info' => array(
			'id'     => 'venue_info',
			'title'  => 'Venue Info',
			'fields' => array(
				'venue_address'  => array(
					'type'  => 'textarea',
					'title' => 'Address',
				),
				'venue_capacity' => array(
					'type'  => 'number',
					'title' => 'Capacity',
				),
				'venue_website'  => array(
					'type'  => 'text',
					'title' => 'Website',
				),
			),
		),
	),
	'frontend' => array(
		'enabled'   => true,
		'shortcode' => true,
		// No 'templates' key — falls through to framework defaults
	),
	'blocks'   => array(
		'enabled' => true,
		// No 'templates' key — falls through to framework defaults
	),
);
