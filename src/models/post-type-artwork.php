<?php
/**
 * Artwork — serialized meta and REST shape.
 *
 * Demonstrates the two `data_type` modes side by side so you can diff the MCP payloads:
 *
 * - **unserialized** (default): one meta key per field. Paths are flat: `artwork_title`, `artwork_artist`.
 * - **serialized**: one meta key holding a nested object. Paths are dotted: `artwork_info.dimensions.width`.
 *
 * Both metaboxes set `register_rest_api: true`, so the difference appears in `normalized.rest_meta_keys`
 * and in how `MetaFieldProvider::normalize_meta_fields()` builds the `schema` for nested repeaters.
 *
 * This is the subtlest framework behaviour — `book` only shows the default. Enable this model from
 * the "Demo Models" settings section.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'artwork' ) ) {
	return array();
}

return array(
	'type'    => 'cpt',
	'name'    => 'artwork',
	'labels'  => array(
		'has_one'  => 'Artwork',
		'has_many' => 'Artworks',
	),
	'options' => array(
		'menu_icon' => 'dashicons-admin-customizer',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	),
	'meta'    => array(
		// Unserialized (default): one key per field
		'artwork_flat' => array(
			'id'                => 'artwork_flat',
			'title'             => 'Flat Meta (unserialized)',
			'data_type'         => 'unserialize',
			'register_rest_api' => true,
			'fields'            => array(
				'artwork_artist' => array(
					'type'  => 'text',
					'title' => 'Artist',
					'desc'  => 'Stored as top-level meta key: artwork_artist',
				),
				'artwork_year'   => array(
					'type'  => 'number',
					'title' => 'Year',
					'desc'  => 'Stored as top-level meta key: artwork_year',
				),
				'artwork_medium' => array(
					'type'    => 'select',
					'title'   => 'Medium',
					'desc'    => 'Stored as top-level meta key: artwork_medium',
					'options' => array(
						'oil'        => 'Oil',
						'acrylic'    => 'Acrylic',
						'watercolor' => 'Watercolor',
						'digital'    => 'Digital',
					),
				),
				'artwork_tags'   => array(
					'type'   => 'repeater',
					'title'  => 'Tags (flat repeater)',
					'desc'   => 'Stored as top-level meta key: artwork_tags (array)',
					'fields' => array(
						array(
							'id'    => 'tag_name',
							'type'  => 'text',
							'title' => 'Tag',
						),
					),
				),
			),
		),

		// Serialized: one key holding nested object
		'artwork_info' => array(
			'id'                => 'artwork_info',
			'title'             => 'Nested Meta (serialized)',
			'data_type'         => 'serialize',
			'register_rest_api' => true,
			'fields'            => array(
				'title'      => array(
					'type'  => 'text',
					'title' => 'Title',
					'desc'  => 'Path: artwork_info.title',
				),
				'dimensions' => array(
					'type'   => 'group',
					'title'  => 'Dimensions',
					'desc'   => 'Path: artwork_info.dimensions (object)',
					'fields' => array(
						'width'  => array(
							'type'  => 'number',
							'title' => 'Width (cm)',
						),
						'height' => array(
							'type'  => 'number',
							'title' => 'Height (cm)',
						),
					),
				),
				'provenance' => array(
					'type'   => 'repeater',
					'title'  => 'Provenance (nested repeater)',
					'desc'   => 'Path: artwork_info.provenance[].owner / artwork_info.provenance[].year',
					'fields' => array(
						array(
							'id'    => 'owner',
							'type'  => 'text',
							'title' => 'Owner',
						),
						array(
							'id'    => 'year',
							'type'  => 'number',
							'title' => 'Year Acquired',
						),
					),
				),
				'notes'      => array(
					'type'  => 'textarea',
					'title' => 'Notes',
					'desc'  => 'Path: artwork_info.notes',
				),
			),
		),
	),
);
