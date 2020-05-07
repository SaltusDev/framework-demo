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
		'editor',
		'comments',
		'revisions',
		'trackbacks',
		'author',
		'excerpt',
		'page-attributes',
		'thumbnail',
		'custom-fields',
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
				'name'                  => 'Books',
				'singular_name'         => 'Book',
				'menu_name'             => 'Books',
				'name_admin_bar'        => 'Book',
				'add_new'               => 'Add New',
				'add_new_item'          => 'Add New Book',
				'edit_item'             => 'Edit Book',
				'new_item'              => 'New Book',
				'view_item'             => 'View Book',
				'view_items'            => 'View Books',
				'search_items'          => 'Search Books',
				'not_found'             => 'No books found.',
				'not_found_in_trash'    => 'No books found in Trash.',
				'parent_item-colon'     => 'Parent Books:',
				'all_items'             => 'All Books',
				'archives'              => 'Book Archives',
				'attributes'            => 'Book Attributes',
				'insert_into_item'      => 'Insert into book',
				'uploaded_to_this_item' => 'Uploaded to this book',
				'featured_image'        => 'Featured Image',
				'set_featured_image'    => 'Set featured image',
				'remove_featured_image' => 'Remove featured image',
				'use_featured_image'    => 'Use featured image',
				'filter_items_list'     => 'Filter books list',
				'items_list_navigation' => 'Books list navigation',
				'items_list'            => 'Books list',
				'featured_image'        => 'Book Cover Image',
				'set_featured_image'    => 'Set Book Cover Image',
				'remove_featured_image' => 'Remove Book Cover',
				'use_featured_image'    => 'Use as Book Cover',
			],
			'messages'      => [
				'post_updated'         => 'Book information updated. <a href="{permalink}" target="_blank">View Book</a>',
				'post_updated_short'   => 'Book info updated',
				'custom_field_updated' => 'Custom field updated',
				'custom_field_deleted' => 'Custom field deleted',
				'restored_to_revision' => 'Book content restored from revision',
				'post_published'       => 'Book Published',
				'post_saved'           => 'Book information saved.',
				'post_submitted'       => 'Book submitted. <a href="{preview_url}" target="_blank">Preview</a>',
				'post_schedulled'      => 'Book scheduled for {date}. <a href="{preview_url}" target="_blank">Preview</a>',
				'post_draft_updated'   => 'Book draft updated. <a href="{preview_url}" target="_blank">Preview</a>',
			],
			'bulk_messages' => [
				'updated_singular'   => 'Book updated. Yay!',
				'updated_plural'     => '%s Books updated. Yay!',
				'locked_singular'    => 'Book not updated, somebody is editing it',
				'locked_plural'      => '%s Books not updated, somebody is editing them',
				'deleted_singular'   => 'Book permanetly deleted. Fahrenheit 451 team was here?',
				'deleted_plural'     => '%s Books permanently deleted. Why? :(',
				'trashed_singular'   => 'Book moved to the trash. I\'m sad :(',
				'trashed_plural'     => '%s Books moved to the trash. Why? :(',
				'untrashed_singular' => 'Book recovered from trash. Well done!',
				'untrashed_plural'   => __( '%s Books saved from the enemies!', 'framework-demo' ),
			],
			// overrides some of the available button labels and placeholders
			'ui'            => [
				'enter_title_here' => 'Enter book name here',
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
				'social' => [
					'title'  => __( 'Social Networks', 'framework-demo' ),
					'desc'   => __( 'Links to Social Networks', 'framework-demo' ),
					'icon'   => 'fa fa-share-square fa-lg',
					'fields' => [
						'name'       => [
							'title' => __( 'Name', 'framework-demo' ),
							'type'  => 'text',
							'desc'  => __( 'Name this social network', 'framework-demo' ),
						],
						'enabled'    => [
							'type'    => 'switcher',
							'title'   => __( 'Enabled', 'framework-demo' ),
							'default' => true,
						],
						'icon'       => [
							'title'      => __( 'Icon', 'framework-demo' ),
							'type'       => 'icon',
							'desc'       => __( 'Icon for this social network', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						],
						'icon_color' => [
							'title'      => __( 'Icon Color', 'framework-demo' ),
							'type'       => 'color',
							'desc'       => __( 'Social network icon colour', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						],
						'desc'       => [
							'title'      => __( 'Description', 'framework-demo' ),
							'type'       => 'text',
							'desc'       => __( 'Description for this field', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
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
						'archive_url'     => [
							'type'    => 'text',
							'title'   => __( 'Archive Page Redirect', 'framework-demo' ),
							'desc'    => __( 'Sometimes breadcrumbs will link to the default theme archive page, which does not have the layout you want. You can setup here the URL you want the archive page to redirect to.', 'framework-demo' ),
							'default' => '',
						],

						'mailto_link'     => [
							'title' => __( 'mailto: Links', 'framework-demo' ),
							'desc'  => __( 'Enable mailto: links on email fields', 'framework-demo' ),
							'type'  => 'checkbox',
						],
						'tel_link'        => [
							'title' => __( 'tel: Links', 'framework-demo' ),
							'desc'  => __( 'Enable tel: links in telephone fields.', 'framework-demo' ),
							'type'  => 'checkbox',
						],
						'nofollow_social' => [
							'title' => __( 'nofollow for Social Links', 'framework-demo' ),
							'desc'  => __( 'When active, social links will have the rel="nofollow" parameter.', 'framework-demo' ),
							'type'  => 'checkbox',
						],
						'redirect_user'   => [
							'title' => __( 'Redirect to User Page', 'framework-demo' ),
							'desc'  => __( 'Will redirect the user/author pages to the associated team member entry page', 'framework-demo' ),
							'type'  => 'checkbox',
						],
					],
				],

				'images'  => [
					'title'  => __( 'Image', 'framework-demo' ),
					'desc'   => __( 'Options to control default image sizes', 'framework-demo' ),
					'icon'   => 'fa fa-picture-o fa-lg',
					'fields' => [
						'thumb_width'        => [
							'type'    => 'text',
							'title'   => __( 'Main Image Width', 'framework-demo' ),
							'default' => '200',
						],
						'thumb_height'       => [
							'title'   => __( 'Main Image Height', 'framework-demo' ),
							'default' => '200',
							'type'    => 'text',
						],
						'thumb_crop'         => [
							'title' => __( 'Main Image Crop', 'framework-demo' ),
							'desc'  => __( 'If the image will be cropped to fit defined size', 'framework-demo' ),
							'type'  => 'checkbox',
						],
						'tpthumb_width'      => [
							'type'    => 'text',
							'title'   => __( 'Thumbnails Image Width', 'framework-demo' ),
							'default' => '100',
						],
						'tpthumb_height'     => [
							'title'   => __( 'Thumbnails Pager Image Height', 'framework-demo' ),
							'default' => '100',
							'type'    => 'text',
						],
						'table_thumb_width'  => [
							'type'    => 'text',
							'title'   => __( 'Main Image Width', 'framework-demo' ),
							'default' => '50',
						],
						'table_thumb_height' => [
							'type'    => 'text',
							'title'   => __( 'Main Image Width', 'framework-demo' ),
							'default' => '50',
							'type'    => 'text',
						],
					],
				],
				'custom'  => [
					'title'  => __( 'CSS & JS', 'framework-demo' ),
					'desc'   => __( 'Add custom styles and code snippets to the pages loading the layouts.', 'framework-demo' ),
					'icon'   => 'fa fa-code fa-lg',
					'fields' => [
						'framework-demo_custom_css' => [
							'title' => __( 'Custom CSS', 'framework-demo' ),
							'desc'  => __( 'Custom CSS to load with the layouts', 'framework-demo' ),
							'type'  => 'code_editor',
						],
						'framework-demo_custom_js'  => [
							'title' => __( 'Custom Javascript', 'framework-demo' ),
							'desc'  => __( 'Custom Javascript to load with the layouts', 'framework-demo' ),
							'type'  => 'code_editor',
						],
					],
				],
				'plugin'  => [
					'title'  => __( 'Advanced', 'framework-demo' ),
					'desc'   => __( 'Advanced options to control how the plugin works.', 'framework-demo' ),
					'icon'   => 'fa fa-cogs fa-lg',
					'fields' => [
						'menu_name'                    => [
							'type'    => 'text',
							'title'   => __( 'Menu name', 'framework-demo' ),
							'desc'    => __( 'Text that will in the administration menus.', 'framework-demo' ),
							'default' => 'Team',
						],
						'framework-demo_name_singular' => [
							'type'    => 'text',
							'title'   => __( 'Singular Name', 'framework-demo' ),
							'desc'    => __( '', 'framework-demo' ),
							'default' => 'Member',
						],
						'framework-demo_name_plural'   => [
							'type'    => 'text',
							'title'   => __( 'Plural Name', 'framework-demo' ),
							'desc'    => __( '', 'framework-demo' ),
							'default' => 'Team',
						],
						'framework-demo_name_slug'     => [
							'type'    => 'text',
							'title'   => __( 'Slug', 'framework-demo' ),
							'desc'    => __( 'Text that will be used in permalinks. Must be unique and shouldn\'t contain spaces or special characters.', 'framework-demo' ),
							'default' => 'team',
						],

						'exclude_search'               => [
							'title'   => __( 'Exclude from general search', 'framework-demo' ),
							'desc'    => __( 'If active it will exclude the entries from the general site search results.', 'framework-demo' ),
							'type'    => 'switcher',
							'default' => true,
						],
						'block_editor'                 => [
							'title'   => __( 'Use block editor', 'framework-demo' ),
							'desc'    => __( 'If active when editing an entry, you\'ll be able to use the block editor.', 'framework-demo' ),
							'type'    => 'switcher',
							'default' => true,
						],
					],
				],

			],
		],
	],
];
