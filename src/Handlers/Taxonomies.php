<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class Taxonomies extends DependentHandler {

	protected $logger;

	public function getDefaults($property) {
		return [
			'default' => [
				'taxonomy' => '{{ prop[1] }}',
				'post_types' => null,
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
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $args) {
			$taxonomy = $args['taxonomy'];
			$postTypes = $args['post_types'];

			// we need the taxonomy name at a minimum
			if (empty($args['labels']['name'])) {
				$this->logger->warning("Could not determine name for '{$taxonomy}' taxonomy. Skipping.");
				continue;
			}

			// remove some keys from the args array
			$args = array_diff_key($args, array_flip(['taxonomy', 'extend', 'post_types', 'label']));

			// wrap labels in translatable text expressions
			$args['labels'] = $transformer->create('ArrayExpression', [
				'array' => array_map(function($label) use ($transformer) {
					return $transformer->create('TranslatableTextExpression', ['text' => $label]);
				}, $args['labels'])
			]);

			// add the function call
			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_taxonomy',
					'args' => [$taxonomy, $postTypes, $args],
				]),
				['hook' => 'init']
			);
		}
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
