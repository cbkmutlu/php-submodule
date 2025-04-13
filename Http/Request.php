<?php

declare(strict_types=1);

namespace System\Http;

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

      return isset($this->get[$param]) ? $this->filter($this->get[$param], $filter) : false;
   }

   public function post(?string $param = null, $filter = true): mixed {
      if (is_null($param)) {
         return $this->filter($this->post, $filter);
      }

      return isset($this->post[$param]) ? $this->filter($this->post[$param], $filter) : false;
   }

   public function put(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents("php://input"), $_PUT);

      if (is_null($param)) {
         return $this->filter($_PUT, $filter);
      }

      return isset($_PUT[$param]) ? $this->filter($_PUT[$param], $filter) : false;
   }

   public function patch(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents('php://input'), $_PATCH);

      if (is_null($param)) {
         return $this->filter($_PATCH, $filter);
      }

      return isset($_PATCH[$param]) ? $this->filter($_PATCH[$param], $filter) : false;
   }

   public function delete(?string $param = null, $filter = true): mixed {
      parse_str(file_get_contents("php://input"), $_DELETE);

      if (is_null($param)) {
         return $this->filter($_DELETE, $filter);
      }

      return isset($_DELETE[$param]) ? $this->filter($_DELETE[$param], $filter) : false;
   }

   public function json(?string $param = null, bool $filter = true): mixed {
      $body = [];
      if (!str_contains($this->headers('Content-Type'), 'multipart/form-data') && (int) $this->headers('Content-Length') <= $this->checkSize()) {
         $contents = file_get_contents('php://input');

         if ($contents !== false && $contents !== '') {
            $body = json_decode($contents, true);
         }
      }

      if (is_null($param)) {
         return $this->filter($body, $filter);
      }

      return isset($body[$param]) ? $this->filter($body[$param], $filter) : [];
   }

   public function files(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->files;
      }

      return isset($this->file[$param]) ? $this->files[$param] : false;
   }

   public function server(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->server;
      }

      return $this->server[$param] ?? null;
   }

   public function cookie(?string $param = null): mixed {
      if (is_null($param)) {
         return $this->cookie;
      }

      return isset($this->cookie[$param]) ? $this->cookie[$param] : false;
   }

   public function all(bool $filter = true): mixed {
      return $this->filter(array_merge($_REQUEST, $this->json(null)), $filter);
   }

   public function headers(?string $param = null): mixed {
      $headers = getallheaders();

      if (is_null($param)) {
         return getallheaders();
      }

      $response = [];
      foreach ($headers as $key => $val) {
         $response[$key] = $val;
      }

      return $response[ucwords($param)];
   }

   public function method(): string {
      return $this->server('REQUEST_METHOD');
   }

   public function protocol(): string {
      return stripos($this->server('SERVER_PROTOCOL'), 'https') === true ? 'https' : 'http';
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

      if (strstr($uri, '?')) {
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
      return trim($this->server('HTTP_REFERER') ?? '');
   }

   public function segments(?int $index = null): mixed {
      $segments = explode('/', trim(parse_url($this->server('REQUEST_URI'), PHP_URL_PATH), '/'));

      if ($index === -1) {
         return end($segments);
      }

      if ($index === null) {
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
      if (is_null($data)) {
         return null;
      }

      if (is_array($data)) {
         return $filter === true ? array_map([$this, 'xss'], $data) : array_map('trim', $data);
      }

      return $filter === true ? $this->checkXss(trim((string) $data)) : trim((string) $data);
   }

   public function isUri(): bool {
      return preg_match('#^/[a-z0-9\-\/]+$#i', $this->uri()) > 0;
   }

   public function isJson(): bool {
      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept  = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($content || $accept) {
         return true;
      }

      return false;
   }

   public function isAjax(): bool {
      return null !== $this->server('HTTP_X_REQUESTED_WITH') && strtolower($this->server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';
   }

   public function isSecure(): bool {
      return $this->server('HTTPS') !== null || ($this->server('HTTP_X_FORWARDED_PROTO') === 'https');
   }

   public function isRobot(): bool {
      return null !== $this->server('HTTP_USER_AGENT') && preg_match('/curl|wget|python|bot|crawl|spider/i', $this->server('HTTP_USER_AGENT'));
   }

   public function isMobile(): bool {
      return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $this->server("HTTP_USER_AGENT"));
   }

   public function isReferral(): bool {
      return !empty($this->server('HTTP_REFERER'));
   }

   private function checkXss(string $data): string {
      // $data = str_replace( array('<','>',"'",'"',')','('), array('&lt;','&gt;','&apos;','&#x22;','&#x29;','&#x28;'), $data );
      // $data = str_ireplace( '%3Cscript', '', $data );
      // return $data;
      return trim(htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
