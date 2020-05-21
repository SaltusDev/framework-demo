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
	'labels'   => [
		'has_one'        => 'Movie',
		'has_many'       => 'Movies',
		'featured_image' => 'Movie Poster',
	],
];
