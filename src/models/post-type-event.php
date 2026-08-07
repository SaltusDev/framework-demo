<?php
/**
 * Event — admin list mastery.
 *
 * Demonstrates all admin column source kinds, all admin filter kinds, sortable/capability-gated
 * columns, and the two admin_filters hooks for customizing filter rendering and queries.
 *
 * This is the model that makes the framework's extended-cpts heritage legible. For meta fields
 * themselves, see `recipe`; for how these combine with frontend rendering or AI context, see `book`.
 *
 * Enable this model from the "Demo Models" settings section.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'event' ) ) {
	return array();
}

return array(
	'type'     => 'cpt',
	'name'     => 'event',
	'labels'   => array(
		'has_one'  => 'Event',
		'has_many' => 'Events',
	),
	'options'  => array(
		'menu_icon' => 'dashicons-calendar-alt',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	),
	'meta'     => array(
		'event_details' => array(
			'id'     => 'event_details',
			'title'  => 'Event Details',
			'fields' => array(
				'event_date'           => array(
					'type'  => 'date',
					'title' => 'Event Date',
					'desc'  => 'Used by the post_date filter and date_format column demo',
				),
				'event_venue'          => array(
					'type'  => 'text',
					'title' => 'Venue',
					'desc'  => 'Used by meta_key filter and meta_key column',
				),
				'event_capacity'       => array(
					'type'  => 'number',
					'title' => 'Capacity',
					'desc'  => 'Used by meta_key column with sortable',
				),
				'event_organizer'      => array(
					'type'  => 'text',
					'title' => 'Organizer',
					'desc'  => 'Used by meta_search_key filter',
				),
				'event_featured'       => array(
					'type'  => 'switcher',
					'title' => 'Featured Event',
					'desc'  => 'Used by meta_exists filter',
				),
				'event_internal_notes' => array(
					'type'  => 'textarea',
					'title' => 'Internal Notes',
					'desc'  => 'Hidden column, capability-gated to edit_others_posts',
				),
			),
		),
	),
	'features' => array(
		'admin_cols'    => array(
			// post_field source
			'event_status'        => array(
				'title'      => 'Status',
				'post_field' => 'post_status',
				'sortable'   => true,
			),
			// meta_key source with date_format
			'event_date_col'      => array(
				'title'       => 'Event Date',
				'meta_key'    => 'event_date',
				'date_format' => 'M j, Y',
				'sortable'    => true,
			),
			// meta_key source with link
			'event_venue_col'     => array(
				'title'    => 'Venue',
				'meta_key' => 'event_venue',
				'link'     => 'edit',
			),
			// meta_key source, numeric
			'event_capacity_col'  => array(
				'title'    => 'Capacity',
				'meta_key' => 'event_capacity',
				'sortable' => true,
			),
			// taxonomy source
			'event_category_col'  => array(
				'title'    => 'Category',
				'taxonomy' => 'event_category',
			),
			// featured_image source
			'event_thumbnail_col' => array(
				'title'          => 'Poster',
				'featured_image' => 'thumbnail',
				'sortable'       => false,
			),
			// function callback
			'event_computed_col'  => array(
				'title'    => 'Days Until',
				'function' => function ( $post ) {
					$event_date = get_post_meta( $post->ID, 'event_date', true );
					if ( ! $event_date ) {
						return '—';
					}
					$diff = floor( ( strtotime( $event_date ) - time() ) / DAY_IN_SECONDS );
					if ( $diff < 0 ) {
						return 'Past';
					}
					return $diff . ' days';
				},
				'sortable' => false,
			),
			// capability-gated column
			'event_internal_col'  => array(
				'title'    => 'Internal Notes',
				'meta_key' => 'event_internal_notes',
				'cap'      => 'edit_others_posts',
			),
		),

		'admin_filters' => array(
			// taxonomy filter
			'event_category_filter'  => array(
				'title'    => 'Category',
				'taxonomy' => 'event_category',
			),
			// meta_key filter (dropdown of distinct values)
			'event_venue_filter'     => array(
				'title'    => 'Venue',
				'meta_key' => 'event_venue',
			),
			// meta_search_key filter (text input)
			'event_organizer_filter' => array(
				'title'           => 'Organizer',
				'meta_search_key' => 'event_organizer',
			),
			// meta_exists filter
			'event_featured_filter'  => array(
				'title'       => 'Featured',
				'meta_exists' => 'event_featured',
			),
			// post_date filter with custom key
			'event_date_filter'      => array(
				'title'     => 'Event Date',
				'post_date' => 'event_date',
				'key'       => 'event_date_filter_key',
			),
		),
	),
);
