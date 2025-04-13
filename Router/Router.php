<?php

declare(strict_types=1);

namespace System\Router;

use System\Container\Container;
use System\Exception\ExceptionHandler;

class Router {
    private $container;
    private $routes = [];
    private $prefix = '/';
    private $module = null;
    private $middlewares = [];
    private $domain = [];
    private $ip = [];
    private $ssl = false;
    private $as = null;
    private $callback = null;
    private $groups = [];
    private $length = 0;
    private $names = [];

    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * group
     *
     * @param callable $callback
     *
     * @return void
     */
    public function group(callable $callback): void {
        $this->length++;
        $this->groups[] = [
            'prefix' => $this->prefix,
            'middlewares' => $this->middlewares,
            'module' => $this->module,
            'domain' => $this->domain,
            'ip' => $this->ip,
            'ssl' => $this->ssl,
            'as' => $this->as,
        ];

        call_user_func($callback, $this);

        // if ($this->length > 0) {
        //     $this->prefix = $this->groups[$this->length - 1]['prefix'];
        //     $this->middlewares = $this->groups[$this->length - 1]['middlewares'];
        //     $this->module = $this->groups[$this->length - 1]['module'];
        //     $this->domain = $this->groups[$this->length - 1]['domain'];
        //     $this->ip = $this->groups[$this->length - 1]['ip'];
        //     $this->ssl = $this->groups[$this->length - 1]['ssl'];
        //     $this->as = $this->groups[$this->length - 1]['as'];
        // }

        // $this->length--;

        // if ($this->length <= 0) {
        $this->prefix = '/';
        $this->middlewares = [];
        $this->module = null;
        $this->domain = [];
        $this->ip = [];
        $this->ssl = false;
        $this->as = null;
        // }
    }

    /**
     * prefix
     *
     * @param string $prefix
     *
     * @return self
     */
    public function prefix(string $prefix): self {
        $this->prefix = '/' . $prefix;
        return $this;
    }

    /**
     * middleware
     *
     * @param array $middlewares
     *
     * @return self
     */
    public function middleware(array $middlewares): self {
        foreach ($middlewares as $middleware) {
            $this->middlewares[$middleware] = ['callback'  => 'App\\Middlewares\\' . ucfirst($middleware) . '@handle'];
        }
        return $this;
    }

    /**
     * module
     *
     * @param string $module
     *
     * @return self
     */
    public function module(string $module): self {
        $this->module = $module;
        return $this;
    }

    /**
     * domain
     *
     * @param array $domain
     *
     * @return self
     */
    public function domain(array $domain): self {
        $this->domain = $domain;
        return $this;
    }

    /**
     * ip
     *
     * @param array $ip
     *
     * @return self
     */
    public function ip(array $ip): self {
        $this->ip = $ip;
        return $this;
    }

    /**
     * ssl
     *
     * @return self
     */
    public function ssl(): self {
        $this->ssl = true;
        return $this;
    }

    /**
     * as
     *
     * @param string $as
     *
     * @return self
     */
    public function as(string $as): self {
        $this->as = $as;
        return $this;
    }

    /**
     * get
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function get(string $pattern, string|callable $callback): self {
        $this->add('GET', $pattern, $callback);
        return $this;
    }

    /**
     * post
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function post(string $pattern, string|callable $callback): self {
        $this->add('POST', $pattern, $callback);
        return $this;
    }

    /**
     * patch
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function patch(string $pattern, string|callable $callback): self {
        $this->add('PATCH', $pattern, $callback);
        return $this;
    }

    /**
     * delete
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function delete(string $pattern, string|callable $callback): self {
        $this->add('DELETE', $pattern, $callback);
        return $this;
    }

    /**
     * put
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function put(string $pattern, string|callable $callback): self {
        $this->add('PUT', $pattern, $callback);
        return $this;
    }

    /**
     * options
     *
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return self
     */
    public function options(string $pattern, string|callable $callback): self {
        $this->add('OPTIONS', $pattern, $callback);
        return $this;
    }

    /**
     * match
     *
     * @param array $methods
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return [type]
     */
    public function match(array $methods, string $pattern, string|callable $callback) {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $pattern, $callback);
        }
    }

    /**
     * where
     *
     * @param array $expressions
     *
     * @return self
     */
    public function where(array $expressions): self {
        $key = array_search(end($this->routes), $this->routes);
        $pattern = $this->parseUri($this->routes[$key]['uri'], $expressions);
        $pattern = '/' . implode('/', $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

        $this->routes[$key]['pattern'] = $pattern;
        return $this;
    }

    /**
     * name
     *
     * @param string $name
     * @param array $params
     *
     * @return self
     */
    public function name(string $name): self {
        $key = array_search(end($this->routes), $this->routes);
        $name = ($this->as) ? $this->as . '.' . $name : $name;

        $this->routes[$key]['name'] = $name;

        $uri = $this->parseUri($this->routes[$key]['uri'], []);
        $uri = implode('/', $uri);

        $this->names[$name] = $uri;
        return $this;
    }

    /**
     * run
     *
     * @return void
     */
    public function run(): void {
        $matched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $this->getUri(), $params)) {
                if ($this->checkIp($route) && $this->checkDomain($route) && $this->checkSSL($route) && $this->checkMethod($route)) {
                    $matched = true;

                    $middlewares = config('services.middlewares.default');
                    foreach ($middlewares as $middleware) {
                        if (class_exists($middleware)) {
                           $container = $this->container->resolve($middleware);
                           call_user_func_array([$container, 'handle'], []);
                        }
                    }

                    if (array_key_exists('middlewares', $route)) {
                        foreach ($route['middlewares'] as $middleware) {
                            list($controller, $method) = explode('@', $middleware['callback']);

                            if (class_exists($controller)) {
                                $container = $this->container->resolve($controller);
                                call_user_func_array([$container, $method], []);
                            }
                        }
                    }

                    array_shift($params);
                    if (is_callable($route['callback'])) {
                        call_user_func_array($route['callback'], array_values($params));
                    } else if (strpos($route['callback'], '@') !== false) {
                        list($controller, $method) = explode('@', $route['callback']);

                        if (class_exists($controller)) {
                            $container = $this->container->resolve($controller);
                            call_user_func_array([$container, $method], array_values($params));
                        } else {
                            $this->getError();
                        }
                    }

                    break;
                }
            }
        }

        if ($matched === false) {
            $this->getError();
        }
    }

    /**
     * url
     *
     * @param string $name
     *
     * @return string
     */
    public function url(string $name, array $params = []): mixed {
        if (array_key_exists($name, $this->names)) {
            $pattern = $this->parseUri($this->names[$name], $params);
            $pattern = implode('/', $pattern);
            return $pattern;
        }

        return null;
    }

    /**
     * routes
     *
     * @return array
     */
    public function routes(): array {
        return $this->routes;
    }

    /**
     * names
     *
     * @return array
     */
    public function names(): array {
        return $this->names;
    }

    /**
     * error
     *
     * @param object|callable $callback
     *
     * @return void
     */
    public function error(object|callable $callback): void {
        $this->callback = $callback;
    }

    /**
     * add
     *
     * @param string $method
     * @param string $pattern
     * @param string|callable $callback
     *
     * @return void
     */
    private function add(string $method, string $pattern, string|callable $callback): void {
        if ($pattern === '/') {
            $pattern = $this->prefix . trim($pattern, '/');
        } else {
            if ($this->prefix === '/') {
                $pattern = $this->prefix . trim($pattern, '/');
            } else {
                $pattern = $this->prefix . $pattern;
            }
        }

        $uri = $pattern;
        $pattern = preg_replace('/[\[{\(].*[\]}\)]/U', '([^/]+)', $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

        if (is_callable($callback)) {
            $closure = $callback;
        } elseif (strpos($callback, '@') !== false) {
            if ($this->module) {
                $closure = 'App\\Modules\\' . ucfirst($this->module) . '\\Controllers\\' . $callback;
            }
        }

        $this->routes[] = array_filter([
            'uri'        => $uri,
            'method'     => $method,
            'pattern'    => $pattern,
            'callback'   => $closure,
            'module'     => $this->module ? ucfirst($this->module) : null,
            'middlewares' => $this->middlewares ?: [],
            'domain'     => $this->domain ?: [],
            'ip'         => $this->ip ?: [],
            'ssl'        => $this->ssl ?: false,
        ]);
    }

    /**
     * parseUri
     *
     * @param string $uri
     * @param array $expressions
     *
     * @return array
     */
    private function parseUri(string $uri, array $expressions): array {
        $segments = explode('/', ltrim($uri, '/'));

        return array_map(function ($segment) use ($expressions) {
            if (preg_match('/[\[{\(](.*)[\]}\)]/U', $segment, $match)) {
                $key = $match[1];
                return $expressions[$key] ?? $segment;
            }
            return $segment;
        }, $segments);
    }

    /**
     * getUri
     *
     * @return string
     */
    private function getUri(): string {
        $path = array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1);
        $path = implode('/', $path) . '/';
        $uri = substr($_SERVER['REQUEST_URI'], strlen($path));

        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * getError
     *
     * @return void
     */
    private function getError(): void {
        if ($this->callback && is_callable($this->callback)) {
            call_user_func($this->callback, $this);
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            throw new ExceptionHandler("Hata", "Controller bulunamadı");
        }
    }

    /**
     * checkIp
     *
     * @param array $route
     *
     * @return bool
     */
    private function checkIp(array $route): bool {
        if (array_key_exists('ip', $route)) {
            if (is_array($route['ip'])) {
                if (!in_array($_SERVER['REMOTE_ADDR'], $route['ip'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * checkDomain
     *
     * @param array $route
     *
     * @return bool
     */
    private function checkDomain(array $route): bool {
        if (array_key_exists('domain', $route)) {
            if (is_array($route['domain'])) {
                if (!in_array($_SERVER['HTTP_HOST'], $route['domain'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * checkSSL
     *
     * @param array $route
     *
     * @return bool
     */
    private function checkSSL(array $route): bool {
        if (array_key_exists('ssl', $route) && $route['ssl'] === true) {
            if ($_SERVER['REQUEST_SCHEME'] !== 'https') {
                return false;
            }
        }

        return true;
    }

    /**
     * checkMethod
     *
     * @param array $route
     *
     * @return bool
     */
    private function checkMethod(array $route): bool {
        $headers = getallheaders();
        $method = $_SERVER['REQUEST_METHOD'];

        if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
            ob_start();
            $method = 'GET';
            ob_end_clean();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $headers = getallheaders();
            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }

        return ($route['method'] !== $method) ? false : true;
    }
}
