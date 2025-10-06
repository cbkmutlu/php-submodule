<?php

declare(strict_types=1);

namespace System\Database;

use System\Exception\SystemException;

class DatabaseException extends SystemException {
   public function __construct(string $message = 'Database Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
