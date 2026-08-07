<?php
/**
 * Recipe — all 44 Codestar field types.
 *
 * The meta field gallery. Every field type the framework supports, grouped by kind, each
 * annotated with the JSON-schema-ish type it maps to in `normalized.fields` (the shape MCP
 * clients consume).
 *
 * Enable this model from the "Demo Models" settings section. Its sole purpose is showing what
 * can go in a meta field and what each one stores. For how these combine with admin cols,
 * filters, frontend rendering, or AI context, see `book`.
 *
 * @package Saltus\WP\Plugin\Saltus\PluginFrameworkDemo
 */

if ( ! saltus_demo_model_enabled( 'recipe' ) ) {
	return array();
}

return array(
	'type'    => 'cpt',
	'name'    => 'recipe',
	'labels'  => array(
		'has_one'  => 'Recipe',
		'has_many' => 'Recipes',
	),
	'options' => array(
		'menu_icon' => 'dashicons-carrot',
		'supports'  => array( 'title', 'editor', 'thumbnail' ),
	),
	'meta'    => array(
		'recipe_fields' => array(
			'id'       => 'recipe_fields',
			'title'    => 'Recipe Fields',
			'sections' => array(

				'text_like'  => array(
					'title'  => 'Text-like',
					'icon'   => 'fa fa-font',
					'fields' => array(
						'text_field'        => array(
							'type'  => 'text',
							'title' => 'Text',
							'desc'  => 'Maps to: string',
						),
						'textarea_field'    => array(
							'type'  => 'textarea',
							'title' => 'Textarea',
							'desc'  => 'Maps to: string',
						),
						'number_field'      => array(
							'type'  => 'number',
							'title' => 'Number',
							'desc'  => 'Maps to: number',
						),
						'spinner_field'     => array(
							'type'  => 'spinner',
							'title' => 'Spinner',
							'desc'  => 'Maps to: string (numeric string)',
							'unit'  => 'px',
						),
						'slider_field'      => array(
							'type'  => 'slider',
							'title' => 'Slider',
							'desc'  => 'Maps to: string (numeric string)',
							'min'   => 0,
							'max'   => 100,
							'step'  => 5,
							'unit'  => '%',
						),
						'code_editor_field' => array(
							'type'     => 'code_editor',
							'title'    => 'Code Editor',
							'desc'     => 'Maps to: string',
							'settings' => array(
								'theme' => 'dracula',
								'mode'  => 'htmlmixed',
							),
						),
						'wp_editor_field'   => array(
							'type'  => 'wp_editor',
							'title' => 'WP Editor',
							'desc'  => 'Maps to: string (HTML)',
						),
					),
				),

				'choice'     => array(
					'title'  => 'Choice',
					'icon'   => 'fa fa-check-square-o',
					'fields' => array(
						'select_field'       => array(
							'type'    => 'select',
							'title'   => 'Select',
							'desc'    => 'Maps to: array (when multiple) or string',
							'options' => array(
								'opt1' => 'Option 1',
								'opt2' => 'Option 2',
								'opt3' => 'Option 3',
							),
						),
						'radio_field'        => array(
							'type'    => 'radio',
							'title'   => 'Radio',
							'desc'    => 'Maps to: string',
							'options' => array(
								'yes' => 'Yes',
								'no'  => 'No',
							),
						),
						'checkbox_field'     => array(
							'type'  => 'checkbox',
							'title' => 'Checkbox',
							'desc'  => 'Maps to: string ("1" when checked, "" when not)',
						),
						'switcher_field'     => array(
							'type'  => 'switcher',
							'title' => 'Switcher',
							'desc'  => 'Maps to: string ("1" or "")',
						),
						'button_set_field'   => array(
							'type'    => 'button_set',
							'title'   => 'Button Set',
							'desc'    => 'Maps to: string',
							'options' => array(
								'left'   => 'Left',
								'center' => 'Center',
								'right'  => 'Right',
							),
						),
						'image_select_field' => array(
							'type'    => 'image_select',
							'title'   => 'Image Select',
							'desc'    => 'Maps to: string',
							'options' => array(
								'layout1' => plugin_dir_url( __FILE__ ) . '../../assets/images/layout-1.png',
								'layout2' => plugin_dir_url( __FILE__ ) . '../../assets/images/layout-2.png',
							),
						),
						'palette_field'      => array(
							'type'    => 'palette',
							'title'   => 'Palette',
							'desc'    => 'Maps to: string',
							'options' => array(
								'set1' => array( '#1abc9c', '#2ecc71', '#3498db' ),
								'set2' => array( '#e74c3c', '#e67e22', '#f39c12' ),
							),
						),
					),
				),

				'media'      => array(
					'title'  => 'Media',
					'icon'   => 'fa fa-picture-o',
					'fields' => array(
						'media_field'   => array(
							'type'  => 'media',
							'title' => 'Media',
							'desc'  => 'Maps to: array (id, url, thumbnail, etc.)',
						),
						'upload_field'  => array(
							'type'  => 'upload',
							'title' => 'Upload',
							'desc'  => 'Maps to: string (URL)',
						),
						'gallery_field' => array(
							'type'  => 'gallery',
							'title' => 'Gallery',
							'desc'  => 'Maps to: string (comma-separated IDs)',
						),
					),
				),

				'design'     => array(
					'title'  => 'Design',
					'icon'   => 'fa fa-paint-brush',
					'fields' => array(
						'color_field'       => array(
							'type'  => 'color',
							'title' => 'Color',
							'desc'  => 'Maps to: string (hex)',
						),
						'color_group_field' => array(
							'type'    => 'color_group',
							'title'   => 'Color Group',
							'desc'    => 'Maps to: object',
							'options' => array(
								'color1' => 'Primary',
								'color2' => 'Secondary',
							),
						),
						'background_field'  => array(
							'type'  => 'background',
							'title' => 'Background',
							'desc'  => 'Maps to: object',
						),
						'border_field'      => array(
							'type'  => 'border',
							'title' => 'Border',
							'desc'  => 'Maps to: object',
						),
						'dimensions_field'  => array(
							'type'  => 'dimensions',
							'title' => 'Dimensions',
							'desc'  => 'Maps to: string (serialized array)',
						),
						'spacing_field'     => array(
							'type'  => 'spacing',
							'title' => 'Spacing',
							'desc'  => 'Maps to: string (serialized array)',
						),
						/*
						 * This field is why `Plugin\CodestarCompat` exists. Codestar resolves field
						 * classes by string name, so it asks for the un-prefixed
						 * `CSF_Field_typography`; Strauss's alias autoloader fails to define it, but
						 * still defines a `csf_get_google_fonts()` shim pointing at a prefixed target
						 * that was never loaded. Without the compat shim, rendering this field is a
						 * hard fatal. See ROADMAP Phase 4 task 1.
						 */
						'typography_field'  => array(
							'type'  => 'typography',
							'title' => 'Typography',
							'desc'  => 'Maps to: string (serialized array)',
						),
						'link_color_field'  => array(
							'type'  => 'link_color',
							'title' => 'Link Color',
							'desc'  => 'Maps to: string (serialized array)',
						),
					),
				),

				'structural' => array(
					'title'  => 'Structural',
					'icon'   => 'fa fa-sitemap',
					'fields' => array(
						'group_field'     => array(
							'type'   => 'group',
							'title'  => 'Group',
							'desc'   => 'Maps to: object',
							'fields' => array(
								'group_text'  => array(
									'type'  => 'text',
									'title' => 'Text in Group',
								),
								'group_color' => array(
									'type'  => 'color',
									'title' => 'Color in Group',
								),
							),
						),
						'fieldset_field'  => array(
							'type'   => 'fieldset',
							'title'  => 'Fieldset',
							'desc'   => 'Maps to: object',
							'fields' => array(
								array(
									'id'    => 'fieldset_text',
									'type'  => 'text',
									'title' => 'Text in Fieldset',
								),
								array(
									'id'    => 'fieldset_number',
									'type'  => 'number',
									'title' => 'Number in Fieldset',
								),
							),
						),
						'repeater_field'  => array(
							'type'   => 'repeater',
							'title'  => 'Repeater',
							'desc'   => 'Maps to: array',
							'fields' => array(
								array(
									'id'    => 'rep_title',
									'type'  => 'text',
									'title' => 'Title',
								),
								array(
									'id'    => 'rep_content',
									'type'  => 'textarea',
									'title' => 'Content',
								),
							),
						),
						'accordion_field' => array(
							'type'       => 'accordion',
							'title'      => 'Accordion',
							'desc'       => 'Maps to: string (UI-only wrapper, no stored value)',
							'accordions' => array(
								array(
									'title'  => 'Section 1',
									'fields' => array(
										array(
											'id'    => 'acc_field1',
											'type'  => 'text',
											'title' => 'Field 1',
										),
									),
								),
								array(
									'title'  => 'Section 2',
									'fields' => array(
										array(
											'id'    => 'acc_field2',
											'type'  => 'text',
											'title' => 'Field 2',
										),
									),
								),
							),
						),
						'tabbed_field'    => array(
							'type'  => 'tabbed',
							'title' => 'Tabbed',
							'desc'  => 'Maps to: string (stores each tab field separately)',
							'tabs'  => array(
								array(
									'title'  => 'Tab 1',
									'fields' => array(
										array(
											'id'    => 'tab_field1',
											'type'  => 'text',
											'title' => 'Field 1',
										),
									),
								),
								array(
									'title'  => 'Tab 2',
									'fields' => array(
										array(
											'id'    => 'tab_field2',
											'type'  => 'textarea',
											'title' => 'Field 2',
										),
									),
								),
							),
						),
						'sortable_field'  => array(
							'type'   => 'sortable',
							'title'  => 'Sortable',
							'desc'   => 'Maps to: string (serialized array)',
							'fields' => array(
								array(
									'id'    => 'sort1',
									'type'  => 'text',
									'title' => 'Item 1',
								),
								array(
									'id'    => 'sort2',
									'type'  => 'text',
									'title' => 'Item 2',
								),
							),
						),
						'sorter_field'    => array(
							'type'     => 'sorter',
							'title'    => 'Sorter',
							'desc'     => 'Maps to: string (serialized array)',
							'enabled'  => array(
								'item1' => 'Item 1',
								'item2' => 'Item 2',
							),
							'disabled' => array(
								'item3' => 'Item 3',
							),
						),
					),
				),

				'other'      => array(
					'title'  => 'Other',
					'icon'   => 'fa fa-ellipsis-h',
					'fields' => array(
						'date_field'     => array(
							'type'  => 'date',
							'title' => 'Date',
							'desc'  => 'Maps to: string (Y-m-d)',
						),
						'datetime_field' => array(
							'type'  => 'datetime',
							'title' => 'DateTime',
							'desc'  => 'Maps to: string (Y-m-d H:i)',
						),
						'icon_field'     => array(
							'type'  => 'icon',
							'title' => 'Icon',
							'desc'  => 'Maps to: string (icon class)',
						),
						'link_field'     => array(
							'type'  => 'link',
							'title' => 'Link',
							'desc'  => 'Maps to: string (serialized array with url/text/target)',
						),
						'map_field'      => array(
							'type'  => 'map',
							'title' => 'Map',
							'desc'  => 'Maps to: object',
						),
						'callback_field' => array(
							'type'     => 'callback',
							'title'    => 'Callback',
							'desc'     => 'Maps to: string (render-only, no stored value)',
							'function' => function () {
								echo '<p>This is a custom callback field.</p>';
							},
						),
						'backup_field'   => array(
							'type'  => 'backup',
							'title' => 'Backup',
							'desc'  => 'Maps to: string (import/export UI, no meta storage)',
						),
					),
				),

				'content_ui' => array(
					'title'  => 'Content/UI-only',
					'icon'   => 'fa fa-info-circle',
					'fields' => array(
						'heading_field'    => array(
							'type'    => 'heading',
							'content' => 'This is a heading',
						),
						'subheading_field' => array(
							'type'    => 'subheading',
							'content' => 'This is a subheading',
						),
						'content_field'    => array(
							'type'    => 'content',
							'content' => 'This is arbitrary HTML content.',
						),
						'notice_field'     => array(
							'type'    => 'notice',
							'style'   => 'success',
							'content' => 'This is a notice.',
						),
						'submessage_field' => array(
							'type'    => 'submessage',
							'style'   => 'info',
							'content' => 'This is a submessage.',
						),
					),
				),

			),
		),
	),
);
