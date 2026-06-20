<?php

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
