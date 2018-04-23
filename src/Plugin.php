<?php

namespace OomphInc\WASP\Core;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use OomphInc\WASP\Event\Events;
use OomphInc\WASP\Handler\HandlerProxy;

class Plugin implements EventSubscriberInterface {

	protected $wasp;

	public function __construct($wasp) {
		$this->wasp = $wasp;
	}

	public function registerHandlers($event) {
		// create a proxy handler
		$handlerProxy = new HandlerProxy($this->wasp, 'wasp_');

		// grab each handler and add to the proxy
		foreach (glob(__DIR__ . '/Handlers/*.php') as $file) {
			$class = basename($file, '.php');
			// convert camel case to snake
			$property = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
			$handlerProxy->setHandlerClass($property, __NAMESPACE__ . "\\Handlers\\{$class}");
		}

		// register the proxy handler
		$event->getArgument('transformer')->setHandler($handlerProxy);
	}

	static public function getSubscribedEvents() {
		return [
			Events::REGISTER_TRANSFORMS => 'registerHandlers',
		];
	}

}
