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
   private $user;

   public function __construct() {
      $this->get = $_GET;
      $this->post = $_POST;
      $this->cookie = $_COOKIE;
      $this->files = $_FILES;
      $this->server = $_SERVER;
   }

   public function setUser(array $data): self {
      $this->user = $data;
      return $this;
   }

   public function getUser(?string $param = null): mixed {
      if ($param === null) {
         return $this->user;
      }

      return $this->user[$param] ?? null;
   }

   public function get(?string $param = null, bool $filter = true): mixed {
      if ($param === null) {
         return $this->filter($this->get, $filter);
      }

      return $this->filter($this->get[$param] ?? null, $filter);
   }

   public function post(?string $param = null, bool $filter = true): mixed {
      if ($param === null) {
         return $this->filter($this->post, $filter);
      }

      return $this->filter($this->post[$param] ?? null, $filter);
   }

   public function put(?string $param = null, bool $filter = true): mixed {
      parse_str(file_get_contents('php://input'), $_PUT);

      if ($param === null) {
         return $this->filter($_PUT, $filter);
      }

      return $this->filter($_PUT[$param] ?? null, $filter);
   }

   public function patch(?string $param = null, bool $filter = true): mixed {
      parse_str(file_get_contents('php://input'), $_PATCH);

      if ($param === null) {
         return $this->filter($_PATCH, $filter);
      }

      return $this->filter($_PATCH[$param] ?? null, $filter);
   }

   public function delete(?string $param = null, bool $filter = true): mixed {
      parse_str(file_get_contents('php://input'), $_DELETE);

      if ($param === null) {
         return $this->filter($_DELETE, $filter);
      }

      return $this->filter($_DELETE[$param] ?? null, $filter);
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

      if ($param === null) {
         return $this->filter($body, $filter);
      }

      return $this->filter($body[$param] ?? null, $filter);
   }

   public function files(?string $param = null): mixed {
      if ($param === null) {
         return $this->files;
      }

      return $this->files[$param] ?? null;
   }

   public function server(?string $param = null): mixed {
      if ($param === null) {
         return $this->server;
      }

      return $this->server[$param] ?? null;
   }

   public function cookie(?string $param = null): mixed {
      if ($param === null) {
         return $this->cookie;
      }

      return $this->cookie[$param] ?? null;
   }

   public function all(bool $filter = true): mixed {
      return $this->filter(array_merge($_REQUEST, $this->json(null)), $filter);
   }

   public function headers(?string $param = null): mixed {
      $headers = getallheaders();

      if ($param === null) {
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

   public function origin(): string {
      return $this->protocol() . '://' . $this->host();
   }

   public function href(): string {
      return $this->protocol() . '://' . $this->host() . $this->uri();
   }

   public function script(): string {
      return $this->server('SCRIPT_NAME');
   }

   public function content(?int $index = null): mixed {
      if ($index === null) {
         return $this->headers('Accept');
      }

      return explode(',', $this->headers('Accept'))[$index];
   }

   public function referrer(): string {
      return $this->server('HTTP_REFERER') ? trim($this->server('HTTP_REFERER')) : '';
   }

   public function authorization(): string {
      $auth = null;
      if ($this->server('Authorization')) {
         $auth = $this->server['Authorization'];
      } elseif ($this->server('HTTP_AUTHORIZATION')) {
         $auth = $this->server['HTTP_AUTHORIZATION'];
      } else {
         if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
         } else {
            $headers = getallheaders();
         }

         $headers = array_combine(array_map('ucwords', array_keys($headers)), array_values($headers));
         if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
         }
      }

      return is_string($auth) ? trim($auth) : '';
   }

   public function bearer(): ?string {
      $auth = $this->authorization();

      if (!$auth) {
         return null;
      }

      if (substr_count($auth, ' ') > 1) {
         return null;
      }

      $auth = trim($auth);
      [$type, $token] = array_pad(explode(' ', $auth, 2), 2, null);

      if (strcasecmp($type, 'Bearer') !== 0 || !$token) {
         return null;
      }

      return $token;
   }

   public function segments(?int $index = null): mixed {
      $segments = explode('/', trim(parse_url($this->server('REQUEST_URI'), PHP_URL_PATH), '/'));

      if ($index === -1) {
         return end($segments);
      } elseif ($index === null) {
         return $segments;
      }

      return isset($segments[$index]) ? $segments[$index] : null;
   }

   public function locales(?int $index = null): mixed {
      $locales = explode(',', preg_replace('/(;q=[0-9\.]+)/i', '', strtolower(trim($this->server('HTTP_ACCEPT_LANGUAGE')))));

      return isset($locales[$index]) ? [$locales[$index]] : $locales;
   }

   public function query(?array $data = null): mixed {
      if ($data === null) {
         return $this->server('QUERY_STRING');
      }

      return http_build_query($data);
   }

   public function userAgent(): string {
      return $this->server('HTTP_USER_AGENT') ?? 'UNKNOWN';
   }

   public function userIp(): string {
      $headers = [
         'HTTP_CF_CONNECTING_IP',
         'HTTP_X_FORWARDED_FOR',
         'HTTP_FORWARDED_FOR',
         'HTTP_FORWARDED',
         'HTTP_X_REAL_IP',
         'HTTP_CLIENT_IP',
         'REMOTE_ADDR'
      ];

      foreach ($headers as $header) {
         if (!empty($this->server($header))) {
            $ip = ($header === 'HTTP_X_FORWARDED_FOR') ? explode(',', $this->server($header))[0] : $this->server($header);

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
               return $ip;
            }
         }
      }

      return $this->server('REMOTE_ADDR') ?? 'UNKNOWN';
   }

   public function filter(mixed $data = null, bool $filter = false): mixed {
      if ($filter === false || $data === null) {
         return $data;
      }

      if (is_array($data)) {
         foreach ($data as $key => $value) {
            $data[$key] = $this->filter($value, true); // recursive
         }
         return $data;
      }

      return $data;
      // return trim(htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      // return escape_xss($data);
   }

   public function isUri(): bool {
      $url = $this->origin() . uri_get();

      if (filter_var($url, FILTER_VALIDATE_URL) === false) {
         return false;
      }

      return preg_match('#^/[a-zA-Z0-9/_\-]*$#', uri_get()) === 1;
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
      return preg_match('/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i', $this->server('HTTP_USER_AGENT')) > 0;
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
