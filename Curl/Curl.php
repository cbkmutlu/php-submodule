<?php

declare(strict_types=1);

namespace System\Curl;

use System\Exception\SystemException;

class Curl {
   private $curl;
   private array $options = [];
   private array $headers = [];
   private ?string $referrer = null;
   private ?string $auth = null;
   private string $response_body = '';
   private array $response_header = [];
   private string $user_agent;
   private bool $redirect;
   private bool $use_cookie;
   private string $path;

   public function __construct() {
      $config           = import_config('defines.curl');
      $this->user_agent = $config['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'PHP-Curl');
      $this->redirect   = $config['redirect'] ?? true;
      $this->use_cookie = $config['use_cookie'] ?? false;
      $this->path       = APP_DIR . ($config['path'] ?? 'Storage/Cookies/cookie.txt');

      $this->checkPath();
   }

   public function get(string $url, ?array $params = null): void {
      if (!empty($params)) {
         $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
      }
      $this->request('GET', $url);
   }

   public function post(string $url, array $params = []): void {
      $this->request('POST', $url, $params);
   }

   public function put(string $url, array $params = []): void {
      $this->request('PUT', $url, $params);
   }

   public function delete(string $url, array $params = []): void {
      $this->request('DELETE', $url, $params);
   }

   public function head(string $url, array $params = []): void {
      $this->request('HEAD', $url, $params);
   }

   public function setOptions(array $options): self {
      $this->options = $options;
      return $this;
   }

   public function setHeader(array $headers): self {
      $this->headers = $headers;
      return $this;
   }

   public function setReferrer(string $referrer): self {
      $this->referrer = $referrer;
      return $this;
   }

   public function setUseCookie(bool $cookie = true): self {
      $this->use_cookie = $cookie;
      return $this;
   }

   public function setAuth(string $user, string $password): self {
      $this->auth = $user . ':' . $password;
      return $this;
   }

   public function setUserAgent(string $agent): self {
      $this->user_agent = $agent;
      return $this;
   }

   public function getResponseBody(): string {
      return $this->response_body;
   }

   public function getResponseHeader(): array {
      return $this->response_header;
   }

   private function request(string $method, string $url, array $params = []): void {
      $this->curl = curl_init();

      curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->curl, CURLOPT_HEADER, true);
      curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->redirect);
      curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent);

      if ($this->use_cookie) {
         if (!file_exists($this->path)) {
            file_put_contents($this->path, '');
         }
         curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->path);
         curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->path);
      }

      if ($this->auth) {
         curl_setopt($this->curl, CURLOPT_USERPWD, $this->auth);
      }

      if (!empty($this->headers)) {
         $header_lines = [];
         foreach ($this->headers as $k => $v) {
            $header_lines[] = "$k: $v";
         }
         curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header_lines);
      }

      $method = strtoupper($method);
      curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);

      if (in_array($method, ['POST', 'PUT', 'DELETE']) && !empty($params)) {
         curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($params));
      }

      if ($this->referrer) {
         curl_setopt($this->curl, CURLOPT_REFERER, $this->referrer);
      }

      foreach ($this->options as $opt => $val) {
         curl_setopt($this->curl, $opt, $val);
      }

      $response = curl_exec($this->curl);

      if ($response === false) {
         $err = curl_error($this->curl);
         curl_close($this->curl);
         throw new SystemException("Curl request failed: $err");
      }

      $header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
      $header_text = substr($response, 0, $header_size);
      $this->response_body = substr($response, $header_size);

      $this->response_header = $this->parseHeaders($header_text);

      $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
      if ($http_code >= 400) {
         curl_close($this->curl);
         throw new SystemException("HTTP error code: $http_code");
      }

      curl_close($this->curl);
   }

   private function parseHeaders(string $raw): array {
      $headers = [];
      $lines = explode("\r\n", trim($raw));
      foreach ($lines as $line) {
         if (strpos($line, ':') !== false) {
            [$key, $value] = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
         }
      }
      return $headers;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new SystemException('Curl directory [' . $this->path . '] cannot be created');
      }

      if (!check_permission($this->path)) {
         throw new SystemException('Curl directory [' . $this->path . '] not writable');
      }
   }
}
