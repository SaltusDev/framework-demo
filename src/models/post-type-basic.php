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
	/**
	* reserved words:
	* - meta
	* - section
	* - fields
	*/
	'meta'     => [
		'section' => [
			'id'     => 'aaaa',
			'title'  => 'title1',
			'fields' => [
				// 'id' => array of settings
				'subtitle' => [
					'type'        => 'text',
					'name'        => __( 'Subtitle', 'framework-demo' ),
					'description' => __( 'test!', 'framework-demo' ),
					'default'     => '',
				],
				'lead'     => [
					'type'        => 'text',
					'name'        => __( 'Lead', 'framework-demo' ),
					'description' => __( 'A longer paragraph.', 'framework-demo' ),
					'default'     => '',
				],
			],
		],
	],
];
