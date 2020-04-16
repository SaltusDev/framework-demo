<?php

// defaults for some repeater fields in the settings, just for the demo
$meta_info_default   = json_decode( '[{"name":"Short Bio","enabled":"1","desc":"Shorter description for this entry. You can use HTML tags here","type":"textarea","icon":"fa fa-align-left","id":"_tsfreehtml"},{"name":"Position","enabled":"1","desc":"The job description, position or functions","type":"text","icon":"fa fa-bookmark","id":"_tsposition"},{"name":"Location","enabled":"1","desc":"Location\/Origin\/Address","type":"text","icon":"fa fa-map-marker","id":"_tslocation"},{"name":"Personal Website","enabled":"1","desc":"Full URL to personal website.","type":"text","icon":"fa fa-external-link","id":"_tspersonal"},{"name":"Personal Website Anchor Text","enabled":"1","desc":"Text to display for the link. If blank URL will be used.","type":"text","icon":"fa fa-external-link","id":"_tspersonalanchor"},{"name":"Telephone","enabled":"1","desc":"Telephone contact.","type":"text","icon":"fa fa-mobile","id":"_tstel"},{"name":"Email","enabled":"1","desc":"Email address.","type":"text","icon":"fa fa-envelope","id":"_tsemail"},{"name":"User\/Author Profile","enabled":"1","desc":"If this member is associated with a user account select it here. Might be used to fetch latest published posts in the single member page.","type":"user_select","icon":"fa fa-user","id":"_tsuser"}]', true );
$meta_social_default = json_decode( '[{"name":"Facebook","enabled":"1","icon":"fa fa-facebook-square","icon_color":"#3b5998","desc":"URL to facebook profile"},{"name":"Twitter","enabled":"1","icon":"fa fa-twitter-square","icon_color":"#00acee","desc":"Complete URL to twitter profile"}]', true );
$taxonomies_default  = json_decode( '[{"enabled":"1","name":"Category","slug":"framework-demo-categories","hierarchical":"1"},{"enabled":"0","name":"Group","slug":"framework-demo-taxonomy","hierarchical":"1"},{"enabled":"0","name":"Taxonomy","slug":"framework-demo-ctaxonomy","hierarchical":"1"},{"enabled":"0","name":"Taxonomy 4","slug":"framework-demo-dtaxonomy","hierarchical":"1"}]', true );

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
				'title'    => __( 'Shortcode', 'interactive-geo-maps' ),
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
		// auto created featured image labels
		'featured_image' => 'Book Cover',
		'text_domain'    => 'framework-demo',
		'overrides'      => [
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
		// todo in saltus
		// overrides some of the available button labels and placeholders
		'ui_overrides'   => [
			'enter_title_here' => 'Enter book name here',
			'publish'          => 'Save Book',
		],
	],
	// todo in saltus
	// we could use placeholders like {permalink}, {date}
	'messages'     => [
		'published'    => 'Book Saved',
		'saved'        => 'Book Saved',
		'updated'      => 'Book updated',
		'updated_view' => 'Book Saved <a href="{permalink}">View</a>',
		'submitted'    => 'Book Submitted',
		'scheduled'    => 'Scheduled for: {date}',
		'draf_updated' => 'Book draft updated',
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
						'warning'                      => [
							'type'    => 'submessage',
							'style'   => 'warning',
							'content' => __( 'Attention! Changing the settings below might lead to the loss of data. Avoid deleting existing fields if you have already saved data into them. Proceed with carefull.', 'framework-demo' ),
						],
						'taxonomies'                   => [
							'title'        => __( 'Taxonomies', 'framework-demo' ),
							'desc'         => __( 'Available taxonomies in the plugin. <br> Hierarchical taxonomies will behave the same way as categories. Non-hierarchical taxonomies will behave like tags.', 'framework-demo' ),
							'type'         => 'group',
							'group_title'  => __( 'Entry {#}', 'framework-demo' ),
							'button_title' => __( 'Add Another Taxonomy', 'framework-demo' ),
							'closed'       => true,
							'default'      => $taxonomies_default,
							'fields'       => [

								'name'         => [
									'title' => __( 'Name', 'framework-demo' ),
									'type'  => 'text',
									'desc'  => __( 'Singular name for this taxonomy', 'framework-demo' ),
								],
								'enabled'      => [
									'type'    => 'switcher',
									'title'   => __( 'Enabled', 'framework-demo' ),
									'default' => true,
								],
								'plural_name'  => [
									'title'      => __( 'Plural name', 'framework-demo' ),
									'type'       => 'text',
									'desc'       => __( 'Plural name for this taxonomy', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
								],
								'slug'         => [
									'title'      => __( 'Unique Identifier (slug)', 'framework-demo' ),
									'type'       => 'text',
									'desc'       => __( 'Unique word to identify this taxonomy. When changed a new taxonomy will be created. No spaces are allowed.<br>If empty, it will default to the singular name of this taxonomy.', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
								],
								'hierarchical' => [
									'title'      => __( 'Hierarchical?', 'framework-demo' ),
									'type'       => 'checkbox',
									'desc'       => __( 'Can the terms in this taxonomy have parent-child relationship?', 'framework-demo' ),
									'default'    => true,
									'dependency' => array( 'enabled', '==', 'true' ),
								],

							],
						],
						'meta_info'                    => [
							'title'        => __( 'Information Fields', 'framework-demo' ),
							'subtitle'     => 'Work in Progress. Not done yet.',
							'desc'         => __( 'Fields that will display when adding new entries.', 'framework-demo' ),
							'type'         => 'group',
							'group_title'  => __( 'Entry {#}', 'framework-demo' ),
							'button_title' => __( 'Add Another Field', 'framework-demo' ),
							'closed'       => true,
							'default'      => $meta_info_default,
							'fields'       => [
								'name'     => [
									'title' => __( 'Name', 'framework-demo' ),
									'type'  => 'text',
									'desc'  => __( 'Name this field', 'framework-demo' ),
								],
								'enabled'  => [
									'type'    => 'switcher',
									'title'   => __( 'Enabled', 'framework-demo' ),
									'default' => true,
								],
								'desc'     => [
									'title'      => __( 'Description', 'framework-demo' ),
									'type'       => 'text',
									'desc'       => __( 'Description for this field', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
								],
								'type'     => [
									'title'      => __( 'Field type', 'framework-demo' ),
									'type'       => 'select',
									'desc'       => __( 'Type of field', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
									'options'    => [
										'text'        => __( 'Text', 'framework-demo' ),
										'textarea'    => __( 'Textarea', 'framework-demo' ),
										'user_select' => __( 'User select', 'framework-demo' ),
									],
								],
								'icon'     => [
									'title'      => __( 'Icon', 'framework-demo' ),
									'type'       => 'icon',
									'desc'       => __( 'Icon for this social network', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
								],
								'template' => [
									'title'      => __( 'Template', 'framework-demo' ),
									'type'       => 'text',
									'desc'       => __( 'HTML template when the content for this field is displayed. The placeholder {value} must be present.', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
									'default'    => '{value}',
								],
								'id'       => [
									'title'      => __( 'Unique ID', 'framework-demo' ),
									'type'       => 'text',
									'desc'       => __( 'Unique identifier for this field. Should not be changed once you have added data.<br>If left blank, it will default to a sanitized version of the name of this field.', 'framework-demo' ),
									'dependency' => array( 'enabled', '==', 'true' ),
								],

							],
						],
						'meta_social'                  => [
							'title'        => __( 'Social Networks', 'framework-demo' ),
							'desc'         => __( 'Social Networks fields that will display when adding new entries.', 'framework-demo' ),
							'type'         => 'group',
							'group_title'  => __( 'Entry {#}', 'framework-demo' ),
							'button_title' => __( 'Add Another Social Network', 'framework-demo' ),
							'closed'       => true,
							'default'      => $meta_social_default,
							'fields'       => [
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
		],
	],
];
