<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class ThemeSupports extends AbstractHandler {

	public function handle($transformer, $config, $property) {
		foreach ($config as $feature) {
			$expression = $transformer->create('FunctionExpression', [
				'name' => 'add_theme_support',
			]);

			if (is_string($feature)) {
				$expression->args = [$feature];
			} else if (is_array($feature) && count($feature) === 1) {
				$expression->args = reset($feature);
				array_unshift($expression->args, key($feature));
			} else {
				continue;
			}

			$transformer->outputExpression->addExpression($expression, ['hook' => 'after_setup_theme']);
		}
	}

}
