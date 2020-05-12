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
		'type'         => 'category',
		'name'         => 'writer',
		'associations' => 'book',
	],
	[
		'type'         => 'tag',
		'name'         => 'mood',
		'associations' => [
			'book',
			'post',
		],
	],
];
