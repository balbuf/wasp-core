<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class WidgetAreas extends AbstractHandler {

	public function getDefaults($property) {
		return [
			'default' => [
				'id' => '{{ top.wasp.prefix() ? top.wasp.prefix ~ "-" : "" }}{{ prop[1] }}-widget',
				'class' => '{{ this.id }}',
			],
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $id => $args) {
			// wrap text in translation functions
			foreach (['name', 'description'] as $key) {
				if (isset($args[$key])) {
					$args[$key] = $transformer->create('TranslatableTextExpression', [
						'text' => $args[$key],
					]);
				}
			}

			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_sidebar',
					'args' => [$args],
				]),
				['hook' => 'widgets_init']
			);
		}
	}

}
