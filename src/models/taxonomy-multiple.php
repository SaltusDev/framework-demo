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
