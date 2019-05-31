<?php
return [
	'type'     => 'cpt',
	'name'     => 'movie',
	'supports' => [
		'title',
		'editor',
		'thumbnail',
	],
	'labels'   => [
		'has_one'     => 'Movie',
		'has_many'    => 'Movies',
		'text_domain' => 'saltus',
	],
];
