<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class Constants extends AbstractHandler {

	public function handle($transformer, $config, $property) {
		foreach ($config as $constant => $value) {
			$transformer->outputExpression->addExpression(
				$transformer->create('BlockExpression', [
					'name' => 'if',
					'parenthetical' => $transformer->create('FunctionExpression', [
						'name' => '!defined',
						'args' => [$constant],
						'inline' => true,
					]),
					'expressions' => [
						$transformer->create('FunctionExpression', [
							'name' => 'define',
							'args' => [$constant, $value],
						])
					],
				]),
				['priority' => 1]
			);
		}
	}

}
