<?php
/**
 * Event Category — the taxonomy backing `event`'s column and filter demos.
 *
 * Lives in its own file because the `Modeler` cannot mix a single model and a nested one:
 * `Modeler::is_multiple()` decides how to parse a file by inspecting only its *first* element
 * (`is_array( current( $config->all() ) )`). A file whose first key is `'type' => 'cpt'` is treated
 * as one model, so any additional model appended alongside it is silently dropped. Either every
 * top-level element is a model array (see `taxonomy-multiple.php`) or the file declares exactly one.
 *
 * Gated by the same toggle as `event`, since it exists only to serve that model's demos.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'event' ) ) {
	return array();
}

return array(
	'type'         => 'category',
	'name'         => 'event_category',
	'labels'       => array(
		'has_one'  => 'Event Category',
		'has_many' => 'Event Categories',
	),
	'options'      => array(
		'show_in_rest' => true,
	),
	'associations' => array( 'event' ),
);
