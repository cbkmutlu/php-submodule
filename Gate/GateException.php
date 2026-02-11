<?php

declare(strict_types=1);

namespace System\Gate;

use System\Exception\SystemException;

class GateException extends SystemException {
   public function __construct(string $message = 'Gate Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
