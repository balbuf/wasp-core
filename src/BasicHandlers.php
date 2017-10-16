<?php
namespace OomphInc\WASP\Core;

class BasicHandlers {

	protected $application;

	public function __construct($application) {
		$this->application = $application;
	}

	public function postTypes($transformer, $data) {
		$defaults = [
			'labels' => [
				'name' => '%plural%',
				'all_items' => 'All %plural%',
				'add_new_item' => 'Add New %singular%',
				'edit_item' => 'Edit %singular%',
				'new_item' => 'New %singular%',
				'view_item' => 'View %singular%',
				'search_items' => 'Search %plural%',
				'not_found' => 'No %plural% found',
			],
			'show_ui' => true,
			'public' => true,
			'has_archive' => true,
			'show_in_nav_menus' => true,
			'menu_position' => 20,
			'map_meta_cap' => true,
			'supports' => [
				'title',
				'editor',
				'thumbnail',
			],
			'hierarchical' => false,
		];
		$patterns = ['%singular%', '%plural%'];
		if (isset($data['default'])) {
			$defaults = array_merge_recursive($defaults, $data['default']);
			unset($data['default']);
		}
		foreach ($data as $postType => $args) {
			if (isset($args['post_type'])) {
				$postType = $args['post_type'];
				unset($args['post_type']);
			}
			if (isset($args['label'])) {
				$plural = $args['label'];
			} elseif (isset($args['labels']['name'])) {
				$plural = $args['labels']['name'];
			} else {
				$plural = ucwords($postType);
				$args['label'] = $plural;
			}
			if (!isset($args['labels']['singular_name'])) {
				if (substr($plural, -1) === 's') {
					$args['labels']['singular_name'] = substr($plural, 0, -1);
				} else {
					$this->application->services->logger->warning("Could not determine singular name for $postType post type. Skipping.");
					continue;
				}
			}
			$args = array_merge_recursive($defaults, $args);
			$replacements = [$args['labels']['singular_name'], $plural];
			$args['labels'] = $transformer->create('ArrayExpression', [
				'array' => array_map(function($label) use ($transformer) {
					return $transformer->create('TranslatableTextExpression', ['text' => $label]);
				}, str_replace($patterns, $replacements, $args['labels']))
			]);
			$transformer->setupFile->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_post_type',
					'args' => [$postType, $transformer->create('ArrayExpression', ['array' => $args])],
				]),
				['hook' => 'init']
			);
		}

	}

	public function taxonomies($transformer, $data) {

	}

	public function siteOptions($transformer, $data) {
		foreach ($data as $option => $value) {
			$transformer->setupFile->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'update_option',
					'args' => [$option, $value],
				]),
				['lazy' => true]
			);
		}
	}

	public function scripts($transformer, $data) {

	}

	public function styles($transformer, $data) {

	}

	public function imageSizes($transformer, $data) {
		foreach ($data as $name => $settings) {
			if (!isset($settings['width'], $settings['height'])) {
				$this->application->services->logger->warning("Error: missing width or height for image size '$name'");
				continue;
			}
			$settings += ['crop' => true];
			$args = [$name, $settings['width'], $settings['height'], $settings['crop']];
			$transformer->setupFile->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'add_image_size',
					'args' => $args,
				]),
				['hook' => 'after_setup_theme']
			);
		}
	}

	public function constants($transformer, $data) {
		foreach ($data as $constant => $value) {
			$transformer->setupFile->addExpression(
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

	public function menuLocations($transformer, $data) {
		$data = array_map(function($label) use ($transformer) {
			return $transformer->create('TranslatableTextExpression', ['text' => $label]);
		}, $data);

		$transformer->setupFile->addExpression(
			$transformer->create('FunctionExpression', [
				'name' => 'register_nav_menus',
				'args' => [$transformer->create('ArrayExpression', ['array' => $data])],
			]),
			['hook' => 'after_setup_theme']
		);
	}

	public function autoloader($transformer, $data) {
		if (!isset($data['namespace'])) {
			$this->application->services->logger->warning('No namespace set for autoloader property');
			return;
		}

		$data += ['dir' => 'src'];
		$autoloader = <<<'PHP'
/**
 * PSR 4 Autoloader for class includes.
 * @source  http://www.php-fig.org/psr/psr-4/examples/
 */
spl_autoload_register( function ( $class ) {
	// project-specific namespace prefix
	$prefix = %PREFIX%;
	// base directory for the namespace prefix
	$base_dir = __DIR__ . %DIR%;
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
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
	// if the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
} );
PHP;
		$autoloader = str_replace('%PREFIX%', var_export((string) $data['namespace'], true), $autoloader);
		$autoloader = str_replace('%DIR%', var_export('/' . trim((string) $data['dir'], '/') . '/', true), $autoloader);

		$transformer->setupFile->addExpression(
			$transformer->create('RawExpression', ['expression' => $autoloader]),
			['priority' => 2]
		);
	}

	public function themeSupports($transformer, $data) {
		foreach ($data as $feature) {
			if (is_string($feature)) {
				$expression = $transformer->create('FunctionExpression', [
					'name' => 'add_theme_support',
					'args' => [$feature],
				]);
			} else if (is_array($feature) && count($feature) === 1) {
				$args = reset($feature);
				array_unshift($args, key($feature));
				$expression = $transformer->create('FunctionExpression', [
					'name' => 'add_theme_support',
					'args' => $args,
				]);
			} else {
				continue;
			}
			$transformer->setupFile->addExpression($expression, ['hook' => 'after_setup_theme']);
		}
	}

	public function widgetAreas($transformer, $data) {
		foreach ($data as $id => $args) {
			if (!isset($args['id'])) {
				$args['id'] = $id . '-widget';
			}
			foreach (['name', 'description'] as $key) {
				if (isset($args[$key])) {
					$args[$key] = $transformer->create('TranslatableTextExpression', [
						'text' => $args[$key],
					]);
				}
			}
			$transformer->setupFile->addExpression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_sidebar',
					'args' => [
						$transformer->create('ArrayExpression', [
							'array' => $args,
						]),
					],
				]),
				['hook' => 'widgets_init']
			);
		}
	}

	// @todo Add file globbing
	public function includes($transformer, $data) {
		$dir = isset($data['dir']) ? trim($data['dir'], '/') . '/' : '';
		if (isset($data['files'])) {
			foreach ($data['files'] as $file) {
				$transformer->setupFile->addExpression(
					$transformer->create('RawExpression', [
						'expression' => "require_once __DIR__ . '/$dir$file';",
					])
				);
			}
		}
	}
}
