<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;
use OomphInc\WASP\FileSystem\FileSystemHelper;

class Hooks extends DependentHandler {

	const DEFAULT_PRIORITY = 10;
	const HOOK_REGEX = '/^(.*?)[ \t]*@(\d+)$/'; // used to parse out a hook name and priority

	protected $logger;
	// core WordPress functions that return a fixed value
	public $valueFunctionsMapping = [
		'__return_true' => true,
		'__return_false' =>false,
		'__return_zero' => 0,
		'__return_empty_array' => [],
		'__return_null' => null,
		'__return_empty_string' => '',
	];

	public function getDefaults($property) {
		return [
			'priority' => static::DEFAULT_PRIORITY,
			'hook' => [],
			'unhook' => [],
			'filter_values' => [],
		];
	}

	public function handle($transformer, $config, $property) {
		// add the explicit hooks
		if (!empty($config['hook'])) {
			$expression = $transformer->create('CompositeExpression', [
				'expressions' => [
					$transformer->create('Comment', [
						'comment' => ' Hooks set explicity in setup file',
					]),
				],
			]);

			foreach ($this->parseHookTree($config['hook'], $config['priority']) as list($hook, $callable, $priority)) {
				$expression->expressions[] = $transformer->create('FunctionExpression', [
					'name' => 'add_filter',
					'args' => [$hook, $callable, $priority, 99],
				]);
			}

			$transformer->outputExpression->addExpression($expression);
		}

		// add explicit unhooks
		if (!empty($config['unhook'])) {
			$expression = $transformer->create('CompositeExpression', [
				'expressions' => [
					$transformer->create('Comment', [
						'comment' => ' Unooks set explicity in setup file',
					]),
				],
			]);

			foreach ($this->parseHookTree($config['unhook'], null) as $args) {
				// remove priority if not set
				if ($args[2] === null) {
					array_pop($args);
				}

				$expression->expressions[] = $transformer->create('FunctionExpression', [
					'name' => 'remove_filter',
					'args' => $args,
				]);
			}

			$transformer->outputExpression->addExpression($expression);
		}

		// add the value filters
		if (!empty($config['filter_values'])) {
			$expression = $transformer->create('CompositeExpression', [
				'expressions' => [
					$transformer->create('Comment', [
						'comment' => ' Values to filter set in setup file',
					]),
				],
			]);

			foreach ($config['filter_values'] as $hook => $value) {
				// is there a core function for this value?
				if ($callable = array_search($value, $this->valueFunctionsMapping, true)) {
					$expression->expressions[] = $transformer->create('FunctionExpression', [
						'name' => 'add_filter',
						'args' => [$hook, $callable, $config['priority']],
					]);
				// otherwise create a closure
				} else {
					$expression->expressions[] = $transformer->create('HookExpression', [
						'name' => 'image_size_names_choose',
						'expressions' => [
							$transformer->create('CompositeExpression', [
								'expressions' => [
									$transformer->create('RawExpression', [
										'expression' => 'return ',
									]),
									$value,
									$transformer->create('RawExpression', [
										'expression' => ";\n",
									]),
								],
							]),
						],
						'function' => 'add_filter',
					]);
				}
			}

			$transformer->outputExpression->addExpression($expression);
		}
	}

	/**
	 * Parse a hook tree array.
	 * @param  array  $hookTree   hooked tree from parse yaml
	 * @param  int    $defaultPriority default priority
	 * @return array            [[hook, callable, priority], ...]
	 */
	protected function parseHookTree($hookTree, $defaultPriority) {
		$hooks = [];
		foreach ($hookTree as $hook => $info) {
			// array is either a single verbose hook or multiple hooks
			if (is_array($info)) {
				// a single verbose hook
				if (isset($info['callable'])) {
					$priority = isset($info['priority']) ? $info['priority'] : $defaultPriority;
					foreach ((array) $info['callable'] as $callable) {
						$hooks[] = [$hook, $callable, $priority];
					}
				// should be an array of verbose hooks
				} else {
					foreach ($info as $item) {
						if (!isset($item['callable'])) {
							$this->logger->warning("Missing callable property for $hook");
							continue;
						}

						foreach ((array) $item['callable'] as $callable) {
							$hooks[] = [$hook, $callable, isset($item['priority']) ? $item['priority'] : $defaultPriority];
						}
					}
				}
			} else {
				// parse out the hook/priority short syntax
				if (preg_match(static::HOOK_REGEX, $hook, $matches)) {
					$hooks[] = [$matches[1], $info, $matches[2]];
				} else {
					$hooks[] = [$hook, $info, $defaultPriority];
				}
			}
		}
		return $hooks;
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
