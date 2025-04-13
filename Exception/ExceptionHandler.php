<?php

declare(strict_types=1);

namespace System\Exception;

use Exception;

class ExceptionHandler extends Exception {
   public function __construct(string $title, string $body) {

      // strRpos
      if ($colon = strrpos($body, ':')) {
         $body = trim(substr($body, $colon + 1));
      }

      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept  = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($content || $accept) {
         header_remove();
         http_response_code(500);
         print(json_encode([
            'status' => false,
            'code' => 500,
            'message' => $title . ': ' . $body,
         ]));
         exit();
      }

      parent::__construct(strip_tags($title . ': ' . $body), 1);
   }
}
