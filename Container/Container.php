<?php

declare(strict_types=1);

namespace System\Container;

use ReflectionClass;
use ReflectionNamedType;
use System\Exception\SystemException;

class Container {
   private $providers;
   private $services;
   private $instances;
   private $reflections;

   public function __construct() {
      $this->providers = import_config('services.providers');
   }

   public function register(): void {
      foreach ($this->providers as $key => $definition) {
         if (is_array($definition)) {
            [$name, $singleton] = $definition;
         } else {
            $name = $definition;
            $singleton = false;
         }

         $this->services[$key] = [
            'definition' => function () use ($name) {
               return $this->resolve($name);
            },
            'singleton' => $singleton,
         ];
      }
   }

   public function set(string $name, object $service, bool $singleton): void {
      $this->services[$name] = [
         'definition' => $service,
         'singleton' => $singleton,
      ];
   }

   public function get(string $name): object {
      if (isset($this->instances[$name])) {
         return $this->instances[$name];
      }

      if (!isset($this->services[$name])) {
         throw new SystemException("Service not found [{$name}]");
      }

      $service = $this->services[$name];
      $definition = $service['definition'];

      if (is_callable($definition)) {
         $instance = $definition();
      } elseif (is_object($definition)) {
         $instance = $definition;
      } else {
         throw new SystemException("Service definition must be callable or object [{$name}]");
      }

      if ($service['singleton']) {
         $this->instances[$name] = $instance;
      }

      return $instance;
   }

   public function resolve(string $class): object {
      if (!isset($this->reflections[$class])) {
         $this->reflections[$class] = new ReflectionClass($class);
      }

      $reflection = $this->reflections[$class];
      if (!$reflection->isInstantiable()) {
         throw new SystemException("Class is not instantiable [{$class}]");
      }

      $constructor = $reflection->getConstructor();
      if (!$constructor) {
         return $reflection->newInstance();
      }

      $parameters = $constructor->getParameters();
      if (empty($parameters)) {
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

            foreach ($this->providers as $key => $definition) {
               $name = is_array($definition) ? $definition[0] : $definition;
               if ($name === $type->getName()) {
                  return $this->get($key);
               }
            }

            return $this->resolve($type->getName());
         }

         if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
         }

         throw new SystemException("Cannot resolve parameter [{$parameter->getName()}] of type [{$type}].");
      }, $parameters);

      $instance = $reflection->newInstanceWithoutConstructor();
      $constructor->invokeArgs($instance, $dependencies);

      return $instance;
   }
}
