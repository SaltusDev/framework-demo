<?php
/**
 * Movie — the floor.
 *
 * Demonstrates the minimum viable model: type, name, supports, and just enough labels to produce
 * correct English strings. Everything else inherits from framework defaults (has_one/has_many).
 *
 * **Its value is being boring.** It proves the framework's defaults are real, not aspirational.
 * Anything added here damages it. For richer examples, see `book` (kitchen sink) or the focused
 * models (`recipe`, `event`, `venue`, etc.).
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

return array(
	'type'     => 'cpt',
	'name'     => 'movie',
	'supports' => array(
		'title',
		'editor',
		'thumbnail',
		'page-attributes',
	),
	'labels'   => array(
		'has_one'        => 'Movie',
		'has_many'       => 'Movies',
		'featured_image' => 'Movie Poster',
	),
);
