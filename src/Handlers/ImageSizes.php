<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class ImageSizes extends DependentHandler {

	protected $logger;

	public function getDefaults($property) {
		return [
			'default' => [
				'crop' => true,
				'name' => '{{ prop[1] }}', // name defaults to the key
			],
		];
	}

	public function handle($transformer, $config, $property) {
		foreach ($config as $settings) {
			// we must have both a width and height!
			if (!isset($settings['width'], $settings['height'])) {
				$this->logger->warning("Error: missing width or height for image size '{$settings['name']}'");
				continue;
			}

			// assemble function args
			$args = [$settings['name'], $settings['width'], $settings['height'], $settings['crop']];

			$transformer->outputExpression->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'add_image_size',
					'args' => $args,
				]),
				['hook' => 'after_setup_theme']
			);
		}
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
