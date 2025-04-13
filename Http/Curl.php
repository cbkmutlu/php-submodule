<?php

declare(strict_types=1);

namespace System\Http;

class Curl {
   private $ch = null;
   private $followRedirects = true;
   private $options = [];
   private $headers = [];
   private $referrer = null;
   private $useCookie = false;
   private $cookieFile = '';
   private $userAgent = '';
   private $responseBody = '';
   private $responseHeader = [];
   private $error = '';

   function __construct() {
      if ($this->useCookie) {
         $this->cookieFile  = APP_DIR . 'Storage/curl_cookie.txt';
      }

      if ($this->userAgent === '') {
         $this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.142.86 Safari/537.36';
      }
   }

   public function head(string $url, array $params = []): void {
      $this->request('HEAD', $url, $params);
   }

   public function get(string $url, array $params = []): void {
      if (!empty($params)) {
         $url .= (stripos($url, '?') !== false) ? '&' : '?';
         $url .= (is_string($params)) ? $params : http_build_query($params, '', '&');
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

   public function responseHeader(?string $key = null): mixed {
      if (is_null($key)) {
         return $this->responseHeader;
      } else {
         if (array_key_exists($key, $this->responseHeader)) {
            return $this->responseHeader[$key];
         } else {
            return null;
         }
      }
   }

   public function responseBody(): string {
      return $this->responseBody;
   }

   public function setUserAgent(string $agent): string {
      return $this->userAgent = $agent;
   }

   public function setReferrer(string $referrer): string {
      return $this->referrer = $referrer;
   }

   public function setHeader(mixed $header, ?string $value = null): array {
      if (is_array($header)) {
         $this->headers = $header;
      } else {
         $this->headers[$header] = $value;
      }

      return $this->headers;
   }

   public function setOptions(mixed $options, ?string $value = null): array {
      if (is_array($options)) {
         $this->options = $options;
      } else {
         $this->options[$options] = $value;
      }

      return $this->options;
   }

   public function getError(): string {
      return $this->error;
   }

   private function request(string $method, string $url, array $params = []): void {
      $this->error = '';
      $this->ch = curl_init();
      $params = http_build_query($params, '', '&');

      $this->setRequestMethod($method);
      $this->setRequestOptions($url, $params);
      $this->setRequestHeaders();

      $response = curl_exec($this->ch);

      if ($response) {
         $response = $this->getResponse($response);
      } else {
         $this->error = curl_errno($this->ch) . ' - ' . curl_error($this->ch);
      }

      curl_close($this->ch);
   }

   private function setRequestHeaders(): void {
      $headers = [];
      foreach ($this->headers as $key => $value) {
         $headers[] = $key . ': ' . $value;
      }
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
   }

   private function setRequestMethod(string $method): void {
      switch (strtoupper($method)) {
         case 'HEAD':
            curl_setopt($this->ch, CURLOPT_NOBODY, true);
            break;
         case 'GET':
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
            break;
         case 'POST':
            curl_setopt($this->ch, CURLOPT_POST, true);
            break;
         default:
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
      }
   }

   private function setRequestOptions(string $url, mixed $params): void {
      curl_setopt($this->ch, CURLOPT_URL, $url);
      if (!empty($params)) {
         curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
      }

      curl_setopt($this->ch, CURLOPT_HEADER, true);
      curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
      if ($this->useCookie !== false) {
         curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFile);
         curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieFile);
      }
      if ($this->followRedirects) {
         curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
      }
      if ($this->referrer !== null) {
         curl_setopt($this->ch, CURLOPT_REFERER, $this->referrer);
      }

      foreach ($this->options as $option => $value) {
         curl_setopt($this->ch, constant('CURLOPT_' . str_replace('CURLOPT_', '', strtoupper($option))), $value);
      }
   }

   private function getResponse(string $response): void {
      $pattern = '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims';

      preg_match_all($pattern, $response, $matches);
      $headers_string = array_pop($matches[0]);
      $headers = explode("\r\n", str_replace("\r\n\r\n", '', $headers_string));

      $this->responseBody = str_replace($headers_string, '', $response);

      $version_and_status = array_shift($headers);
      preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches);
      $this->responseHeader['Http-Version'] = $matches[1];
      $this->responseHeader['Status-Code'] = $matches[2];
      $this->responseHeader['Status'] = $matches[2] . ' ' . $matches[3];

      foreach ($headers as $header) {
         preg_match('#(.*?)\:\s(.*)#', $header, $matches);
         $this->responseHeader[$matches[1]] = $matches[2];
      }
   }
}
