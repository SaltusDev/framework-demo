/**
 * Saltus model configuration types.
 *
 * DO NOT EDIT. Regenerate with `php bin/generate-model-schema.php`.
 * Schema version: 1.0.0
 */

/** A Codestar field type. Studio must not offer anything outside this union: the
 *  framework's `matched_fields` map knows only these, and unknown types are ignored. */
export type SaltusFieldType =
  | 'accordion'
  | 'background'
  | 'backup'
  | 'border'
  | 'button_set'
  | 'callback'
  | 'checkbox'
  | 'code_editor'
  | 'color'
  | 'color_group'
  | 'content'
  | 'date'
  | 'datetime'
  | 'dimensions'
  | 'fieldset'
  | 'gallery'
  | 'group'
  | 'heading'
  | 'icon'
  | 'image_select'
  | 'link'
  | 'link_color'
  | 'map'
  | 'media'
  | 'notice'
  | 'number'
  | 'palette'
  | 'radio'
  | 'repeater'
  | 'select'
  | 'slider'
  | 'sortable'
  | 'sorter'
  | 'spacing'
  | 'spinner'
  | 'subheading'
  | 'submessage'
  | 'switcher'
  | 'tabbed'
  | 'text'
  | 'textarea'
  | 'typography'
  | 'upload'
  | 'wp_editor';

export const SALTUS_FIELD_TYPES: readonly SaltusFieldType[] = [
  'accordion',
  'background',
  'backup',
  'border',
  'button_set',
  'callback',
  'checkbox',
  'code_editor',
  'color',
  'color_group',
  'content',
  'date',
  'datetime',
  'dimensions',
  'fieldset',
  'gallery',
  'group',
  'heading',
  'icon',
  'image_select',
  'link',
  'link_color',
  'map',
  'media',
  'notice',
  'number',
  'palette',
  'radio',
  'repeater',
  'select',
  'slider',
  'sortable',
  'sorter',
  'spacing',
  'spinner',
  'subheading',
  'submessage',
  'switcher',
  'tabbed',
  'text',
  'textarea',
  'typography',
  'upload',
  'wp_editor',
] as const;

export type SaltusPostTypeAlias =
  | 'cpt'
  | 'post-type'
  | 'post_type'
  | 'posttype';

export type SaltusTaxonomyAlias =
  | 'cat'
  | 'category'
  | 'tag'
  | 'tax'
  | 'taxonomy';

export type SaltusModelType = SaltusPostTypeAlias | SaltusTaxonomyAlias;

export const SALTUS_MAX_NAME_LENGTH = {
  post: 20,
  taxonomy: 32,
} as const;

/** Minimal shape. The authoritative contract is schema/model.schema.json. */
export interface SaltusModel {
  $schema?: string;
  type: SaltusModelType;
  name?: string;
  supports?: string[];
  block_editor?: boolean;
  associations?: string | string[] | Record<string, unknown>;
  labels?: Record<string, unknown>;
  options?: Record<string, unknown> & { show_in_rest?: boolean; mcp_tools?: boolean };
  features?: Record<string, unknown>;
  meta?: Record<string, unknown>;
  settings?: Record<string, unknown>;
  blocks?: Record<string, unknown>;
  frontend?: boolean | Record<string, unknown>;
  ai_context?: Record<string, unknown>;
}
