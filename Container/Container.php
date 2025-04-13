<?php

declare(strict_types=1);

namespace System\Container;

use ReflectionClass;
use ReflectionNamedType;
use System\Exception\ExceptionHandler;

class Container {
   private array $services = [];
   private array $instances = [];
   private array $reflections = [];
   private array $providers = [];

   public function __construct() {
      $this->providers = config('services.providers');
   }

   /**
    * register
    *
    * @param array|null $providers
    *
    * @return void
    */
   public function register(?array $providers = null): void {
      if ($providers) {
         $this->providers = $providers;
      }

      foreach ($this->providers as $name => $class) {
         $this->services[$name] = [
            'definition' => function () use ($class) {
               return new $class();
            },
            'shared' => false,
         ];
      }
   }

   /**
    * set
    *
    * @param string $name
    * @param callable|object $service
    * @param bool $singleton
    *
    * @return mixed
    */
   public function set(string $name, callable|object $service, bool $singleton): mixed {
      $this->services[$name] = [
         'definition' => $service,
         'shared' => $singleton,
      ];

      return $service;
   }

   /**
    * get
    *
    * @param string $name
    *
    * @return mixed
    */
   public function get(string $name): mixed {
      if (isset($this->instances[$name])) {
         return $this->instances[$name];
      }

      if (!isset($this->services[$name])) {
         throw new ExceptionHandler('Error', "Service '{$name}' not found.");
      }

      $service = $this->services[$name];
      $definition = $service['definition'];

      if (is_callable($definition)) {
         $instance = $definition();
      } elseif (is_object($definition)) {
         $instance = $definition;
      } else {
         throw new ExceptionHandler('Error', "Service definition for '{$name}' must be callable or object.");
      }

      if ($service['shared']) {
         $this->instances[$name] = $instance;
      }

      return $instance;
   }

   /**
    * resolve
    *
    * @param string $class
    *
    * @return object
    */
   public function resolve(string $class): object {
      if (!isset($this->reflections[$class])) {
         $this->reflections[$class] = new ReflectionClass($class);
      }

      $reflection = $this->reflections[$class];
      $constructor = $reflection->getConstructor();
      if ($constructor === null) {
         return $reflection->newInstance();
      }

      $parameters = $constructor->getParameters();
      if ($parameters === []) {
         return $reflection->newInstance();
      }

      $dependencies = array_map(function ($parameter) {
         $type = $parameter->getType();

         if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {

            if ($parameter->isDefaultValueAvailable()) {
               return $parameter->getDefaultValue();
            }

            if ($type->getName() === self::class) {
               return $this;
            }

            if ($name = array_search($type->getName(), $this->providers)) {
               return $this->get($name);
            }

            return $this->resolve($type->getName());
         }

         if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
         }
         throw new ExceptionHandler('Error', "Cannot resolve parameter '{$parameter->getName()}' of type '{$type}'.");
      }, $parameters);

      $instance = $reflection->newInstanceWithoutConstructor();
      $constructor->invokeArgs($instance, $dependencies);

      return $instance;
   }
}
