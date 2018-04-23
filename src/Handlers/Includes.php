<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;
use OomphInc\WASP\FileSystem\FileSystemHelper;

class Includes extends DependentHandler {

	protected $logger;
	protected $filesystem;

	public function getDefaults($property) {
		return [
			'use' => 'require_once',
			'files' => [],
			'files_match' => [],
		];
	}

	public function handle($transformer, $config, $property) {
		if (!preg_match('/^(?:require|include)(?:_once)?$/', $config['use'])) {
			$this->logger->warn("Invalid use type '{$config['use']}', using 'require_once' instead");
			$config['use'] = 'require_once';
		}

		// get explicit files
		$files = FileSystemHelper::flattenFileArray($config['files']);

		// find files and add by globbing
		foreach ((array) $config['files_match'] as $pattern) {
			$this->filesystem->pushd($transformer->getVar('rootDir'));
			$files = array_merge($files, $this->filesystem->getFiles($pattern));
			$this->filesystem->popd();
		}

		$expression = $transformer->create('CompositeExpression', ['joiner' => "\n"]);

		// add the files
		foreach ($files as $file) {
			$expression->expressions[] = $transformer->create('RawExpression', [
				'expression' => $config['use'] . ' ' . $transformer->outputExpression->convertPath($file) . ';',
			]);
		}

		$transformer->outputExpression->addExpression($expression, ['priority' => 3]);
	}

	public static function getRequestedServices() {
		return ['logger', 'filesystem'];
	}

}
