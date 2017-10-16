<?php

namespace OomphInc\WASP\Core;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use OomphInc\WASP\Events;

class Plugin implements EventSubscriberInterface {

	protected static $application;

	public function __construct($application) {
		static::$application = $application;
	}

	static public function registerHandlers($event) {
		$transformer = $event->getArgument('transformer');
		$handlers = new BasicHandlers(static::$application);
		$transformer->importHandlersFromClass($handlers, 'wasp_');
	}

	static public function getSubscribedEvents() {
		return [
			Events::REGISTER_TRANSFORMS => static::class . '::registerHandlers',
		];
	}

}
