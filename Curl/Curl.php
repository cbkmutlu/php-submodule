<?php

declare(strict_types=1);

namespace System\Curl;

use System\Language\Language;
use System\Exception\SystemException;

class Curl {
   private $curl;
   private $options = [];
   private $headers = [];
   private $referrer;
   private $auth;
   private $response_body;
   private $response_header;
   private $user_agent;
   private $redirect;
   private $use_cookie;
   private $path;

   public function __construct(
      private Language $language
   ) {
      $config = import_config('defines.curl');
      $this->user_agent = $config['user_agent'];
      $this->redirect = $config['redirect'];
      $this->use_cookie = $config['use_cookie'];
      $this->path = APP_DIR . $config['path'];

      if (empty($this->user_agent)) {
         if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
         }
      }
   }

   public function head(string $url, array $params = []): void {
      $this->request('HEAD', $url, $params);
   }

   public function get(string $url, ?array $params = null): void {
      if (is_array($params)) {
         $url = $url . array_serialize($params);
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

   public function setOptions(mixed $options, ?string $value = null): self {
      if (is_array($options)) {
         $this->options = $options;
      } else {
         $this->options[$options] = $value;
      }

      return $this;
   }

   public function getOptions(): array {
      return $this->options;
   }

   public function setRedirect(bool $redirect = true): self {
      $this->redirect = $redirect;
      return $this;
   }

   public function getRedirect(): bool {
      return $this->redirect;
   }

   public function setHeader(mixed $header, ?string $value = null): self {
      if (is_array($header)) {
         $this->headers = $header;
      } else {
         $this->headers[$header] = $value;
      }

      return $this;
   }

   public function getHeader(): array {
      return $this->headers;
   }

   public function setReferrer(string $referrer): self {
      $this->referrer = $referrer;
      return $this;
   }

   public function getReferrer(): string {
      return $this->referrer;
   }

   public function setPath(string $path): self {
      $this->path = APP_DIR . $path;
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setUseCookie(bool $cookie = true): self {
      $this->use_cookie = $cookie;
      return $this;
   }

   public function getUseCookie(): bool {
      return $this->use_cookie;
   }

   public function setUserAgent(string $agent): self {
      $this->user_agent = $agent;
      return $this;
   }

   public function getUserAgent(): string {
      return $this->user_agent;
   }

   public function setAuth(string $user, string $password): self {
      $this->auth = $user . ':' . $password;
      return $this;
   }

   public function getAuth(): string {
      return $this->auth;
   }

   public function getResponseHeader(?string $key = null): mixed {
      if (is_null($key)) {
         return $this->response_header;
      } else {
         if (isset($this->response_header[$key])) {
            return $this->response_header[$key];
         }

         return null;
      }
   }

   public function getResponseBody(): string {
      return $this->response_body;
   }

   private function request(string $method, string $url, array $params = []): void {
      $this->curl = curl_init();

      $headers = [];
      foreach ($this->headers as $key => $value) {
         $headers[] = $key . ': ' . $value;
      }
      curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

      $method = strtoupper($method);
      $options = [
         'HEAD' => CURLOPT_NOBODY,
         'GET'  => CURLOPT_HTTPGET,
         'POST' => CURLOPT_POST,
      ];
      if (isset($options[$method])) {
         curl_setopt($this->curl, $options[$method], true);
      } else {
         curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
      }

      curl_setopt($this->curl, CURLOPT_URL, $url);
      if (!empty($params)) {
         curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);
      }

      curl_setopt($this->curl, CURLOPT_HEADER, true);
      curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent);

      if ($this->use_cookie) {
         curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->path);
         curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->path);
      }

      if ($this->redirect) {
         curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
      }

      if ($this->referrer) {
         curl_setopt($this->curl, CURLOPT_REFERER, $this->referrer);
      }

      if ($this->auth) {
         curl_setopt($this->curl, CURLOPT_USERPWD, $this->auth);
      }

      foreach ($this->options as $option => $value) {
         curl_setopt($this->curl, constant('CURLOPT_' . str_replace('CURLOPT_', '', strtoupper($option))), $value);
      }

      $response = curl_exec($this->curl);
      if ($response) {
         $pattern = '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims';

         if (!preg_match_all($pattern, $response, $matches) || empty($matches[0])) {
            throw new SystemException("Curl request was sent but the response was not received");
         }

         $headers_string = array_pop($matches[0]);
         $headers = explode("\r\n", str_replace("\r\n\r\n", '', $headers_string));
         $this->response_body = str_replace($headers_string, '', $response);

         $version_and_status = array_shift($headers);

         if (!preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches)) {
            throw new SystemException("Curl response status is invalid [{$version_and_status}]");
         }

         $this->response_header['Http-Version'] = $matches[1];
         $this->response_header['Status-Code'] = $matches[2];
         $this->response_header['Status'] = $matches[2] . ' ' . $matches[3];

         foreach ($headers as $header) {
            [$key, $value] = explode(':', $header, 2) + [null, null];
            if ($key !== null && $value !== null) {
               $this->response_header[trim($key)] = trim($value);
            }
         }
      } else {
         $error = curl_error($this->curl);
         curl_close($this->curl);
         throw new SystemException("Curl request failed [{$error}]");
      }

      curl_close($this->curl);
   }
}
