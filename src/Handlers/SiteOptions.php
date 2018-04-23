<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class SiteOptions extends AbstractHandler {

	public function handle($transformer, $config, $property) {
		foreach ($config as $option => $value) {
			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'update_option',
					'args' => [$option, $value],
				]),
				['lazy' => true]
			);
		}
	}

}
