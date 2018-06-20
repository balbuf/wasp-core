<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class MetaBoxes extends AbstractHandler {

	protected $logger;

	public function getDefaults($property) {
		return [
			'default' => [
				'id' => '{{ prop[1] }}',
				'title' => null,
				'callback' => [],
				'screen' => [], // screen IDs
				'post_types' => [],
				'context' => 'advanced',
				'priority' => 'default',
				'callback_args' => [],
				'remove' => false,
			],
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $args) {
			// post types can be set either in screen or post_types
			$args['screen'] = array_unique(array_merge((array) $args['screen'], $args['post_types']));

			// handle meta box removals
			if ($args['remove']) {
				foreach ($args['screen'] as $screen) {
					$transformer->outputExpression->addExpression(
						$transformer->create('FunctionExpression', [
							'name' => 'remove_meta_box',
							'args' => [$args['id'], $screen, $args['context']],
						]),
						['hook' => 'admin_menu']
					);
				}
			} else {
				// convert php callable representation to a proper function body array
				if (is_string($args['callback']) || (is_array($args['callback']) && array_keys($args['callback']) === [0, 1])) {
					$args['callback'] = [
						'type' => 'callable',
						'body' => $args['callback'],
					];
				}

				// add meta box expression
				$transformer->outputExpression->addExpression(
					$transformer->create('FunctionExpression', [
						'name' => 'add_meta_box',
						'args' => [
							$args['id'],
							$transformer->create('TranslatableTextExpression', [
								'text' => $args['title'],
							]),
							$transformer->create('FunctionDeclaration', [
								'args' => ['post', 'callback_args'],
							] + $args['callback']),
							$args['screen'],
							$args['context'],
							$args['priority'],
							$args['callback_args'],
						],
					]),
					['hook' => 'add_meta_boxes']
				);
			}
		}
	}

}
