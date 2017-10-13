<?php
namespace OomphInc\WASP\Core;

use OomphInc\WASP\Compilable\FunctionExpression;
use OomphInc\WASP\Compilable\ArrayExpression;
use OomphInc\WASP\Compilable\TranslatableTextExpression;
use OomphInc\WASP\Compilable\RawExpression;
use OomphInc\WASP\Compilable\CompositeExpression;
use OomphInc\WASP\Compilable\BlockExpression;


class BasicHandlers {

	protected $application;

	public function __construct($application) {
		$this->application = $application;
	}

	public function post_types($transformer, $data) {
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
		foreach ($data as $post_type => $args) {
			if (isset($args['post_type'])) {
				$post_type = $args['post_type'];
				unset($args['post_type']);
			}
			if (isset($args['label'])) {
				$plural = $args['label'];
			} elseif (isset($args['labels']['name'])) {
				$plural = $args['labels']['name'];
			} else {
				$plural = ucwords($post_type);
				$args['label'] = $plural;
			}
			if (!isset($args['labels']['singular_name'])) {
				if (substr($plural, -1) === 's') {
					$args['labels']['singular_name'] = substr($plural, 0, -1);
				} else {
					$this->application->services->logger->warning("Could not determine singular name for $post_type post type. Skipping.");
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
			$transformer->setup_file->add_expression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_post_type',
					'args' => [$post_type, $transformer->create('ArrayExpression', ['array' => $args])],
				]),
				'init'
			);
		}

	}

	public function taxonomies($transformer, $data) {

	}

	public function site_options($transformer, $data) {
		foreach ($data as $option => $value) {
			$transformer->setup_file->add_lazy_expression(
				$transformer->create('FunctionExpression', [
					'name' => 'update_option',
					'args' => [$option, $value],
				])
			);
		}
	}

	public function scripts($transformer, $data) {

	}

	public function styles($transformer, $data) {

	}

	public function image_sizes($transformer, $data) {
		foreach ($data as $name => $settings) {
			if (!isset($settings['width'], $settings['height'])) {
				$this->application->services->logger->warning("Error: missing width or height for image size '$name'");
				continue;
			}
			$settings += ['crop' => true];
			$args = [$name, $settings['width'], $settings['height'], $settings['crop']];
			$transformer->setup_file->add_expression(
				$transformer->create('FunctionExpression', [
					'name' => 'add_image_size',
					'args' => $args,
				]),
				'after_setup_theme'
			);
		}
	}

	public function constants($transformer, $data) {
		foreach ($data as $constant => $value) {
			$transformer->setup_file->add_expression(
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
				])
			);
		}
	}

	public function menu_locations($transformer, $data) {
		$data = array_map(function($label) use ($transformer) {
			return $transformer->create('TranslatableTextExpression', ['text' => $label]);
		}, $data);

		$transformer->setup_file->add_expression(
			$transformer->create('FunctionExpression', [
				'name' => 'register_nav_menus',
				'args' => [$transformer->create('ArrayExpression', ['array' => $data])],
			]),
			'after_setup_theme'
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

		$transformer->setup_file->add_expression(
			$transformer->create('RawExpression', ['expression' => $autoloader])
		);
	}

	public function theme_supports($transformer, $data) {
		foreach ($data as $feature) {
			if (is_string($feature)) {
				$transformer->setup_file->add_expression(
					$transformer->create('FunctionExpression', [
						'name' => 'add_theme_support',
						'args' => [$feature],
					]),
					'after_setup_theme'
				);
			}
			if (is_array($feature) && count($feature) === 1) {
				foreach ($feature as $name => $args) {
					array_unshift($args, $name);
					$transformer->setup_file->add_expression(
						$transformer->create('FunctionExpression', [
							'name' => 'add_theme_support',
							'args' => $args,
						]),
						'after_setup_theme'
					);
				}
			}
		}
	}

	public function widget_areas($transformer, $data) {
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
			$transformer->setup_file->add_expression(
				$transformer->create('FunctionExpression', [
					'name' => 'register_sidebar',
					'args' => [
						$transformer->create('ArrayExpression', [
							'array' => $args,
						]),
					],
				]),
				'widgets_init'
			);
		}
	}

	// @todo Add file globbing
	public function includes($transformer, $data) {
		$dir = isset($data['dir']) ? trim($data['dir'], '/') . '/' : '';
		if (isset($data['files'])) {
			foreach ($data['files'] as $file) {
				$transformer->setup_file->add_expression(
					$transformer->create('RawExpression', [
						'expression' => "require_once __DIR__ . '/$dir$file';",
					])
				);
			}
		}
	}
}
