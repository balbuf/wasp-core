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
		$labels = [];

		foreach ($config as $settings) {
			// we must have both a width and height!
			if (!isset($settings['width'], $settings['height'])) {
				$this->logger->warning("Error: missing width or height for image size '{$settings['name']}'");
				continue;
			}

			// is label is provided?
			if (!empty($settings['label'])) {
				$labels[$settings['name']] = $settings['label'];
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

		if (count($labels)) {
			$transformer->outputExpression->addExpression(
				$transformer->create('HookExpression', [
					'name' => 'image_size_names_choose',
					'expressions' => [
						$transformer->create('CompositeExpression', [
							'expressions' => [
								$transformer->create('RawExpression', [
									'expression' => 'return ',
								]),
								array_map(function($label) use ($transformer) {
									return $transformer->create('TranslatableTextExpression', ['text' => $label]);
								}, $labels),
								$transformer->create('RawExpression', [
									'expression' => ";\n",
								]),
							],
						]),
					],
					'function' => 'add_filter',
				])
			);
		}
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
