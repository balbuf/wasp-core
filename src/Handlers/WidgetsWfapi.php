<?php

namespace OomphInc\WASP\Core\Handlers;

use OomphInc\WASP\Handler\AbstractHandler;
use OomphInc\WASP\Property\PropertyManipulatorInterface;

class WidgetsWfapi extends AbstractHandler implements PropertyManipulatorInterface {

	// whether there are wp-forms-api widgets
	protected $hasWidgets = false;

	/**
	 * {@inheritdoc}
	 */
	public function handle($transformer, $config, $property) {
		if (!$this->hasWidgets) {
			return;
		}

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
			]),
			['hook' => 'admin_footer']
		);
	}

	public function manipulateProperties($propertyTree, $docBlockFinder) {
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
	}

}
