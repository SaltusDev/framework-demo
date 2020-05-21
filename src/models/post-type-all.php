<?php

return [
	'active'       => true,
	'type'         => 'cpt',
	'name'         => 'book',
	'features'     => [
		'dragAndDrop'   => true,
		'duplicate'     => true,
		'single_export' => true,
		'admin_cols'    => array(
			'featured_image' => array(
				'title'          => 'Image',
				'featured_image' => 'thumbnail',
			),
			'title',
			'genre'          => array(
				'taxonomy' => 'genre',
			),
			'writer'         => array(
				'taxonomy' => 'writer',
			),
			'author'         => array(
				'title'      => 'Entry Author',
				'post_field' => 'post_author',
			),
			'id'             => array(
				'title'      => 'ID',
				'post_field' => 'ID',
			),
			'shortcode'      => array(
				'title'    => __( 'Shortcode', 'framework-demo' ),
				'function' => function() {
					global $post;
					echo esc_html( '[display-book id="' . $post->ID . '"]' );
				},
			),
		),
		'admin_filters' => array(
			'genres'  => array(
				'taxonomy' => 'genre',
			),
			'writers' => array(
				'taxonomy' => 'writer',
			),
		),
	],
	'supports'     => [
		'title',
		'author',
		'excerpt',
		// 'editor',
		'page-attributes',
		'thumbnail',
		'post-formats',
	],
	'labels'       => [
		'has_one'        => 'Book',
		'has_many'       => 'Books',
		'featured_image' => 'Book Cover',
		'text_domain'    => 'framework-demo',

		// optional, but better for translation
		'overrides'      => [
			'labels'        => [
				'name'                  => __( 'Books', 'framework-demo' ),
				'singular_name'         => __( 'Book', 'framework-demo' ),
				'menu_name'             => __( 'Books', 'framework-demo' ),
				'name_admin_bar'        => __( 'Book', 'framework-demo' ),
				'add_new'               => __( 'Add New', 'framework-demo' ),
				'add_new_item'          => __( 'Add New Book', 'framework-demo' ),
				'edit_item'             => __( 'Edit Book', 'framework-demo' ),
				'new_item'              => __( 'New Book', 'framework-demo' ),
				'view_item'             => __( 'View Book', 'framework-demo' ),
				'view_items'            => __( 'View Books', 'framework-demo' ),
				'search_items'          => __( 'Search Books', 'framework-demo' ),
				'not_found'             => __( 'No books found.', 'framework-demo' ),
				'not_found_in_trash'    => __( 'No books found in Trash.', 'framework-demo' ),
				'parent_item-colon'     => __( 'Parent Books:', 'framework-demo' ),
				'all_items'             => __( 'All Books', 'framework-demo' ),
				'archives'              => __( 'Book Archives', 'framework-demo' ),
				'attributes'            => __( 'Book Attributes', 'framework-demo' ),
				'insert_into_item'      => __( 'Insert into book', 'framework-demo' ),
				'uploaded_to_this_item' => __( 'Uploaded to this book', 'framework-demo' ),
				'featured_image'        => __( 'Featured Image', 'framework-demo' ),
				'set_featured_image'    => __( 'Set featured image', 'framework-demo' ),
				'remove_featured_image' => __( 'Remove featured image', 'framework-demo' ),
				'use_featured_image'    => __( 'Use featured image', 'framework-demo' ),
				'filter_items_list'     => __( 'Filter books list', 'framework-demo' ),
				'items_list_navigation' => __( 'Books list navigation', 'framework-demo' ),
				'items_list'            => __( 'Books list', 'framework-demo' ),
				'featured_image'        => __( 'Book Cover Image', 'framework-demo' ),
				'set_featured_image'    => __( 'Set Book Cover Image', 'framework-demo' ),
				'remove_featured_image' => __( 'Remove Book Cover', 'framework-demo' ),
				'use_featured_image'    => __( 'Use as Book Cover', 'framework-demo' ),
			],
			// you can use the placeholders {permalink}, {preview_url}, {date}
			'messages'      => [
				'post_updated'         => __( 'Book information updated. <a href="{permalink}" target="_blank">View Book</a>', 'framework-demo' ),
				'post_updated_short'   => __( 'Book info updated', 'framework-demo' ),
				'custom_field_updated' => __( 'Custom field updated', 'framework-demo' ),
				'custom_field_deleted' => __( 'Custom field deleted', 'framework-demo' ),
				'restored_to_revision' => __( 'Book content restored from revision', 'framework-demo' ),
				'post_published'       => __( 'Book Published', 'framework-demo' ),
				'post_saved'           => __( 'Book information saved.', 'framework-demo' ),
				'post_submitted'       => __( 'Book submitted. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
				'post_schedulled'      => __( 'Book scheduled for {date}. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
				'post_draft_updated'   => __( 'Book draft updated. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
			],
			'bulk_messages' => [
				'updated_singular'   => __( 'Book updated. Yay!', 'framework-demo' ),
				'updated_plural'     => __( '%s Books updated. Yay!', 'framework-demo' ),
				'locked_singular'    => __( 'Book not updated, somebody is editing it', 'framework-demo' ),
				'locked_plural'      => __( '%s Books not updated, somebody is editing them', 'framework-demo' ),
				'deleted_singular'   => __( 'Book permanetly deleted. Fahrenheit 451 team was here?', 'framework-demo' ),
				'deleted_plural'     => __( '%s Books permanently deleted. Why? :(', 'framework-demo' ),
				'trashed_singular'   => __( 'Book moved to the trash. I\'m sad :(', 'framework-demo' ),
				'trashed_plural'     => __( '%s Books moved to the trash. Why? :(', 'framework-demo' ),
				'untrashed_singular' => __( 'Book recovered from trash. Well done!', 'framework-demo' ),
				'untrashed_plural'   => __( '%s Books saved from the enemies!', 'framework-demo' ),
			],
			// overrides some of the available button labels and placeholders
			'ui'            => [
				'enter_title_here' => __( 'Enter book name here', 'framework-demo' ),
			],
		],
	],
	'options'      => [
		'public'             => true,
		'publicly_queryable' => true,
		'show_in_rest'       => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'can_export'         => true,
		'capability_type'    => 'post',
		'menu_icon'          => 'dashicons-book',
		'rewrite'            => [
			'slug'       => 'book',
			'with_front' => true,
			'feeds'      => true,
			'pages'      => true,
		],
	],
	'block_editor' => false,
	'meta'         => [
		'ts_info' => [
			'id'       => 'information_metabox',
			'title'    => __( 'Information', 'framework-demo' ),
			'sections' => [
				'details' => [
					'title'  => __( 'Details', 'framework-demo' ),
					'desc'   => __( 'Book details', 'framework-demo' ),
					'icon'   => 'fa fa-file-text-o',
					'fields' => [
						'paperback'  => [
							'title' => __( 'Paperback', 'framework-demo' ),
							'type'  => 'text',
							'desc'  => __( 'Number of pages', 'framework-demo' ),
						],
						'isbn'       => [
							'title' => __( 'ISBN', 'framework-demo' ),
							'type'  => 'text',
						],
						'desc'       => [
							'title' => __( 'Description', 'framework-demo' ),
							'type'  => 'textarea',
							'desc'  => __( 'Short description for this book', 'framework-demo' ),
						],
						'enabled'    => [
							'type'    => 'switcher',
							'title'   => __( 'Display more options', 'framework-demo' ),
							'default' => false,
						],
						'icon'       => [
							'title'      => __( 'Icon', 'framework-demo' ),
							'type'       => 'icon',
							'desc'       => __( 'Demo field', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						],
						'icon_color' => [
							'title'      => __( 'Icon Color', 'framework-demo' ),
							'type'       => 'color',
							'desc'       => __( 'Social network icon colour', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						],
					],
				],
				'gallery' => [
					'title'  => __( 'Preview', 'framework-demo' ),
					'icon'   => 'fa fa-picture-o',
					'fields' => [
						'gallery' => [
							'title'    => __( 'Gallery', 'framework-demo' ),
							'type'     => 'gallery',
							'subtitle' => __( 'Images from the book.', 'framework-demo' ),
						],
					],
				],
				'notes'   => [
					'title'  => __( 'Notes', 'framework-demo' ),
					'desc'   => __( 'Personal Notes', 'framework-demo' ),
					'icon'   => 'fa fa-sticky-note-o',
					'fields' => [
						'notes' => [
							'title'    => __( 'Notes', 'framework-demo' ),
							'type'     => 'wp_editor',
							'subtitle' => __( 'Personal notes', 'framework-demo' ),
						],
					],
				],
			],
		],
	],
	// settings page
	'settings'     => [
		'framework-demo-settings' => [
			'id'         => 'framework-demo-settings',
			'title'      => __( 'Plugin Settings', 'framework-demo' ),
			'capability' => 'manage_options',
			'menu_title' => __( 'Settings', 'framework-demo' ),
			'sections'   => [
				'general' => [
					'title'  => __( 'General', 'framework-demo' ),
					'desc'   => __( 'Basic settings that affect the plugin behaviour', 'framework-demo' ),
					'icon'   => 'fa fa-cog fa-lg',
					'fields' => [
						'gen01' => [
							'type'    => 'text',
							'title'   => __( 'Option 01', 'framework-demo' ),
							'desc'    => __( 'Description', 'framework-demo' ),
							'default' => '',
						],

						'gen02' => [
							'title'    => __( 'Option 02', 'framework-demo' ),
							'desc'     => __( 'description', 'framework-demo' ),
							'subtitle' => __( 'subtitle', 'framework-demo' ),
							'type'     => 'textarea',
						],
						'gen04' => [
							'title' => __( 'Option 03', 'framework-demo' ),
							'desc'  => __( 'Description', 'framework-demo' ),
							'type'  => 'switcher',
						],
						'gen05' => [
							'title' => __( 'Checkbox', 'framework-demo' ),
							'desc'  => __( 'Description', 'framework-demo' ),
							'type'  => 'checkbox',
						],
					],
				],


				'custom'  => [
					'title'  => __( 'CSS', 'framework-demo' ),
					'desc'   => __( 'Add custom styles to the pages loading the layouts.', 'framework-demo' ),
					'icon'   => 'fa fa-code fa-lg',
					'fields' => [
						'framework-demo_custom_css' => [
							'title' => __( 'Custom CSS', 'framework-demo' ),
							'desc'  => __( 'Custom CSS to load with the layouts', 'framework-demo' ),
							'type'  => 'code_editor',
						],
					],
				],

			],
		],
	],
];
