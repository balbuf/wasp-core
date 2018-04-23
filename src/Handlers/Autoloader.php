<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\DependentHandler;

class Autoloader extends DependentHandler {

	protected $logger;

	public function getDefaults($property) {
		return [
			'dir' => 'src',
		];
	}

	public function handle($transformer, $config, $property) {
		if (!isset($config['namespace'])) {
			$this->logger->warning('No namespace set for autoloader property');
			return;
		}

		$autoloader = <<<'PHP'
/**
 * PSR 4 Autoloader for class includes.
 * @source  http://www.php-fig.org/psr/psr-4/examples/
 */
spl_autoload_register( function ( $class ) {
	// project-specific namespace prefix
	$prefix = %PREFIX%;
	// base directory for the namespace prefix
	$base_dir = %DIR%;
	// does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// no, move to the next registered autoloader
		return;
	}
	// get the relative class name
	$relative_class = substr( $class, $len );
	// replace the namespace prefix with the base directory, replace namespace
	// separators with directory separators in the relative class name, append
	// with .php
	$file = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
	// if the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
} );
PHP;
		$autoloader = str_replace('%PREFIX%', var_export((string) $config['namespace'], true), $autoloader);
		$autoloader = str_replace('%DIR%', $transformer->outputExpression->convertPath(rtrim($config['dir'], '/') . '/'), $autoloader);

		$transformer->outputExpression->addExpression(
			$transformer->create('RawExpression', ['expression' => $autoloader]),
			['priority' => 2]
		);
	}

	public static function getRequestedServices() {
		return ['logger'];
	}

}
