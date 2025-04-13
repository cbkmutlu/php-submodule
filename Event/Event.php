<?php

declare(strict_types=1);

namespace System\Event;

use System\Exception\ExceptionHandler;

class Event {
   private $action = 'handle';
   private $params = [];
   private $listeners = null;

   public function setListener(string $event): self {
      $listeners = config('services.listeners');
      $this->listeners = $listeners[$event];

      return $this;
   }

   public function setAction(string $action): self {
      $this->action = $action;

      return $this;
   }

   public function setParams(array $params): self {
      $this->params = $params;

      return $this;
   }

   public function fire(): void {
      foreach ($this->listeners as $listener) {
         if (!is_array($this->listeners)) {
            throw new ExceptionHandler('Error', "Listener class '{$listener}' not found.");
         }

         if (!class_exists($listener)) {
            throw new ExceptionHandler('Error', "Listener class '{$listener}' not found.");
         }

         if (!method_exists($listener, $this->action)) {
            throw new ExceptionHandler('Error', "Listener method '{$this->action}()' not found in class '{$listener}'.");
         }

         call_user_func_array(array(new $listener, $this->action), $this->params);
      }
   }
}
