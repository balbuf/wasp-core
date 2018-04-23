<?php

namespace OomphInc\WASP\Core\Handlers;

class Styles extends Scripts {

	protected $assetType = 'style';

	/**
	 * {@inheritdoc}
	 */
	public function getDefaults($property) {
		return [
			'default' => [
				'handle' => '{{ prop[1] }}',
				'enqueue' => true,
				'dequeue' => null,
				'version' => true,
				'dependencies' => [],
				'media' => 'all',
				'override' => false,
				'data' => null, // for wp_style_add_data
				'inline' => null, // wp_add_inline_style
			],
		];
	}

	/**
	 * Get the args used for the enqueue function call.
	 * @param  array $asset asset array
	 * @return array        args array
	 */
	protected function getArgs($asset) {
		// start with the handle
		$args = [$asset['handle']];

		// if there is a src, add all the rest
		if (!empty($asset['src'])) {
			array_push($args, $this->getSrcExpression($asset['src']), $asset['dependencies'], $asset['version'], $asset['media']);
		}

		return $args;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doCustomization($asset, &$expression) {
		// additional customization expressions for this asset
		$expressions = [];

		if (is_array($asset['data'])) {
			foreach ($asset['data'] as $key => $value) {
				$expressions[] = $this->transformer->create('FunctionExpression', [
					'name' => 'wp_style_add_data',
					'args' => [$asset['handle'], $key, $value],
				]);
			}
		}

		// add inline style block
		if (!empty($asset['inline'])) {
			$expressions[] = $this->transformer->create('FunctionExpression', [
				'name' => 'wp_add_inline_style',
				'args' => [$asset['handle'], $asset['inline']],
			]);
		}

		// do we have any expressions?
		if (empty($expressions)) {
			return;
		}

		// add the main expression to the front
		array_unshift($expressions, $expression);

		$expression = $this->transformer->create('CompositeExpression', [
			'expressions' => $expressions,
		]);
	}

}
