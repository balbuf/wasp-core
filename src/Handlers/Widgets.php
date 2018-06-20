<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class Widgets extends AbstractHandler {

	protected $methods = [
		'widget' => ['args', 'instance'],
		'update' => ['new_instance', 'old_instance'],
		'form' => ['instance'],
	];

	/**
	 * {@inheritdoc}
	 */
	public function getDefaults($property) {
		return [
			'default' => [
				'id_base' => '{{ prop[1] }}',
				'name' => null,
				'description' => null,
				'methods' => [
					'default' => [
						'args' => [],
						'name' => '{{ prop[1] }}',
						'visibility' => 'public',
						'type' => 'callable',
						'body' => null,
					],
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle($transformer, $config, $property) {
		foreach ($config as $widget) {
			// create a class name
			$className = 'Wasp_' . preg_replace('/\W/', '_', $transformer->getProperty('wasp', 'prefix') . '_' . $widget['id_base']) . '_Widget';
			// create the widget class
			$class = $transformer->create('BlockExpression', [
				'name' => "class {$className} extends WP_Widget",
				'expressions' => [
					// add the constructor
					$transformer->create('FunctionDeclaration', [
						'name' => '__construct',
						'methodModifiers' => 'public',
						'expressions' => [
							$transformer->create('FunctionExpression', [
								'name' => 'parent::__construct',
								'args' => [
									$widget['id_base'],
									$widget['name'],
									['description' => $widget['description']],
								],
							]),
						],
					]),
				],
			]);

			// create the methods
			foreach ($widget['methods'] as $method => $options) {
				// string is shorthand for callable
				if (is_string($options)) {
					$options = [
						'type' => 'callable',
						'body' => $options,
					];
				}

				if (isset($this->methods[$method])) {
					$options['args'] = $this->methods[$method];
				}

				// the 'visibility' property can be used instead of 'methodModifiers'
				if (isset($options['visibility']) && !isset($options['methodModifiers'])) {
					$options['methodModifiers'] = $options['visibility'];
				}

				$class->expressions[] = $transformer->create('FunctionDeclaration', [
					'name' => $method,
				] + $options);
			}

			$transformer->outputExpression->addExpression($class);

			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_widget',
					'args' => [$className],
				]),
				['hook' => 'widgets_init']
			);
		}
	}

}
