<?php

declare(strict_types=1);

namespace System\Router;

use System\Exception\SystemException;

class RouterException extends SystemException {
   public function __construct(string $message = 'Router Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
