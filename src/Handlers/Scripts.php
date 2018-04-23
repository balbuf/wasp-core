<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;
use OomphInc\WASP\FileSystem\FileSystemHelper;

class Scripts extends DependentHandler {

	protected $logger;
	protected $filesystem;
	protected $transformer;
	protected $assetType = 'script';
	// enqueue type to action mapping
	protected $enqueueTypes = [
		'frontend' => 'wp_enqueue_scripts',
		'admin' => 'admin_enqueue_scripts',
		'login' => 'login_enqueue_scripts',
		'block-editor' => 'enqueue_block_editor_assets',
		'block-frontend' => 'enqueue_block_assets',
	];

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
				'in_footer' => false,
				'override' => false,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle($transformer, $config, $property) {
		$enqueueExpressions = [];
		$registerExpressions = [];
		$dequeueExpressions = [];

		foreach ($config as $asset) {
			// normalize the enqueue value
			if ($asset['enqueue']) {
				if (($asset['enqueue'] = $this->normalizeEnqueue($asset['enqueue'])) === null) {
					$this->logger->warning("Unknown enqueue value - skipping {$this->assetType} '{$asset['handle']}'");
					continue;
				}
			} else {
				// just register the asset
				if (!isset($asset['src'])) {
					$this->logger->warning("No src property provided - cannot register {$this->assetType} '{$asset['handle']}'");
					continue;
				}
				$asset['version'] = $this->getVersion($asset);
				$expression = $transformer->create('FunctionExpression', [
					'name' => "wp_register_{$this->assetType}",
					'args' => $this->getArgs($asset),
				]);

				// do we have localization text this asset?
				$this->doCustomization($asset, $expression);
				// should this asset override a previous one?
				$this->doOverride($asset, $expression);
				$registerExpressions[] = $expression;
				continue;
			}

			// normalize the dequeue value
			if ($asset['dequeue']) {
				if (($asset['dequeue'] = $this->normalizeEnqueue($asset['dequeue'])) === null) {
					$this->logger->warning("Unknown dequeue value - skipping {$this->assetType} '{$asset['handle']}'");
					continue;
				}
			} else {
				$asset['dequeue'] = [];
			}

			// remove any dequeue types from the enqueue array
			$asset['enqueue'] = array_diff_key($asset['enqueue'], $asset['dequeue']);
			if (!empty($asset['src'])) {
				$asset['version'] = $this->getVersion($asset);
			}

			// process the enqueue contexts
			foreach ($asset['enqueue'] as $type => $enqueue) {
				// valid enqueue type?
				if (!isset($this->enqueueTypes[$type])) {
					$this->logger->warning("Unknown enqueue type '{$type}' - skipping");
					continue;
				}

				if (!isset($enqueueExpressions[$type])) {
					$enqueueExpressions[$type] = [];
				}

				$expression = $transformer->create('FunctionExpression', [
					'name' => "wp_enqueue_{$this->assetType}",
					'args' => $this->getArgs($asset),
				]);

				// do we have localization text this asset?
				$this->doCustomization($asset, $expression);
				// should this asset override a previous one?
				$this->doOverride($asset, $expression);
				// a string value is a raw condition statement to wrap the script in
				$this->doConditional($enqueue, $expression);

				$enqueueExpressions[$type][] = $expression;
			}

			// process the dequeue contexts
			foreach ($asset['dequeue'] as $type => $dequeue) {
				// valid enqueue type?
				if (!isset($this->enqueueTypes[$type])) {
					$this->logger->warning("Unknown enqueue type '{$type}' - skipping");
					continue;
				}

				if (!isset($dequeueExpressions[$type])) {
					$dequeueExpressions[$type] = [];
				}

				$expression = $transformer->create('FunctionExpression', [
					'name' => "wp_dequeue_{$this->assetType}",
					'args' => [$asset['handle']],
				]);

				// a string value is a raw condition statement to wrap the script in
				$this->doConditional($dequeue, $expression);
				$dequeueExpressions[$type][] = $expression;
			}
		}

		// add the enqueue expressions to the output
		foreach ($enqueueExpressions as $type => $expressions) {
			$transformer->outputExpression->addExpression(
				$transformer->create('CompositeExpression', [
					'expressions' => $expressions,
				]),
				[
					'hook' => $this->enqueueTypes[$type],
					'use' => ['baseUrl'],
				]
			);
		}

		// add the dequeue expressions to the output
		foreach ($dequeueExpressions as $type => $expressions) {
			$transformer->outputExpression->addExpression(
				$transformer->create('CompositeExpression', [
					'expressions' => $expressions,
				]),
				[
					'hook' => $this->enqueueTypes[$type],
					'priority' => 999,
				]
			);
		}

		// add the register expressions to the output
		if (count($registerExpressions)) {
			// variable that will hold the closure for this hook
			$variable = $transformer->create('RawExpression', [
				'expression' => '$' . $this->assetType . 'Register',
			]);

			// create the closure and call for each of the enqueue hook types
			$transformer->outputExpression->addExpression(
				$transformer->create('CompositeExpression', [
					'expressions' => array_merge([
						$variable,
						$transformer->create('RawExpression', [
							'expression' => ' = ',
						]),
						$transformer->create('FunctionDeclaration', [
							'use' => ['baseUrl'],
							'expressions' => $registerExpressions,
						]),
						$transformer->create('RawExpression', [
							'expression' => ";\n",
						]),
					], array_map(function($hook) use ($transformer, $variable) {
						return $transformer->create('FunctionExpression', [
							'name' => 'add_action',
							'args' => [$hook, $variable, 1],
						]);
					}, $this->enqueueTypes)),
				])
			);
		}

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
			array_push($args, $this->getSrcExpression($asset['src']), $asset['dependencies'], $asset['version'], $asset['in_footer']);
		}

		return $args;
	}

	/**
	 * Normalize (en|de)queue values.
	 * @param  mixed $value value passed from config
	 * @return array        normalized value
	 */
	protected function normalizeEnqueue($value) {
		// "true" is a shortcut for frontend
		if ($value === true) {
			return ['frontend' => true];
		// a string is a single enqueue type
		} else if (is_string($value)) {
			// use all enqueue types
			if ($value === 'all') {
				return array_fill_keys(array_keys($this->enqueueTypes), true);
			}
			return [$value => true];
		} else if (is_array($value)) {
			// a non-assoc array is a list of enqueue types
			if (range(0, count($value) -1) === array_keys($value)) {
				return array_fill_keys($value, true);
			// assoc array is the standard format
			} else {
				return array_filter($value);
			}
		}
	}

	/**
	 * Get the version value for the script. If version is set to `true`,
	 * try to read the file and generate a hash to use as the version.
	 * @param  array $asset script definition array
	 * @return mixed
	 */
	protected function getVersion($asset) {
		if ($asset['version'] !== true) {
			return $asset['version'];
		}

		if ($this->isExternal($asset['src'])) {
			// @todo use a curl service
			$version = false;
		} else {
			$this->filesystem->pushd($this->transformer->getVar('rootDir'));
			try {
				$file = $this->filesystem->readFile($asset['src']);
				$version = md5($file);
			} catch (\Exception $e) {
				$this->logger->warning("Could not read file '{$asset['src']}' to generate a version hash.");
				$version = false;
			}
			$this->filesystem->popd();
		}

		return $version;
	}

	/**
	 * Determine whether the asset src is external.
	 * @param  string  $src source URL
	 */
	protected function isExternal($src) {
		return (bool) preg_match('#^(?:https?:)?//#i', $src);
	}

	/**
	 * Get a src expression for use in the enqueue function args.
	 * @param  string $src  source
	 * @return string|object  string if external, compilable object if local
	 */
	protected function getSrcExpression($src) {
		return $this->isExternal($src) ? $src : $this->transformer->create('RawExpression', [
			'expression' => '$baseUrl . ' . var_export(ltrim($src, DIRECTORY_SEPARATOR), true),
		]);
	}

	/**
	 * See if this asset must override an already-registered asset.
	 * @param  array   $asset      asset definition
	 * @param  object  &$expression expression object
	 */
	protected function doOverride($asset, &$expression) {
		if ($asset['override']) {
			$expression = $this->transformer->create('CompositeExpression', [
				'expressions' => [
					$this->transformer->create('FunctionExpression', [
						'name' => "wp_deregister_{$this->assetType}",
						'args' => [$asset['handle']],
					]),
					$expression,
				],
			]);
		}
	}

	/**
	 * See if this function call should be wrapped in a conditional.
	 * @param  mixed $enqueue    (en|de)queue value
	 * @param  object &$expression expression object
	 */
	protected function doConditional($enqueue, &$expression) {
		if (is_string($enqueue)) {
			$expression = $this->transformer->create('BlockExpression', [
				'name' => 'if',
				'parenthetical' => $this->transformer->create('RawExpression', [
					'expression' => $enqueue,
				]),
				'expressions' => [$expression],
			]);
		}
	}

	/**
	 * Add localization call if data has been provided.
	 * @param  array   $asset      asset definition
	 * @param  object  &$expression expression object
	 */
	protected function doCustomization($asset, &$expression) {
		if (empty($asset['localize'])) {
			return;
		}

		if (empty($asset['localize']['variable'])) {
			$this->logger->warning("Localization variable name not set for script '{$asset['handle']}' - skipping localization");
			return;
		}

		if (empty($asset['localize']['strings'])) {
			$this->logger->warning("No localization strings provided for script '{$asset['handle']}' - skipping localization");
			return;
		}

		$expression = $this->transformer->create('CompositeExpression', [
			'expressions' => [
				$expression,
				$this->transformer->create('FunctionExpression', [
					'name' => 'wp_localize_script',
					'args' => [
						$asset['handle'],
						$asset['localize']['variable'],
						array_map(function($text) {
							return $this->transformer->create('TranslatableTextExpression', ['text' => $text]);
						}, $asset['localize']['strings']),
					],
				]),
			],
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getRequestedServices() {
		return ['logger', 'filesystem', 'transformer'];
	}

}
