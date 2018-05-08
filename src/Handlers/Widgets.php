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
				$args = isset($this->methods[$method]) ? $this->methods[$method] : $options['args'];

				switch ($options['type']) {
					// straight callable
					case 'callable':
						// convert array callable to string callable
						if (is_array($options['body']) && array_keys($options['body']) === [0, 1]) {
							$options['body'] = implode('::', $options['body']);
						}

						$expression = $transformer->create('RawExpression', [
							'expression' => 'return ' . $options['body'] . '( ' . preg_replace('/^[^$].+$/', '$$0', $args) . ' );',
						]);
						break;

					// template file
					case 'template':
						$expression = $transformer->create('RawExpression', [
							'expression' => 'return require ' . $transformer->outputExpression->convertPath($options['body']) . ';',
						]);
						break;

					// raw php
					case 'php':
						$expression = $transformer->create('RawExpression', [
							'expression' => $options['body'],
						]);
						break;

					// html
					case 'html':
						$expression = $transformer->create('RawExpression', [
							'expression' => 'echo' . var_export($options['body'], true) . ';',
						]);
						break;

					case 'return':
						$expression = $transformer->create('RawExpression', [
							'expression' => 'return ' . $transformer->compile($options['body']) . ';',
						]);
						break;
				}

				$class->expressions[] = $transformer->create('FunctionDeclaration', [
					'name' => $method,
					'methodModifiers' => $options['visibility'],
					'args' => $args,
					'expressions' => [$expression],
				]);
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
