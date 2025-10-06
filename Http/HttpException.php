<?php

declare(strict_types=1);

namespace System\Http;

use System\Exception\SystemException;

class HttpException extends SystemException {
   public function __construct(string $message = 'Curl Error', int $code = 500) {
      parent::__construct($message, $code);
   }
}
