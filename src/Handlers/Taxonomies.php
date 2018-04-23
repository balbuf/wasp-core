<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class Taxonomies extends DependentHandler {

	protected $logger;

	/**
	 * {@inheritdoc}
	 */
	public function getDefaults($property) {
		return [
			'default' => [
				'taxonomy' => '{{ prop[1] }}',
				'post_types' => null,
				'register' => [
					'labels' => [
						'name' => '{{ this.parent.label }}',
						'singular_name' => '{{ this.name | slice(0, -1) }}',
						'all_items' => 'All {{ this.name }}',
						'edit_item' => 'Edit {{ this.singular_name }}',
						'view_item' => 'View {{ this.singular_name }}',
						'update_item' => 'Update {{ this.singular_name }}',
						'add_new_item' => 'Add New {{ this.singular_name }}',
						'new_item_name' => 'New {{ this.singular_name }} Name',
						'parent_item' => 'Parent {{ this.singular_name }}',
						'parent_item_colon' => '{{ this.parent_item }}:',
						'search_items' => 'Search {{ this.name }}',
						'popular_items' => 'Popular {{ this.name }}',
						'separate_items_with_commas' => 'Separate {{ this.name | lower }} with commas',
						'add_or_remove_items' => 'Add or remove {{ this.name | lower }}',
						'choose_from_most_used' => 'Choose from the most used {{ this.name | lower }}',
						'not_found' => 'No {{ this.name | lower }} found.',
					],
					'show_ui' => true,
					'public' => true,
					'show_tagcloud' => false,
					'rewrite' => [
						'with_front' => false,
						'hierarchical' => true,
					],
				],
				'unregister' => false,
				'remove_post_types' => null,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle($transformer, $config, $property) {
		foreach ($config as $args) {
			// we need the taxonomy name at a minimum
			if (empty($args['register']['labels']['name'])) {
				$this->logger->warning("Could not determine name for '{$taxonomy}' taxonomy. Not registering.");
				$args['register'] = false;
			}

			if (is_array($args['register'])) {
				// wrap labels in translatable text expressions
				$args['register']['labels'] = $transformer->create('ArrayExpression', [
					'array' => array_map(function($label) use ($transformer) {
						return $transformer->create('TranslatableTextExpression', ['text' => $label]);
					}, $args['register']['labels'])
				]);

				// add the function call
				$transformer->outputExpression->addExpression(
					$transformer->create('FunctionExpression', [
						'name' => 'register_taxonomy',
						'args' => [$args['taxonomy'], $args['post_types'], $args['register']],
					]),
					['hook' => 'init']
				);
			} else if ($args['unregister']) {
				$transformer->outputExpression->addExpression(
					$transformer->create('FunctionExpression', [
						'name' => 'unregister_taxonomy',
						'args' => [$args['taxonomy']],
					]),
					['hook' => 'init']
				);
			} else if (is_array($args['post_types'])) {
				foreach ($args['post_types'] as $postType) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'register_taxonomy_for_object_type',
							'args' => [$args['taxonomy'], $postType],
						]),
						['hook' => 'init', 'priority' => 999]
					);
				}
			}

			if (is_array($args['remove_post_types'])) {
				foreach ($args['remove_post_types'] as $postType) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'unregister_taxonomy_for_object_type',
							'args' => [$args['taxonomy'], $postType],
						]),
						['hook' => 'init', 'priority' => 999]
					);
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getRequestedServices() {
		return ['logger'];
	}

}
