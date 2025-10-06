<?php

declare(strict_types=1);

namespace System\Event;

use System\Exception\SystemException;

class Event {
   private $action = 'handle';
   private $params;
   private $listeners;

   public function listener(string $event): self {
      $listeners = import_config('services.listeners');
      $this->listeners = $listeners[$event];

      return $this;
   }

   public function action(string $action): self {
      $this->action = $action;

      return $this;
   }

   public function params(array $params): self {
      $this->params = $params;

      return $this;
   }

   public function fire(): void {
      foreach ($this->listeners as $listener) {
         if (!class_exists($listener)) {
            throw new SystemException("Listener class [{$listener}] not found");
         }

         if (!method_exists($listener, $this->action)) {
            throw new SystemException("Listener method [{$this->action}] not found in class [{$listener}]");
         }

         call_user_func_array([new $listener, $this->action], $this->params);
      }
   }
}
