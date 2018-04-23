<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class PostTypes extends DependentHandler {

	protected $logger;
	// built-in post types
	protected $builtins = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset'];

	public function getDefaults($property) {
		return [
			'default' => [
				'post_type' => '{{ prop[1] }}',
				'register' => [
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
				'taxonomies' => [],
				'supports' => null,
				'unregister' => false,
				'remove_support' => [],
				'remove_taxonomies' => [],
			],
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $args) {
			$postType = $args['post_type'];

			// register the post type?
			if (!empty($args['register'])) {
				// we need the post type name at a minimum
				if (empty($args['register']['labels']['name'])) {
					$this->logger->warning("Could not determine name for '{$postType}' post type. Skipping.");
					continue;
				}

				// wrap labels in translatable text expressions
				$args['register']['labels'] = $transformer->create('ArrayExpression', [
					'array' => array_map(function($label) use ($transformer) {
						return $transformer->create('TranslatableTextExpression', ['text' => $label]);
					}, $args['register']['labels'])
				]);

				// add the function call
				$transformer->outputExpression->addExpression(
					$transformer->create('FunctionExpression', [
						'name' => 'register_post_type',
						'args' => [$postType, $args['register']],
					]),
					['hook' => 'init']
				);
			} else if ($args['unregister']) {
				$transformer->outputExpression->addExpression(
					$transformer->create('FunctionExpression', [
						'name' => 'unregister_post_type',
						'args' => [$postType],
					]),
					['hook' => 'init', 'priority' => 999]
				);
			}

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

			// remove post type support
			if (!empty($args['remove_support'])) {
				foreach ((array) $args['remove_support'] as $feature) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'remove_post_type_support',
							'args' => [$postType, $feature],
						]),
						['hook' => 'init', 'priority' => 999]
					);
				}
			}

			// assign taxonomies
			if (is_array($args['taxonomies'])) {
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

			// unassign taxonomies
			if (is_array($args['remove_taxonomies'])) {
				foreach ($args['remove_taxonomies'] as $taxonomy) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'unregister_taxonomy_for_object_type',
							'args' => [$taxonomy, $postType],
						]),
						['hook' => 'init', 'priority' => 999]
					);
				}
			}
		}
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
