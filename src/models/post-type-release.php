<?php
/**
 * Release — AI governance.
 *
 * Demonstrates strict `ai_context` configuration: restricted statuses, forbidden actions,
 * editorial review enabled, per-field validation rules, and per-section `show_in_mcp` opt-outs
 * while REST stays enabled (proving the gates are independent).
 *
 * This is the locked-down contrast to `book`'s permissive-ish AI context. The model where
 * governance is the point, not a footnote.
 *
 * **Editorial review queue:** Tools → AI Review Queue. Every mutation on this model queues for
 * human approval because of `require_human_review: true` (though see ROADMAP Phase 3 task 4 — the
 * framework filter currently lacks model context, so review applies globally to all models).
 *
 * **MCP rejection path:** When an AI client violates `ai_context` rules, the framework returns
 * HTTP 403 with `code: "ai_context_violation"` and a `data.hint` explaining the violation.
 *
 * Enable this model from the "Demo Models" settings section.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'release' ) ) {
	return array();
}

return array(
	'type'       => 'cpt',
	'name'       => 'release',
	'labels'     => array(
		'has_one'  => 'Release',
		'has_many' => 'Releases',
	),
	'options'    => array(
		'menu_icon'    => 'dashicons-shield-alt',
		'supports'     => array( 'title', 'editor', 'revisions' ),
		'show_in_rest' => true,
		'mcp_tools'    => true, // MCP discovery enabled; REST also on
	),
	'meta'       => array(
		'release_public'   => array(
			'id'           => 'release_public',
			'title'        => 'Release Info (MCP-visible)',
			'show_in_rest' => true,
			'show_in_mcp'  => true,
			'fields'       => array(
				'release_version' => array(
					'type'  => 'text',
					'title' => 'Version',
					'desc'  => 'e.g., 1.2.0',
				),
				'release_date'    => array(
					'type'  => 'date',
					'title' => 'Release Date',
				),
				'release_notes'   => array(
					'type'  => 'textarea',
					'title' => 'Release Notes',
					'desc'  => 'Public-facing notes',
				),
			),
		),
		'release_internal' => array(
			'id'           => 'release_internal',
			'title'        => 'Internal Info (MCP-hidden, REST-visible)',
			'show_in_rest' => true,
			'show_in_mcp'  => false, // Opt-out: REST clients can read this, MCP clients cannot
			'fields'       => array(
				'release_build_number'     => array(
					'type'  => 'text',
					'title' => 'Build Number',
					'desc'  => 'Internal build identifier',
				),
				'release_deployment_notes' => array(
					'type'  => 'textarea',
					'title' => 'Deployment Notes',
					'desc'  => 'Internal deployment checklist and notes',
				),
			),
		),
	),
	'ai_context' => array(
		'model_purpose'        => 'Track software releases with strict governance: drafts only, no publish or delete from AI clients, editorial review required.',
		'allowed_statuses'     => array( 'draft' ), // AI clients cannot publish
		'forbidden_actions'    => array( 'publish', 'delete' ),
		'require_human_review' => true,
		/*
		 * Each rule set must be a LIST OF STRINGS, not a map of structured constraints.
		 * `AiContextProvider::field_rules()` keeps only string members
		 * (`array_filter( $rules, 'is_string' )`) and drops any non-array value outright, so an
		 * associative form is silently mangled rather than rejected. This model previously declared
		 * `array( 'required' => true, 'min_length' => 10, ... )`, which reached AI clients as
		 * `release_notes => []` — every rule discarded, no warning. Caught by the JSON Schema.
		 */
		'field_rules'          => array(
			'release_version' => array(
				'Required. Use semantic versioning, for example 1.2.0.',
				'Keep it under 20 characters.',
			),
			'release_date'    => array(
				'Required. Use the YYYY-MM-DD format.',
			),
			'release_notes'   => array(
				'Required. Write at least a sentence; 5000 characters maximum.',
				'Be clear and concise. Describe what changed, not how it was implemented.',
			),
		),
		'editorial_guidelines' => 'Releases must be reviewed by a human before publishing. Version numbers must follow semantic versioning. Release notes must be clear and concise.',
	),
);
