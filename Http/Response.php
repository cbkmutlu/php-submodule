<?php

declare(strict_types=1);

namespace System\Http;

class Response {
   public $codes;
   public $body;

   public function __construct() {
      $this->codes = import_config('defines.status');
   }

   public function status(?int $code = null): int {
      if ($code !== null) {
         http_response_code($code);
         return $code;
      }

      $current = http_response_code();

      return $current === false ? 200 : $current;
   }

   public function message(?int $code = null): string {
      $code = $code ?? $this->status();

      return $this->codes[$code] ?? $this->codes[500];
   }

   public function json(mixed $payload, ?int $code = 200): void {
      $this->status($code);

      // normalize
      $data = null;
      $meta = null;
      $message = null;
      $error = null;

      if (is_array($payload) && array_key_exists('data', $payload)) {
         $data = $payload['data'];
         $meta = $payload['meta'] ?? null;
         $message = $payload['message'] ?? $this->message($code);
         $error = $payload['error'] ?? null;
      } else {
         $data = $payload;
      }

      // response
      $response = [
         'success' => $code < 300,
         'message' => $message,
         'data'    => $data,
         'error'   => $error,
         'meta'    => $meta,
         'status'  => $code
      ];

      // headers
      $config = import_config('defines.header');
      header('Access-Control-Allow-Origin: ' . $config['allow-origin']);
      header('Access-Control-Allow-Headers: ' . $config['allow-headers']);
      header('Access-Control-Allow-Methods: ' . $config['allow-methods']);
      header('Access-Control-Allow-Credentials: ' . $config['allow-credentials']);
      header('Content-Type: application/json; charset=UTF-8');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
      print(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
   }

   public function body(?string $body, ?int $code = null): void {
      $code = $this->status($code);
      $this->body = $body;

      header('Content-Type: text/html; charset=UTF-8');
      print($body);
   }
}
