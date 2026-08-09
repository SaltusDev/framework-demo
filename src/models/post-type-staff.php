<?php
/**
 * Staff — settings pages.
 *
 * Demonstrates multi-section settings with custom `menu_parent`, `capability`, `menu_title` vs
 * `title`, section icons, and the `tabbed` field type for grouped options within a section.
 *
 * Reads settings back on the frontend to show the round trip. For how settings combine with meta,
 * admin cols, or AI context, see `book`.
 *
 * Enable this model from the "Demo Models" settings section.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'staff' ) ) {
	return array();
}

return array(
	'type'     => 'cpt',
	'name'     => 'staff',
	'labels'   => array(
		'has_one'  => 'Staff Member',
		'has_many' => 'Staff',
	),
	'options'  => array(
		'menu_icon' => 'dashicons-groups',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	),
	'meta'     => array(
		'staff_details' => array(
			'id'     => 'staff_details',
			'title'  => 'Staff Details',
			'fields' => array(
				'staff_role'  => array(
					'type'  => 'text',
					'title' => 'Role',
					'desc'  => 'e.g., Senior Developer, Designer',
				),
				'staff_bio'   => array(
					'type'  => 'textarea',
					'title' => 'Bio',
				),
				'staff_email' => array(
					'type'  => 'text',
					'title' => 'Email',
				),
			),
		),
	),
	'settings' => array(
		/*
		 * The settings key IS the option name — the framework passes it straight to
		 * `CSF::createOptions()`. It is slug-qualified deliberately: as the bare `staff-options` it
		 * contained no `framework-demo` string, so the renamer left it verbatim and every generated
		 * plugin wrote to the same generic row. Uninstalling any one of them then deleted the others'
		 * settings. Keep the plugin slug in any settings id so rebranding namespaces it.
		 */
		'framework-demo-staff-options' => array(
			'id'          => 'framework-demo-staff-options',
			'title'       => 'Staff Directory Settings',
			'menu_title'  => 'Directory Options',
			'menu_parent' => 'options-general.php', // Under Settings, not the Staff submenu
			'capability'  => 'manage_options',
			'sections'    => array(
				'display'  => array(
					'title'  => 'Display Settings',
					'desc'   => 'Control how the staff directory appears on the frontend',
					'icon'   => 'fa fa-desktop',
					'fields' => array(
						'staff_per_page'   => array(
							'type'    => 'number',
							'title'   => 'Staff per Page',
							'desc'    => 'Number of staff members to show per page',
							'default' => 10,
						),
						'staff_show_email' => array(
							'type'    => 'switcher',
							'title'   => 'Show Email Addresses',
							'desc'    => 'Display email addresses publicly',
							'default' => false,
						),
						'staff_layout'     => array(
							'type'    => 'radio',
							'title'   => 'Layout',
							'options' => array(
								'grid' => 'Grid',
								'list' => 'List',
							),
							'default' => 'grid',
						),
					),
				),
				'styling'  => array(
					'title'  => 'Styling',
					'desc'   => 'Visual appearance settings',
					'icon'   => 'fa fa-paint-brush',
					'fields' => array(
						'staff_colors' => array(
							'type'  => 'tabbed',
							'title' => 'Color Scheme',
							'tabs'  => array(
								array(
									'title'  => 'Primary',
									'fields' => array(
										array(
											'id'      => 'primary_bg',
											'type'    => 'color',
											'title'   => 'Background',
											'default' => '#ffffff',
										),
										array(
											'id'      => 'primary_text',
											'type'    => 'color',
											'title'   => 'Text',
											'default' => '#333333',
										),
									),
								),
								array(
									'title'  => 'Secondary',
									'fields' => array(
										array(
											'id'      => 'secondary_bg',
											'type'    => 'color',
											'title'   => 'Background',
											'default' => '#f5f5f5',
										),
										array(
											'id'      => 'secondary_text',
											'type'    => 'color',
											'title'   => 'Text',
											'default' => '#666666',
										),
									),
								),
							),
						),
					),
				),
				'advanced' => array(
					'title'  => 'Advanced',
					'desc'   => 'Developer options',
					'icon'   => 'fa fa-cogs',
					'fields' => array(
						'staff_cache_enabled'  => array(
							'type'    => 'switcher',
							'title'   => 'Enable Caching',
							'desc'    => 'Cache staff directory queries',
							'default' => true,
						),
						'staff_cache_duration' => array(
							'type'       => 'number',
							'title'      => 'Cache Duration (seconds)',
							'default'    => 3600,
							'dependency' => array( 'staff_cache_enabled', '==', 'true' ),
						),
					),
				),
			),
		),
	),
);
