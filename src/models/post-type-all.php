<?php
/**
 * Book — the kitchen sink.
 *
 * This model demonstrates almost every framework feature together: meta, settings, admin cols/filters,
 * duplicate, export, drag-and-drop, remember tabs, quick edit, shortcode + blocks with custom templates,
 * and AI context with editorial review. Its value is showing how these features compose, not isolating
 * one for study.
 *
 * **To learn a single feature, see the focused models instead:**
 *
 * - Meta field types → `recipe` (all 44 Codestar types)
 * - Admin list mastery → `event` (all column/filter kinds + hooks)
 * - Frontend & blocks → `venue` (framework default templates, no overrides)
 * - Settings pages → `staff` (multi-section, custom parent, tabbed fields)
 * - AI governance → `release` (strict context, editorial review, per-section opt-outs)
 * - Serialized meta → `artwork` (data_type comparison, REST shape)
 * - Model formats → `snippet` (JSON model)
 * - Opt-out patterns → `internal_note` (REST/MCP gates, private CPT)
 *
 * This model is the reference for "all of the above, working together."
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

return array(
	'active'       => true,
	'type'         => 'cpt',
	'name'         => 'book',
	'features'     => array(
		'dragAndDrop'   => true,
		'duplicate'     => array(
			'label'      => __( 'Duplicate book', 'framework-demo' ),
			'attr_title' => __( 'Create a draft copy of this book', 'framework-demo' ),
		),
		'quick_edit'    => array(
			'paperback' => array(
				'title'       => __( 'Pages', 'framework-demo' ),
				'column_name' => 'paperback',
			),
			'isbn'      => array(
				'title'       => __( 'ISBN', 'framework-demo' ),
				'column_name' => 'isbn',
			),
		),
		'remember_tabs' => true,
		'single_export' => array(
			'label' => __( 'Export book', 'framework-demo' ),
		),
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
				'function' => function () {
					global $post;
					if ( ! $post instanceof \WP_Post ) {
						return;
					}
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
	),
	'supports'     => array(
		'title',
		'author',
		'excerpt',
		// 'editor',
		'page-attributes',
		'thumbnail',
		'post-formats',
	),
	'labels'       => array(
		'has_one'        => 'Book',
		'has_many'       => 'Books',
		'featured_image' => 'Book Cover',
		'text_domain'    => 'framework-demo',

		// optional, but better for translation
		'overrides'      => array(
			'labels'        => array(
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
				'filter_items_list'     => __( 'Filter books list', 'framework-demo' ),
				'items_list_navigation' => __( 'Books list navigation', 'framework-demo' ),
				'items_list'            => __( 'Books list', 'framework-demo' ),
				'featured_image'        => __( 'Book Cover Image', 'framework-demo' ),
				'set_featured_image'    => __( 'Set Book Cover Image', 'framework-demo' ),
				'remove_featured_image' => __( 'Remove Book Cover', 'framework-demo' ),
				'use_featured_image'    => __( 'Use as Book Cover', 'framework-demo' ),
			),
			// you can use the placeholders {permalink}, {preview_url}, {date}
			'messages'      => array(
				/* translators: {permalink}: replaced by the book permalink. */
				'post_updated'         => __( 'Book information updated. <a href="{permalink}" target="_blank">View Book</a>', 'framework-demo' ),
				'post_updated_short'   => __( 'Book info updated', 'framework-demo' ),
				'custom_field_updated' => __( 'Custom field updated', 'framework-demo' ),
				'custom_field_deleted' => __( 'Custom field deleted', 'framework-demo' ),
				'restored_to_revision' => __( 'Book content restored from revision', 'framework-demo' ),
				'post_published'       => __( 'Book Published', 'framework-demo' ),
				'post_saved'           => __( 'Book information saved.', 'framework-demo' ),
				/* translators: {preview_url}: replaced by the book preview URL. */
				'post_submitted'       => __( 'Book submitted. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
				/* translators: 1: {date}: scheduled publication date, 2: {preview_url}: replaced by the book preview URL. */
				'post_scheduled'       => __( 'Book scheduled for {date}. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
				/* translators: {preview_url}: replaced by the book preview URL. */
				'post_draft_updated'   => __( 'Book draft updated. <a href="{preview_url}" target="_blank">Preview</a>', 'framework-demo' ),
			),
			'bulk_messages' => array(
				'updated_singular'   => __( 'Book updated. Yay!', 'framework-demo' ),
				/* translators: %s: number of books updated. */
				'updated_plural'     => __( '%s Books updated. Yay!', 'framework-demo' ),
				'locked_singular'    => __( 'Book not updated, somebody is editing it', 'framework-demo' ),
				/* translators: %s: number of books not updated because they are locked. */
				'locked_plural'      => __( '%s Books not updated, somebody is editing them', 'framework-demo' ),
				'deleted_singular'   => __( 'Book permanently deleted. Fahrenheit 451 team was here?', 'framework-demo' ),
				/* translators: %s: number of books permanently deleted. */
				'deleted_plural'     => __( '%s Books permanently deleted. Why? :(', 'framework-demo' ),
				'trashed_singular'   => __( 'Book moved to the trash. I\'m sad :(', 'framework-demo' ),
				/* translators: %s: number of books moved to the trash. */
				'trashed_plural'     => __( '%s Books moved to the trash. Why? :(', 'framework-demo' ),
				'untrashed_singular' => __( 'Book recovered from trash. Well done!', 'framework-demo' ),
				/* translators: %s: number of books restored from the trash. */
				'untrashed_plural'   => __( '%s Books saved from the enemies!', 'framework-demo' ),
			),
			// overrides some of the available button labels and placeholders
			'ui'            => array(
				'enter_title_here' => __( 'Enter book name here', 'framework-demo' ),
			),
		),
	),
	'options'      => array(
		'public'             => true,
		'publicly_queryable' => true,
		// Gates the Saltus REST routes for this model. Individual sections ('meta',
		// 'settings', 'blocks') and features ('duplicate', 'single_export',
		// 'drag_and_drop') may override it with their own 'show_in_rest'.
		'show_in_rest'       => true,
		// Gates the WordPress-native MCP abilities for this model. Sections and
		// features override it with 'show_in_mcp'. Unlike REST, this defaults to
		// off, so a model is never exposed to AI clients unless it opts in.
		'mcp_tools'          => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'can_export'         => true,
		'capability_type'    => 'post',
		'menu_icon'          => 'dashicons-book',
		'rewrite'            => array(
			'slug'       => 'book',
			'with_front' => true,
			'feeds'      => true,
			'pages'      => true,
		),
	),
	'block_editor' => false,
	'blocks'       => array(
		'list'      => true,
		'single'    => true,
		'templates' => array(
			'list'   => 'templates/book-list.php',
			'single' => 'templates/book-single.php',
		),
	),
	'frontend'     => array(
		'shortcode'       => true,
		'shortcode_alias' => 'books',
		'templates'       => array(
			'list'   => 'templates/book-list.php',
			'single' => 'templates/book-single.php',
		),
	),
	'ai_context'   => array(
		'brand_voice'          => 'Clear, practical, literary, and specific. Avoid hype.',
		'audiences'            => array( 'readers', 'editors', 'bookshop teams' ),
		'field_rules'          => array(
			'post_title'   => array( 'Keep titles concise and preserve the author intent.' ),
			'post_excerpt' => array( 'Use a useful, spoiler-free summary in one or two sentences.' ),
			'post_content' => array( 'Preserve quotations and factual details supplied by the editor.' ),
		),
		'allowed_statuses'     => array( 'draft', 'pending', 'private' ),
		'forbidden_actions'    => array( 'publish' ),
		'require_human_review' => true,
	),
	'meta'         => array(
		// Add 'show_in_rest' => false here to keep meta out of the Saltus REST routes,
		// or 'show_in_mcp' => false to keep it away from AI clients while leaving the
		// REST routes intact. Both default to the model-level options above.
		'ts_info' => array(
			'id'       => 'information_metabox',
			'title'    => __( 'Information', 'framework-demo' ),
			'sections' => array(
				'details' => array(
					'title'  => __( 'Details', 'framework-demo' ),
					'desc'   => __( 'Book details', 'framework-demo' ),
					'icon'   => 'fa fa-file-text-o',
					'fields' => array(
						'cover'      => array(
							'title'          => __( 'Cover', 'framework-demo' ),
							'type'           => 'media',
							'library'        => 'image',
							'preview_size'   => 'full',
							'preview_width'  => 620,
							'preview_height' => 1102,
							'desc'           => __( 'Upload or select the book cover image', 'framework-demo' ),
						),
						'paperback'  => array(
							'title' => __( 'Paperback', 'framework-demo' ),
							'type'  => 'text',
							'desc'  => __( 'Number of pages', 'framework-demo' ),
						),
						'isbn'       => array(
							'title' => __( 'ISBN', 'framework-demo' ),
							'type'  => 'text',
						),
						'desc'       => array(
							'title' => __( 'Description', 'framework-demo' ),
							'type'  => 'textarea',
							'desc'  => __( 'Short description for this book', 'framework-demo' ),
						),
						'enabled'    => array(
							'type'    => 'switcher',
							'title'   => __( 'Display more options', 'framework-demo' ),
							'default' => false,
						),
						'icon'       => array(
							'title'      => __( 'Icon', 'framework-demo' ),
							'type'       => 'icon',
							'desc'       => __( 'Demo field', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						),
						'icon_color' => array(
							'title'      => __( 'Icon Color', 'framework-demo' ),
							'type'       => 'color',
							'desc'       => __( 'Social network icon colour', 'framework-demo' ),
							'dependency' => array( 'enabled', '==', 'true' ),
						),
					),
				),
				'gallery' => array(
					'title'  => __( 'Preview', 'framework-demo' ),
					'icon'   => 'fa fa-picture-o',
					'fields' => array(
						'gallery' => array(
							'title'    => __( 'Gallery', 'framework-demo' ),
							'type'     => 'gallery',
							'subtitle' => __( 'Images from the book.', 'framework-demo' ),
						),
					),
				),
				'notes'   => array(
					'title'  => __( 'Notes', 'framework-demo' ),
					'desc'   => __( 'Personal Notes', 'framework-demo' ),
					'icon'   => 'fa fa-sticky-note-o',
					'fields' => array(
						'notes' => array(
							'title'    => __( 'Notes', 'framework-demo' ),
							'type'     => 'wp_editor',
							'subtitle' => __( 'Personal notes', 'framework-demo' ),
						),
					),
				),
			),
		),
	),
	// settings page
	'settings'     => array(
		'framework-demo-settings' => array(
			'id'         => 'framework-demo-settings',
			'title'      => __( 'Plugin Settings', 'framework-demo' ),
			'capability' => 'manage_options',
			'menu_title' => __( 'Settings', 'framework-demo' ),
			'sections'   => array(
				'general'     => array(
					'title'  => __( 'General', 'framework-demo' ),
					'desc'   => __( 'Basic settings that affect the plugin behaviour', 'framework-demo' ),
					'icon'   => 'fa fa-cog fa-lg',
					'fields' => array(
						'gen01' => array(
							'type'    => 'text',
							'title'   => __( 'Option 01', 'framework-demo' ),
							'desc'    => __( 'Description', 'framework-demo' ),
							'default' => '',
						),

						'gen02' => array(
							'title'    => __( 'Option 02', 'framework-demo' ),
							'desc'     => __( 'description', 'framework-demo' ),
							'subtitle' => __( 'subtitle', 'framework-demo' ),
							'type'     => 'textarea',
						),
						'gen04' => array(
							'title' => __( 'Option 03', 'framework-demo' ),
							'desc'  => __( 'Description', 'framework-demo' ),
							'type'  => 'switcher',
						),
						'gen05' => array(
							'title' => __( 'Checkbox', 'framework-demo' ),
							'desc'  => __( 'Description', 'framework-demo' ),
							'type'  => 'checkbox',
						),
					),
				),


				'custom'      => array(
					'title'  => __( 'CSS', 'framework-demo' ),
					'desc'   => __( 'Add custom styles to the pages loading the layouts.', 'framework-demo' ),
					'icon'   => 'fa fa-code fa-lg',
					'fields' => array(
						'framework-demo_custom_css' => array(
							'title' => __( 'Custom CSS', 'framework-demo' ),
							'desc'  => __( 'Custom CSS to load with the layouts', 'framework-demo' ),
							'type'  => 'code_editor',
						),
					),
				),

				'demo_models' => array(
					'title'  => __( 'Demo Models', 'framework-demo' ),
					'desc'   => __( 'Enable additional models that demonstrate specific framework features. Each model is focused on one capability—meta fields, admin filters, frontend rendering, etc.—so you can study them in isolation rather than mining the kitchen-sink "book" model.', 'framework-demo' ),
					'icon'   => 'fa fa-cubes',
					'fields' => array(
						'enable_recipe'        => array(
							'type'    => 'switcher',
							'title'   => __( 'Recipe', 'framework-demo' ),
							'desc'    => __( 'All 44 Codestar field types across tabbed sections. The meta field gallery.', 'framework-demo' ),
							'default' => false,
						),
						'enable_event'         => array(
							'type'    => 'switcher',
							'title'   => __( 'Event', 'framework-demo' ),
							'desc'    => __( 'All admin column sources, all filter kinds, sortable/capability-gated columns, and admin_filters hooks.', 'framework-demo' ),
							'default' => false,
						),
						'enable_venue'         => array(
							'type'    => 'switcher',
							'title'   => __( 'Venue', 'framework-demo' ),
							'desc'    => __( 'Shortcode + blocks with framework default templates (no overrides). Frontend rendering baseline.', 'framework-demo' ),
							'default' => false,
						),
						'enable_staff'         => array(
							'type'    => 'switcher',
							'title'   => __( 'Staff', 'framework-demo' ),
							'desc'    => __( 'Multi-section settings page with custom menu parent, capability, and tabbed fields.', 'framework-demo' ),
							'default' => false,
						),
						'enable_release'       => array(
							'type'    => 'switcher',
							'title'   => __( 'Release', 'framework-demo' ),
							'desc'    => __( 'AI governance: strict ai_context, editorial review, per-section show_in_mcp opt-outs.', 'framework-demo' ),
							'default' => false,
						),
						'enable_artwork'       => array(
							'type'    => 'switcher',
							'title'   => __( 'Artwork', 'framework-demo' ),
							'desc'    => __( 'Serialized vs unserialized meta, register_rest_api, nested repeater paths. REST shape comparison.', 'framework-demo' ),
							'default' => false,
						),
						'enable_snippet'       => array(
							'type'    => 'switcher',
							'title'   => __( 'Snippet', 'framework-demo' ),
							'desc'    => __( 'Model declared in JSON (not PHP). Proves the loader accepts multiple formats.', 'framework-demo' ),
							'default' => false,
						),
						'enable_internal_note' => array(
							'type'    => 'switcher',
							'title'   => __( 'Internal Note', 'framework-demo' ),
							'desc'    => __( 'Opt-out reference: show_in_rest: false, mcp_tools absent, private CPT. The negative space.', 'framework-demo' ),
							'default' => false,
						),
						'enable_venue_type'    => array(
							'type'    => 'switcher',
							'title'   => __( 'Venue Type (taxonomy)', 'framework-demo' ),
							'desc'    => __( 'Taxonomy with show_in_rest: false and single association, contrasting the genre/writer/country trio.', 'framework-demo' ),
							'default' => false,
						),
					),
				),

			),
		),
	),
);
