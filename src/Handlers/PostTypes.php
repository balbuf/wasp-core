<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class PostTypes extends DependentHandler {

	protected $logger;
	// built-in post types
	protected $builtins = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset'];
	// properties to exclude from the args array
	protected $propsExclude = ['register', 'extend', 'post_type', 'label'];

	public function getDefaults($property) {
		return [
			'default' => [
				// default to not registering a post if it is a known built-in
				'register' => '{% do output.setValue(this.post_type.getValue() not in ' . json_encode( $this->builtins ) . ') %}',
				'post_type' => '{{ prop[1] }}',
				'labels' => [
					'name' => '{{ this.parent.label }}',
					'singular_name' => '{{ this.name | slice(0, -1) }}',
					'add_new_item' => 'Add New {{ this.singular_name }}',
					'edit_item' => 'Edit {{ this.singular_name }}',
					'new_item' => 'New {{ this.singular_name }}',
					'view_item' => 'View {{ this.singular_name }}',
					'view_items' => 'View {{ this.name }}',
					'search_items' => 'Search {{ this.name }}',
					'not_found' => 'No {{ this.name | lower }} found',
					'not_found_in_trash' => 'No {{ this.name | lower }} in Trash',
					'parent_item_colon' => 'Parent {{ this.singular_name }}:',
					'all_items' => 'All {{ this.name }}',
					'archives' => '{{ this.singular_name }} Archives',
					'attributes' => '{{ this.singular_name }} Attributes',
					'insert_into_item' => 'Insert into {{ this.singular_name | lower }}',
					'uploaded_to_this_item' => 'Uploaded to this {{ this.singular_name | lower }}',
					'featured_image' => 'Featured Image',
					'set_featured_image' => 'Set {{ this.featured_image | lower }}',
					'remove_featured_image' => 'Remove {{ this.featured_image | lower }}',
					'use_featured_image' => 'Use as {{ this.featured_image | lower }}',
				],
				'show_ui' => true,
				'public' => true,
				'has_archive' => true,
				'menu_position' => 20,
				'map_meta_cap' => true,
				'supports' => [
					'title',
					'editor',
					'thumbnail',
				],
				'rewrite' => [
					'with_front' => false,
					'feeds' => true,
				],
			],
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $args) {
			$postType = $args['post_type'];

			// don't register, just pluck out some details to process
			if (!$args['register']) {
				// add post type support
				if (!empty($args['supports'])) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'add_post_type_support',
							'args' => [$postType, $args['supports']],
						]),
						['hook' => 'init', 'priority' => 999]
					);
				}

				// assign taxonomies
				if (!empty($args['taxonomies'])) {
					foreach ($args['taxonomies'] as $taxonomy) {
						$transformer->outputExpression->addExpression(
							$transformer->create('FunctionExpression', [
								'name' => 'register_taxonomy_for_object_type',
								'args' => [$taxonomy, $postType],
							]),
							['hook' => 'init', 'priority' => 999]
						);
					}
				}

				// move on to the next without registering
				continue;
			}

			// we need the post type name at a minimum
			if (empty($args['labels']['name'])) {
				$this->logger->warning("Could not determine name for '{$postType}' post type. Skipping.");
				continue;
			}

			// remove some keys from the args array
			$args = array_diff_key($args, array_flip($this->propsExclude));

			// wrap labels in translatable text expressions
			$args['labels'] = $transformer->create('ArrayExpression', [
				'array' => array_map(function($label) use ($transformer) {
					return $transformer->create('TranslatableTextExpression', ['text' => $label]);
				}, $args['labels'])
			]);

			// add the function call
			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_post_type',
					'args' => [$postType, $args],
				]),
				['hook' => 'init']
			);
		}
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
