<?php

declare(strict_types=1);

namespace System\Starter;

use System\Router\Router;
use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler as WhoopsPrettyPageHandler;

class Starter {
	public static $router;

	public function __construct(Router $router) {
		$whoops = new WhoopsRun;
		$whoops->pushHandler(new WhoopsPrettyPageHandler);
		$whoops->register();
		Self::$router = $router;
	}

	public function run(array $routes): void {
		// $iterator = new RecursiveDirectoryIterator(APP_DIR . 'Config');
		// $loaded = [];
		// foreach (new RecursiveIteratorIterator($iterator) as $fileInfo) {
		// 	$filename = $fileInfo->getRealPath();
		// 	if (strpos($filename, 'Routes.php') !== false && !in_array($filename, $loaded)) {
		// 		require_once $filename;
		// 		$loadedFiles[] = $filename;
		// 	}
		// }

		foreach ($routes as $route) {
			import($route);
		}
		Self::$router->run();
	}

	public static function router() {
		return self::$router;
	}
}
