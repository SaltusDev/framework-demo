<?php
/**
 * Model configuration enums, generated from the framework surface.
 *
 * DO NOT EDIT. Regenerate with `php bin/generate-model-schema.php`.
 * Mirrors `schema/model.schema.json`; see that file for the full structural contract.
 *
 * - `field_types`     — every Codestar field type: one directory under
 *                       `lib/codestar-framework/fields/`.
 * - `type_aliases`    — accepted `type` values, mapped to their canonical target in
 *                       `ModelFactory::create()`.
 * - `max_name_length` — registration name ceilings enforced by
 *                       `BaseModel::get_registration_name()`.
 * - `reserved_post_types` / `reserved_taxonomies`
 *                     — names WordPress registers itself, which a model must not reuse.
 * - `reserved_query_vars`
 *                     — names in `WP::$public_query_vars`. NOT registered types: the clash is
 *                       conditional on being publicly queryable with `query_var` on, so it is a
 *                       warning. WooCommerce ships a CPT named `order`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

return array(
	'schema_version'      => '1.0.0',
	'field_types'         => array(
		'accordion',
		'background',
		'backup',
		'border',
		'button_set',
		'callback',
		'checkbox',
		'code_editor',
		'color',
		'color_group',
		'content',
		'date',
		'datetime',
		'dimensions',
		'fieldset',
		'gallery',
		'group',
		'heading',
		'icon',
		'image_select',
		'link',
		'link_color',
		'map',
		'media',
		'notice',
		'number',
		'palette',
		'radio',
		'repeater',
		'select',
		'slider',
		'sortable',
		'sorter',
		'spacing',
		'spinner',
		'subheading',
		'submessage',
		'switcher',
		'tabbed',
		'text',
		'textarea',
		'typography',
		'upload',
		'wp_editor',
	),
	'type_aliases'        => array(
		'cat'       => 'taxonomy',
		'category'  => 'taxonomy',
		'cpt'       => 'post',
		'post-type' => 'post',
		'post_type' => 'post',
		'posttype'  => 'post',
		'tag'       => 'taxonomy',
		'tax'       => 'taxonomy',
		'taxonomy'  => 'taxonomy',
	),
	'max_name_length'     => array(
		'post'     => 20,
		'taxonomy' => 32,
	),
	'reserved_post_types' => array(
		'attachment',
		'custom_css',
		'customize_changeset',
		'nav_menu_item',
		'oembed_cache',
		'page',
		'post',
		'revision',
		'user_request',
		'wp_block',
		'wp_font_face',
		'wp_font_family',
		'wp_global_styles',
		'wp_navigation',
		'wp_template',
		'wp_template_part',
	),
	'reserved_taxonomies' => array(
		'category',
		'link_category',
		'nav_menu',
		'post_format',
		'post_tag',
		'wp_pattern_category',
		'wp_template_part_area',
		'wp_theme',
	),
	'reserved_query_vars' => array(
		'action',
		'attachment_id',
		'author',
		'cat',
		'category_name',
		'day',
		'embed',
		'feed',
		'hour',
		'm',
		'minute',
		'monthnum',
		'name',
		'order',
		'orderby',
		'p',
		'page',
		'page_id',
		'paged',
		'pagename',
		'post_type',
		'posts',
		'preview',
		'robots',
		's',
		'search',
		'second',
		'sentence',
		'tag',
		'taxonomy',
		'term',
		'theme',
		'w',
		'year',
	),
);
