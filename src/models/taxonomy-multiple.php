<?php
return [
	[
		'type'         => 'category',
		'name'         => 'genre',
		'associations' => [
			'book',
		],
	],
	[
		'type'         => 'tag',
		'name'         => 'author',
		'associations' => 'book',
	],
];
