<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;
use OomphInc\WASP\Property\PropertyManipulatorInterface;

class WpFormsApi extends AbstractHandler implements PropertyManipulatorInterface {

	// variable used in the output file to store form schema
	const FORMS_VAR = 'wpFormsApiForms';

	// key used for the nonce
	const NONCE_KEY = 'wp_forms_api_nonce';

	// wasp prop key for wp forms api forms
	const PROP_KEY = 'wp_forms_api';

	// whether there are wp-forms-api widgets
	protected $hasWidgets = false;

	// IDs for meta boxes that have forms
	protected $metaBoxForms = [];

	/**
	 * {@inheritdoc}
	 */
	public function handle($transformer, $config, $property) {
		if ($this->hasWidgets) {
			$transformer->outputExpression->addExpression($transformer->create('RawExpression', [
				'expression' => <<<'PHP'
?>
<script type="text/javascript">
	jQuery(function($) {
		// when widgets are added or updated
		$(document).on('widget-added widget-updated', function(ev, widget) {
			if (typeof wpFormsApi === 'undefined') {
				return;
			}
			wpFormsApi.setup(widget);
		});
	});
</script>
<?php
PHP
,
				]), [
				'hook' => 'admin_footer',
			]);
		}

		// add handling for meta forms
		if (count($this->metaBoxForms)) {
			$metaBoxes = $transformer->getProperty('meta_boxes');
			$postTypes = [];
			$forms = $formMapping = [];

			// add handling for each form
			foreach ($this->metaBoxForms as $id) {
				if (empty($metaBoxes[$id]['post_types'])) {
					continue;
				}

				$forms[$id] = $metaBoxes[$id][static::PROP_KEY];
				$formMapping[$id] = $metaBoxes[$id]['post_types'];
				$postTypes = array_merge($postTypes, $metaBoxes[$id]['post_types']);
			}

			// expression to hold all 'save_post' logic
			$transformer->outputExpression->addExpression($transformer->create('BlockExpression', [
				'name' => 'if',
				// check validity of nonce
				'parenthetical' => $transformer->create('RawExpression', [
					'expression' => 'isset( $_POST[%key] ) && wp_verify_nonce( $_POST[%key], %key )',
					'tokens' => [
						'%key' => static::NONCE_KEY,
					],
				]),
				'expressions' => [
					// unset the nonce value so the forms are not processed more than once
					$transformer->create('RawExpression', [
						'expression' => 'unset( $_POST[%key] );',
						'tokens' => [
							'%key' => static::NONCE_KEY,
						],
					]),
					// add logic to process forms
					$transformer->create('RawExpression', [
						'expression' => <<<'PHP'
foreach ( %formMapping as $id => $postTypes ) {
	// does form apply to this post type?
	if ( ! in_array( $post->post_type, $postTypes, true ) ) {
		continue;
	}

	// reset meta values array
	$meta_values = null;
	// process form
	\WP_Forms_API::process_form( $GLOBALS[%formsVar][ $id ], $meta_values );
	if ( isset( $meta_values ) ) {
		// update meta values
		foreach ( $meta_values as $meta_key => $meta_value ) {
			update_post_meta( $post->ID, $meta_key, $meta_value );
		}
	}
}
PHP
,
						'tokens' => [
							'%formMapping' => $formMapping,
							'%formsVar' => static::FORMS_VAR,
						],
					]),
				]
			]), [
				'hook' => 'save_post',
				'args' => ['post_id', 'post'],
			]);

			// add form schema
			$transformer->outputExpression->addExpression($transformer->create('RawExpression', [
				'expression' => '$' . static::FORMS_VAR . ' = %value;',
				'tokens' => [
					'%value' => $forms,
				],
			]), [
				'priority' => 2.5,
			]);

			// create a nonce function expression
			$nonce = $transformer->create('FunctionExpression', [
				'name' => 'wp_nonce_field',
				'args' => [static::NONCE_KEY, static::NONCE_KEY, false, true],
			]);

			// add nonce field on post edit page
			$transformer->outputExpression->addExpression($transformer->create('BlockExpression', [
				'name' => 'if',
				'parenthetical' => $transformer->create('ComparisonExpression', [
					'value' => $transformer->create('FunctionExpression', [
						'name' => 'get_post_type',
						'inline' => true,
					]),
					'comparison' => array_unique($postTypes),
					'operator' => 'in',
				]),
				'expressions' => [$nonce],
			]), [
				'hook' => 'post_submitbox_misc_actions',
			]);

			// attachments have their own hook
			if (in_array('attachment', $postTypes)) {
				$transformer->outputExpression->addExpression($nonce, ['hook' => 'attachment_submitbox_misc_actions']);
			}
		}
	}

	public function manipulateProperties($propertyTree, $docBlockFinder) {
		// handle widgets
		foreach ($propertyTree->get('widgets') ?: [] as $id => $widget) {
			// does it have a form?
			if (empty($widget['wp_forms_api'])) {
				continue;
			}

			$widget['methods']['getFormSchema'] = [
				'type' => 'return',
				'body' => $widget['wp_forms_api'],
				'visibility' => 'protected',
			];

			$widget['methods']['update'] = [
				'type' => 'php',
				'body' => <<<'PHP'
// $instance will hold the validated values, if successful
\WP_Forms_API::process_form( $this->getFormSchema(), $instance, $new_instance );
return isset( $instance ) ? $instance : [];
PHP
,
			];

			$widget['methods']['form'] = [
				'type' => 'php',
				'body' => <<<'PHP'
$form = $this->getFormSchema();
// add the widget specific id and name fields
foreach ( $form as $key => &$field ) {
	$field['#id'] = $this->get_field_id( $key );
	$field['#name'] = $this->get_field_name( $key );
}
echo \WP_Forms_API::render_form( $form, $instance );
PHP
,
			];

			$this->hasWidgets = true;
			$propertyTree->set('widgets', $id, $widget);
		}

		// handle meta boxes
		foreach ($propertyTree->get('meta_boxes') ?: [] as $id => $metaBox) {
			// does it have a form?
			if (empty($metaBox['wp_forms_api'])) {
				continue;
			}

			// keep track that this meta box has a form
			$this->metaBoxForms[] = $id;

			$metaBox['callback'] = [
				'type' => 'php',
				'body' => '
global $' . static::FORMS_VAR . ';
$post = get_post( $post );
$meta = get_post_custom( $post->ID );
$values = [];

foreach ( $meta as $meta_key => $meta_values ) {
	$values[ $meta_key ] = maybe_unserialize( $meta_values[0] );
}

echo \WP_Forms_API::render_form( $' . static::FORMS_VAR . '[' . var_export($id, true) . '], $values );
'
,
			];

			$propertyTree->set('meta_boxes', $id, $metaBox);
		}

		// if we had any forms, set a bogus property so our handler is triggered
		if ($this->hasWidgets || count($this->metaBoxForms)) {
			$propertyTree->set('wp_forms_api', true);
		}
	}

}
