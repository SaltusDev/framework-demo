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
		'name'         => 'country',
		'labels'       => [
			'has_one'  => 'Country',
			'has_many' => 'Countries',
			'overrides' => [
				'labels' => [
					'menu_name' => 'Places',
				]
			],
		],
		// args - third parameter for register_taxonomy
		'options' => [
			'description'  => 'Description for this taxonomy',
			'public'       => true,
			'show_in_menu' => true,
		],
		// object_type - second parameter for register_taxonomy
		'associations' => [
			'book',
			'post',
		],
	],
];
