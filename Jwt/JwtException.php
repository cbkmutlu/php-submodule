<?php

declare(strict_types=1);

namespace System\Jwt;

use System\Exception\SystemException;

class JwtException extends SystemException {
   public function __construct(string $message = 'Jwt Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
