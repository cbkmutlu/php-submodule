<?php

declare(strict_types=1);

namespace System\Controller;

class Controller {
	/**
	 * middleware
	 *
	 * @param array $middlewares
	 * @param bool $default
	 *
	 * @return void
	 */
	protected function middleware(array $middlewares, bool $default = false): void {
		$services = config('services.middlewares.custom');
		foreach ($middlewares as $middleware) {
			$middleware = ucfirst($middleware);
			if (array_key_exists($middleware, $services) && class_exists($services[$middleware])) {
				call_user_func_array([new $services[$middleware], 'handle'], []);
			}
		}
	}
}
