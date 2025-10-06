<?php

declare(strict_types=1);

namespace System\Exception;

use Exception;

class SystemException extends Exception {
   public function __construct(string $message = 'System Error', int $code = 500) {
      parent::__construct(strip_tags($message), $code);
   }
}
