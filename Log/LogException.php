<?php

declare(strict_types=1);

namespace System\Log;

use System\Exception\SystemException;

class LogException extends SystemException {
   public function __construct(string $message = 'Log Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
