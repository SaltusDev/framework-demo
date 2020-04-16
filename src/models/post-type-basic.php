<?php

return [
	'type'     => 'cpt',
	'name'     => 'movie',
	'supports' => [
		'title',
		'editor',
		'thumbnail',
		'page-attributes',
	],
	'features' => [
		'dragAndDrop'   => true,
		'duplicate'     => true,
		'single_export' => true,
	],
	'labels'   => [
		'has_one'        => 'Movie',
		'has_many'       => 'Movies',
		'text_domain'    => 'framework-demo',
		'featured_image' => 'Movie Poster',
	],
];
