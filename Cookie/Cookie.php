<?php

declare(strict_types=1);

namespace System\Cookie;

use System\Exception\ExceptionHandler;

class Cookie {
   private $encryption_key;
   private $cookie_security;
   private $http_only;
   private $secure;
   private $separator;
   private $path;
   private $domain;
   private $samesite;

   public function __construct() {
      $config = config('defines.cookie');
      $this->encryption_key = $config['encryption_key'];
      $this->cookie_security = $config['cookie_security'];
      $this->http_only = $config['http_only'];
      $this->secure = $config['secure'];
      $this->separator = $config['separator'];
      $this->path = $config['path'];
      $this->domain = $config['domain'];
      $this->samesite = $config['samesite'];
   }

   public function save(string $name, string $content, int $time = 0): void {
      if ($time > 0) {
         $time = time() + ($time * 60 * 60);
      }

      if ($this->cookie_security === true) {
         setcookie(
            $name,
            $content . $this->separator . hash_hmac('sha256', $content, $this->encryption_key),
            [
               "expires" => $time,
               "path" => $this->path,
               "domain" => $this->domain,
               "secure" => $this->secure,
               "http_only" => $this->http_only,
               "samesite" => $this->samesite
            ]
         );
      } else {
         setcookie($name, $content, [
            "expires" => $time,
            "path" => $this->path,
            "domain" => $this->domain,
            "secure" => $this->secure,
            "http_only" => $this->http_only,
            "samesite" => $this->samesite
         ]);
      }
   }

   public function read(string $name): mixed {
      if ($this->exist($name)) {
         if ($this->cookie_security === true) {
            $parts = explode($this->separator, $_COOKIE[$name]);

            if (hash_equals(hash_hmac('sha256', $parts[0], $this->encryption_key), $parts[1])) {
               return $parts[0];
            } else {
               throw new ExceptionHandler("Hata", "Cookie içeriği değiştirilmiş");
            }
         } else {
            return $_COOKIE[$name];
         }
      } else {
         return null;
      }
   }

   public function delete(string $name): void {
      if ($this->exist($name)) {
         unset($_COOKIE[$name]);
         setcookie($name, '', time() - 3600, $this->path, $this->domain);
      }
   }

   public function exist(string $name): bool {
      return isset($_COOKIE[$name]);
   }

   public function setPath(string $path): self {
      $this->path = $path;
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setHttpOnly(bool $http = false): self {
      $this->http_only = $http;
      return $this;
   }

   public function getHttpOnly(): bool {
      return $this->http_only;
   }

   public function setSecure(bool $secure = false): self {
      $this->secure = $secure;
      return $this;
   }

   public function getSecure(): bool {
      return $this->secure;
   }

   public function setDomain(string $domain): self {
      $this->domain = $domain;
      return $this;
   }

   public function getDomain(): string {
      return $this->domain;
   }
}
