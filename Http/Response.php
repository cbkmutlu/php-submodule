<?php

declare(strict_types=1);

namespace System\Http;

class Response {
   public $codes;

   public function __construct() {
      $this->codes = config('defines.status');
   }

   public function status(?int $code = null): int {
      if (is_null($code)) {
         return http_response_code();
      }
      return http_response_code($code);
   }

   public function message(?int $code = null): string {
      if (is_null($code)) {
         return $this->codes[$this->status()];
      }

      return $this->codes[$code];
   }

   public function json(int $code, string $message, mixed $data = null): void {
      header_remove();
      $this->status($code);

      header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
      header('Content-type: application/json');
      header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $this->codes[$code], true, $code);

      print(json_encode([
         'status' => $code < 300,
         'code' => $code,
         'message' => $message,
         'data' => $data
      ]));
   }
}
