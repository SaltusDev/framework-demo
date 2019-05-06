<?php
return [
    'active' => true,
    'type' => 'cpt',
    'name' => 'book',
    'supports' => [
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
    'labels' => [
        'has_one' => 'Book',
        'has_many' => 'Books',
        'text_domain' => 'sage',
        'overrides' => [
            'name' => 'Books',
            'singular_name' => 'Book',
            'menu_name' => 'Books',
            'name_admin_bar' => 'Book',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Book',
            'edit_item' => 'Edit Book',
            'new_item' => 'New Book',
            'view_item' => 'View Book',
            'view_items' => 'View Books',
            'search_items' => 'Search Books',
            'not_found' => 'No books found.',
            'not_found_in_trash' => 'No books found in Trash.',
            'parent_item-colon' => 'Parent Books:',
            'all_items' => 'All Books',
            'archives' => 'Book Archives',
            'attributes' => 'Book Attributes',
            'insert_into_item' => 'Insert into book',
            'uploaded_to_this_item' => 'Uploaded to this book',
            'featured_image' => 'Featured Image',
            'set_featured_image' => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image' => 'Use featured image',
            'filter_items_list' => 'Filter books list',
            'items_list_navigation' => 'Books list navigation',
            'items_list' => 'Books list',
        ],
    ],
    'config' => [
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => NULL,
        'can_export' => true,
        'capability_type' => 'post',
        'taxonomies' => [
            'category', 'post_tag'
		],
		'menu_icon'  => 'dashicons-book',
        'rewrite' => [
            'slug' => 'book',
            'with_front' => true,
            'feeds' => true,
            'pages' => true,
		],
		'featured_image'      => 'Image for this entry',
		'enter_title_here'    => 'Enter name here',
		'admin_cols'          => array(
			'featured_image'       => array(
				'title'          => 'Image',
				'featured_image' => 'thumbnail',
			),
			'title',
			'categories' => array(
				'taxonomy' => 'category',
			),
			'author'               => array(
				'title'      => 'Author',
				'post_field' => 'post_author',
			),
			'id'                   => array(
				'title'      => 'ID',
				'post_field' => 'ID',
			),

		),
		'admin_filters'       => array(
			'categories' => array(
				'taxonomy' => 'category',
			),
		),
    ],
];