<?php

declare(strict_types=1);

namespace System\Http;

use System\Http\HttpException;

class Request {
   private $get;
   private $post;
   private $files;
   private $server;
   private $cookie;

   public function __construct() {
      $this->get = $_GET;
      $this->post = $_POST;
      $this->cookie = $_COOKIE;
      $this->files = $_FILES;
      $this->server = $_SERVER;
   }

   public function get(?string $param = null, $filter = true): mixed {
      if (is_null($param)) {
         return $this->filter($this->get, $filter);
      }

      return isset($this->get[$param]) ? $this->filter($this->get[$param], $filter) : null;
   }

   public function post(?string $param = null, $filter = true): mixed {
      if (is_null($param)) {
         return $this->filter($this->post, $filter);
      }

      return isset($this->post[$param]) ? $this->filter($this->post[$param], $filter) : null;
   }

   public function put(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents("php://input"), $_PUT);

      if (is_null($param)) {
         return $this->filter($_PUT, $filter);
      }

      return isset($_PUT[$param]) ? $this->filter($_PUT[$param], $filter) : null;
   }

   public function patch(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents('php://input'), $_PATCH);

      if (is_null($param)) {
         return $this->filter($_PATCH, $filter);
      }

      return isset($_PATCH[$param]) ? $this->filter($_PATCH[$param], $filter) : null;
   }

   public function delete(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents("php://input"), $_DELETE);

      if (is_null($param)) {
         return $this->filter($_DELETE, $filter);
      }

      return isset($_DELETE[$param]) ? $this->filter($_DELETE[$param], $filter) : null;
   }

   public function json(?string $param = null, bool $filter = true): mixed {
      $body = [];
      if (!str_contains($this->headers('Content-Type'), 'multipart/form-data') && (int) $this->headers('Content-Length') <= $this->checkSize()) {
         $contents = file_get_contents('php://input');

         if ($contents) {
            $body = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
               throw new HttpException(json_last_error_msg(), 400);
            }
         }
      }

      if (is_null($param)) {
         return $this->filter($body, $filter);
      }

      return isset($body[$param]) ? $this->filter($body[$param], $filter) : null;
   }

   public function files(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->files;
      }

      return isset($this->files[$param]) ? $this->files[$param] : null;
   }

   public function server(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->server;
      }

      return isset($this->server[$param]) ? $this->server[$param] : null;
   }

   public function cookie(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->cookie;
      }

      return isset($this->cookie[$param]) ? $this->cookie[$param] : null;
   }

   public function all(bool $filter = true): mixed {
      return $this->filter(array_merge($_REQUEST, $this->json(null)), $filter);
   }

   public function headers(?string $param = null): mixed {
      $headers = getallheaders();

      if (is_null($param)) {
         return $headers;
      }

      $response = [];
      foreach ($headers as $key => $val) {
         $response[$key] = $val;
      }

      return $response[ucwords($param)] ?? null;
   }

   public function method(): string {
      return $this->server('REQUEST_METHOD');
   }

   public function protocol(): string {
      return stripos($this->server('SERVER_PROTOCOL'), 'https') === 0 ? 'https' : 'http';
   }

   public function uri(): string {
      return $this->server('REQUEST_URI');
   }

   public function host(): string {
      return $this->server('HTTP_HOST');
   }

   public function pathname(): string {
      $path = array_slice(explode('/', $this->server('SCRIPT_NAME')), 0, -1);
      $path = implode('/', $path) . '/';
      $uri = substr($this->server('REQUEST_URI'), strlen($path));

      if (strpos($uri, '?') !== false) {
         $uri = substr($uri, 0, strpos($uri, '?'));
      }

      return '/' . trim($uri, '/');
   }

   public function origin(): string {
      return $this->protocol() . "://" . $this->host();
   }

   public function href(): string {
      return $this->protocol() . "://" . $this->host() . $this->uri();
   }

   public function script(): string {
      return $this->server('SCRIPT_NAME');
   }

   public function content(?int $index = null): mixed {
      if (is_null($index)) {
         return $this->headers('Accept');
      }

      return explode(',', $this->headers('Accept'))[$index];
   }

   public function referrer(): string {
      return $this->server('HTTP_REFERER') ? trim($this->server('HTTP_REFERER')) : '';
   }

   public function authorization(): string {
      $headers = null;
      if ($this->server('Authorization')) {
         $headers = $this->server["Authorization"];
      } else if ($this->server('HTTP_AUTHORIZATION')) {
         $headers = $this->server["HTTP_AUTHORIZATION"];
      } else {
         if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
         } else {
            $headers = getallheaders();
         }

         $headers = array_combine(array_map('ucwords', array_keys($headers)), array_values($headers));
         if (isset($headers['Authorization'])) {
            $headers = $headers['Authorization'];
         }
      }

      return trim($headers);
   }

   public function segments(?int $index = null): mixed {
      $segments = explode('/', trim(parse_url($this->server('REQUEST_URI'), PHP_URL_PATH), '/'));

      if ($index === -1) {
         return end($segments);
      }

      if (is_null($index)) {
         return $segments;
      }

      return isset($segments[$index]) ? $segments[$index] : null;
   }

   public function locales(?int $index = null): mixed {
      $locales = explode(',', preg_replace('/(;q=[0-9\.]+)/i', '', strtolower(trim($this->server('HTTP_ACCEPT_LANGUAGE')))));

      return isset($locales[$index]) ? [$locales[$index]] : $locales;
   }

   public function query(?array $data = null): mixed {
      if (is_null($data)) {
         return $this->server('QUERY_STRING');
      }

      return http_build_query($data);
   }

   public function ip(): string {
      if (getenv('HTTP_CLIENT_IP')) {
         return getenv('HTTP_CLIENT_IP');
      }

      if (getenv('HTTP_X_FORWARDED_FOR')) {
         return getenv('HTTP_X_FORWARDED_FOR');
      }

      if (getenv('HTTP_X_FORWARDED')) {
         return getenv('HTTP_X_FORWARDED');
      }

      if (getenv('HTTP_FORWARDED_FOR')) {
         return getenv('HTTP_FORWARDED_FOR');
      }

      if (getenv('HTTP_FORWARDED')) {
         return getenv('HTTP_FORWARDED');
      }

      if (getenv('REMOTE_ADDR')) {
         return getenv('REMOTE_ADDR');
      }

      return 'UNKNOWN';
   }

   public function filter(mixed $data = null, bool $filter = false): mixed {
      if (!$filter || is_null($data)) {
         return $data;
      }

      if (is_array($data)) {
         foreach ($data as $key => $value) {
            $data[$key] = $this->filter($value, true); // recursive
         }
         return $data;
      }

      return escape_xss($data);
   }

   public function isUri(): bool {
      $url = $this->origin() . $this->pathname();

      if (filter_var($url, FILTER_VALIDATE_URL) === false) {
         return false;
      }

      return preg_match('#^/[a-zA-Z0-9/_\-]*$#', $this->pathname()) === 1;
   }

   public function isJson(): bool {
      $content = null !== $this->server('CONTENT_TYPE') && str_contains($this->server('CONTENT_TYPE'), 'application/json');
      $accept = null !== $this->server('HTTP_ACCEPT') && str_contains($this->server('HTTP_ACCEPT'), 'application/json');

      if ($content || $accept) {
         return true;
      }

      return false;
   }

   public function isAjax(): bool {
      return null !== $this->server('HTTP_X_REQUESTED_WITH') && strtolower($this->server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';
   }

   public function isSecure(): bool {
      return null !== $this->server('HTTPS') || null !== $this->server('HTTP_X_FORWARDED_PROTO') && $this->server('HTTP_X_FORWARDED_PROTO') === 'https';
   }

   public function isRobot(): bool {
      return null !== $this->server('HTTP_USER_AGENT') && preg_match('/curl|wget|python|bot|crawl|spider/i', $this->server('HTTP_USER_AGENT'));
   }

   public function isMobile(): bool {
      return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $this->server("HTTP_USER_AGENT")) > 0;
   }

   public function isReferral(): bool {
      return null !== $this->server('HTTP_REFERER');
   }

   private function checkSize(): int {
      $postMaxSize = ini_get('post_max_size');

      return match (strtoupper(substr($postMaxSize, -1))) {
         'G'     => (int) str_replace('G', '', $postMaxSize) * 1024 ** 3,
         'M'     => (int) str_replace('M', '', $postMaxSize) * 1024 ** 2,
         'K'     => (int) str_replace('K', '', $postMaxSize) * 1024,
         default => (int) $postMaxSize,
      };
   }
}
