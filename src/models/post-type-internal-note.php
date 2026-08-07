<?php
/**
 * Internal Note — the opt-out reference.
 *
 * Demonstrates how to keep a CPT out of REST, MCP, and the frontend entirely. This is the
 * negative space model, and it matters because the two gates default differently:
 *
 * - **REST**: on by default unless explicitly `show_in_rest: false`
 * - **MCP**: off by default unless explicitly `mcp_tools: true`
 *
 * So a model with neither key set is REST-visible but MCP-hidden. This model opts out of both.
 *
 * **What `list_models` returns for this CPT:** Nothing. MCP discovery only includes models with
 * `mcp_tools: true`. REST controllers honor `show_in_rest: false` and return 404. The type exists
 * only in wp-admin.
 *
 * Enable this model from the "Demo Models" settings section. For the inverse (full REST + MCP
 * exposure), see `book` or `release`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'internal_note' ) ) {
	return array();
}

return array(
	'type'    => 'cpt',
	'name'    => 'internal_note',
	'labels'  => array(
		'has_one'  => 'Internal Note',
		'has_many' => 'Internal Notes',
	),
	'options' => array(
		'menu_icon'          => 'dashicons-lock',
		'supports'           => array( 'title', 'editor' ),
		// Lockdown: not visible on the frontend
		'public'             => false,
		'publicly_queryable' => false,
		'has_archive'        => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => false,
		// REST gate: explicitly disabled
		'show_in_rest'       => false,
		// MCP gate: omitted entirely, so it defaults to false
		// (if this were set to `mcp_tools: true`, the type would appear in discovery)
	),
	'meta'    => array(
		'note_meta' => array(
			'id'     => 'note_meta',
			'title'  => 'Note Details',
			'fields' => array(
				'note_priority' => array(
					'type'    => 'select',
					'title'   => 'Priority',
					'options' => array(
						'low'    => 'Low',
						'normal' => 'Normal',
						'high'   => 'High',
					),
					'default' => 'normal',
				),
				'note_content'  => array(
					'type'  => 'textarea',
					'title' => 'Internal Content',
					'desc'  => 'Not exposed via REST or MCP',
				),
			),
		),
	),
);
