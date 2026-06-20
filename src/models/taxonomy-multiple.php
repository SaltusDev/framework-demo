<?php
return array(
	array(
		'type'         => 'category',
		'name'         => 'genre',
		'associations' => array(
			'book',
		),
	),
	array(
		'type'         => 'category',
		'name'         => 'writer',
		'associations' => 'book',
	),
	array(
		'type'         => 'tag',
		'name'         => 'country',
		'labels'       => array(
			'has_one'   => 'Country',
			'has_many'  => 'Countries',
			'overrides' => array(
				'labels' => array(
					'menu_name' => 'Places',
				),
			),
		),
		// args - third parameter for register_taxonomy
		'options'      => array(
			'description'  => 'Description for this taxonomy',
			'public'       => true,
			'show_in_menu' => true,
		),
		// object_type - second parameter for register_taxonomy
		'associations' => array(
			'book',
			'post',
		),
	),
);
