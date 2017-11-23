<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;

class MenuLocations extends AbstractHandler {

	public function handle($transformer, $config, $property) {
		$config = array_map(function($label) use ($transformer) {
			// menu label must be escaped bc WordPress doesn't do it!
			return $transformer->create('FunctionExpression', [
				'name' => 'esc_html',
				'args' => [$transformer->create('TranslatableTextExpression', ['text' => $label])],
				'inline' => true,
			]);
		}, $config);

		$transformer->outputExpression->addExpression(
			$transformer->create('FunctionExpression', [
				'name' => 'register_nav_menus',
				'args' => [$transformer->create('ArrayExpression', ['array' => $config])],
			]),
			['hook' => 'after_setup_theme']
		);
	}

}
