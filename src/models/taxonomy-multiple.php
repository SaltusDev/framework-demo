<?php
/**
 * Taxonomy trio — hierarchical, flat, and multi-association.
 *
 * - `genre` (category-style, hierarchical) → `book` only
 * - `writer` (tag-style, flat) → `book` only
 * - `country` (tag-style) → `book` + core `post`, demonstrating multi-post-type association
 *
 * **Note on taxonomy meta:** Taxonomy models accept a `meta` key and silently ignore it.
 * `ModelFactory::create()` only calls `process_services()` for `PostType`; `Taxonomy::set_meta()`
 * stores the config into `args` where nothing consumes it. Codestar has `createTaxonomyOptions()`,
 * but the framework never calls it. Term meta is not a framework feature as of v3.0.0.
 *
 * For a taxonomy opt-out example (single association, `show_in_rest: false`), see `venue_type`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */
return array(
	array(
		'type'         => 'category',
		'name'         => 'genre',
		'associations' => array(
			'book',
		),
	),
	array(
		'type'         => 'category',
		'name'         => 'writer',
		'associations' => 'book',
	),
	array(
		'type'         => 'tag',
		'name'         => 'country',
		'labels'       => array(
			'has_one'   => 'Country',
			'has_many'  => 'Countries',
			'overrides' => array(
				'labels' => array(
					'menu_name' => 'Places',
				),
			),
		),
		// args - third parameter for register_taxonomy
		'options'      => array(
			'description'  => 'Description for this taxonomy',
			'public'       => true,
			'show_in_menu' => true,
		),
		// object_type - second parameter for register_taxonomy
		'associations' => array(
			'book',
			'post',
		),
	),
);
