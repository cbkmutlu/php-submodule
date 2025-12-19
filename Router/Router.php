<?php

declare(strict_types=1);

namespace System\Router;

use System\Container\Container;
use System\Router\RouterException;

class Router {
   private $routes = [];
   private $prefix = '/';
   private $middlewares = [];
   private $domain = [];
   private $ip = [];
   private $ssl = false;
   private $as = null;
   private $error = null;
   private $groups = [];
   private $length = 0;
   public static $names = [];

   public function __construct(
      private Container $container
   ) {
   }

   public function group(callable $callback): void {
      $this->length++;
      $this->groups[] = [
         'prefix'      => $this->prefix,
         'middlewares' => $this->middlewares,
         'domain'      => $this->domain,
         'ip'          => $this->ip,
         'ssl'         => $this->ssl,
         'as'          => $this->as
      ];

      call_user_func($callback);
      if ($this->length > 0) {
         $this->prefix      = $this->groups[$this->length - 1]['prefix'];
         $this->middlewares = $this->groups[$this->length - 1]['middlewares'];
         $this->domain      = $this->groups[$this->length - 1]['domain'];
         $this->ip          = $this->groups[$this->length - 1]['ip'];
         $this->ssl         = $this->groups[$this->length - 1]['ssl'];
         $this->as          = $this->groups[$this->length - 1]['as'];
      }

      $this->length--;
      if ($this->length <= 0) {
         $this->prefix      = '/';
         $this->middlewares = [];
         $this->domain      = [];
         $this->ip          = [];
         $this->ssl         = false;
         $this->as          = null;
      }
   }

   public function prefix(string $prefix): self {
      $this->prefix = '/' . $prefix;
      return $this;
   }

   public function middleware(array $middlewares): self {
      $this->middlewares = array_merge($this->middlewares, $middlewares);
      return $this;
   }

   public function domain(array $domain): self {
      $this->domain = $domain;
      return $this;
   }

   public function ip(array $ip): self {
      $this->ip = $ip;
      return $this;
   }

   public function ssl(): self {
      $this->ssl = true;
      return $this;
   }

   public function as(string $as): self {
      $this->as = $as;
      return $this;
   }

   public function get(string $pattern, callable|array $callback): self {
      $this->add('GET', $pattern, $callback);
      return $this;
   }

   public function post(string $pattern, callable|array $callback): self {
      $this->add('POST', $pattern, $callback);
      return $this;
   }

   public function patch(string $pattern, callable|array $callback): self {
      $this->add('PATCH', $pattern, $callback);
      return $this;
   }

   public function delete(string $pattern, callable|array $callback): self {
      $this->add('DELETE', $pattern, $callback);
      return $this;
   }

   public function put(string $pattern, callable|array $callback): self {
      $this->add('PUT', $pattern, $callback);
      return $this;
   }

   public function options(string $pattern, callable|array $callback): self {
      $this->add('OPTIONS', $pattern, $callback);
      return $this;
   }

   public function match(array $methods, string $pattern, callable|array $callback) {
      foreach ($methods as $method) {
         $this->add(strtoupper($method), $pattern, $callback);
      }
   }

   public function where(array $expressions): self {
      $key = array_search(end($this->routes), $this->routes);
      $pattern = uri_parse($this->routes[$key]['uri'], $expressions);
      $pattern = '/' . implode('/', $pattern);
      $pattern = '/^' . str_replace('/', '\\/', $pattern) . '$/';

      $this->routes[$key]['pattern'] = $pattern;
      return $this;
   }

   public function name(string $name): self {
      $key = array_search(end($this->routes), $this->routes);
      $name = ($this->as) ? $this->as . '.' . $name : $name;

      $this->routes[$key]['name'] = $name;

      $uri = uri_parse($this->routes[$key]['uri'], []);
      $uri = implode('/', $uri);

      self::$names[$name] = $uri;
      return $this;
   }

   public function run(): void {
      if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
         $config = import_config('defines.header');
         header('Access-Control-Allow-Origin: ' . $config['allow-origin']);
         header('Access-Control-Allow-Headers: ' . $config['allow-headers']);
         header('Access-Control-Allow-Methods: ' . $config['allow-methods']);
         header('Access-Control-Allow-Credentials: ' . $config['allow-credentials']);
         http_response_code(204);
         return;
      }

      $matched = false;

      foreach ($this->routes as $route) {
         if (preg_match($route['pattern'], uri_get(), $params)) {
            if ($this->checkIp($route) && $this->checkDomain($route) && $this->checkSSL($route) && $this->checkMethod($route)) {
               $matched = true;
               break;
            }
         }
      }

      if (!$matched) {
         if ($this->error && is_callable($this->error)) {
            call_user_func($this->error);
         } else {
            throw new RouterException('Route [' . uri_get() . '] not found', 404);
         }
      }

      array_shift($params);
      $callback = function () use ($route, $params) {
         if (is_callable($route['callback'])) {
            call_user_func_array($route['callback'], array_values($params));
         } elseif (is_array($route['callback'])) {
            [$controller, $method] = $route['callback'];
            if (!class_exists($controller)) {
               throw new RouterException('Controller [' . $controller . '] not found');
            }
            if (!method_exists($controller, $method)) {
               throw new RouterException('Method [' . $method . '] not found in ' . $controller);
            }
            $instance = $this->container->resolveClass($controller);
            // $args = $this->container->resolveMethod($instance, $method, $params);
            // $instance->$method(...$args);
            call_user_func_array([$instance, $method], array_values($params));
         } else {
            throw new RouterException('Invalid route callback');
         }
      };

      $middlewares = array_merge(import_config('defines.middlewares') ?? [], $route['middlewares'] ?? []);
      $next = $callback;

      foreach (array_reverse($middlewares) as $middleware) {
         if (class_exists($middleware)) {
            $instance = $this->container->resolveClass($middleware);
            $current = $next;
            $next = function () use ($instance, $current) {
               return $instance->handle($current);
            };
         }
      }

      $next();
   }

   public function error(callable $callback): void {
      $this->error = function () use ($callback) {
         call_user_func($callback, uri_get());
      };
   }

   public static function url(string $name, array $params = []): mixed {
      if (isset(self::$names[$name])) {
         $pattern = uri_parse(self::$names[$name], $params);
         $pattern = implode('/', $pattern);
         return $pattern;
      }

      return null;
   }

   private function add(string $method, string $pattern, callable|array $callback): void {
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
      $pattern = '/^' . str_replace('/', '\\/', $pattern) . '$/';

      $this->routes[] = array_filter([
         'uri'         => $uri,
         'method'      => $method,
         'pattern'     => $pattern,
         'callback'    => $callback,
         'middlewares' => $this->middlewares,
         'domain'      => $this->domain,
         'ip'          => $this->ip,
         'ssl'         => $this->ssl
      ]);
   }

   private function checkIp(array $route): bool {
      if (isset($route['ip'])) {
         if (is_array($route['ip'])) {
            if (!in_array($_SERVER['REMOTE_ADDR'], $route['ip'])) {
               return false;
            }
         }
      }

      return true;
   }

   private function checkDomain(array $route): bool {
      if (isset($route['domain'])) {
         if (is_array($route['domain'])) {
            if (!in_array($_SERVER['HTTP_HOST'], $route['domain'])) {
               return false;
            }
         }
      }

      return true;
   }

   private function checkSSL(array $route): bool {
      if (isset($route['ssl']) && $route['ssl']) {
         if ($_SERVER['REQUEST_SCHEME'] !== 'https') {
            return false;
         }
      }

      return true;
   }

   private function checkMethod(array $route): bool {
      $headers = getallheaders();
      $method = $_SERVER['REQUEST_METHOD'];

      if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
         ob_start();
         $method = 'GET';
         ob_end_clean();
      } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
         if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
            $method = $headers['X-HTTP-Method-Override'];
         }
      }

      return ($route['method'] !== $method) ? false : true;
   }
}
