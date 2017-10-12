<?php

namespace OomphInc\WASP\Core;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use OomphInc\WASP\Events;

class Plugin implements EventSubscriberInterface {

	protected $application;

	public function __construct($application) {
		$this->application = $application;
	}

	static public function registerHandlers($event) {
		$transformer = $event->getArgument('transformer');
		foreach (get_class_methods('OomphInc\\WASP\\Core\\BasicHandlers') as $handler) {
			$transformer->add_handler($handler, 'wasp_' . $handler, ['OomphInc\\WASP\\Core\\BasicHandlers', $handler]);
		}
	}

	static public function getSubscribedEvents() {
		return [
			Events::REGISTER_TRANSFORMS => static::class . '::registerHandlers',
		];
	}

}
