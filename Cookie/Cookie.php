<?php

declare(strict_types=1);

namespace System\Cookie;

use System\Exception\SystemException;

class Cookie {
   private $encryption_key;
   private $cookie_security;
   private $httponly;
   private $secure;
   private $separator;
   private $path;
   private $domain;
   private $samesite;

   public function __construct() {
      $config = import_config('defines.cookie');
      $this->encryption_key = $config['encryption_key'];
      $this->cookie_security = $config['cookie_security'];
      $this->httponly = $config['httponly'];
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

      if ($this->cookie_security) {
         setcookie($name, $content . $this->separator . hash_hmac('sha256', $content, $this->encryption_key), [
            "expires" => $time,
            "path" => $this->path,
            "domain" => $this->domain,
            "secure" => $this->secure,
            "httponly" => $this->httponly,
            "samesite" => $this->samesite
         ]);
      } else {
         setcookie($name, $content, [
            "expires" => $time,
            "path" => $this->path,
            "domain" => $this->domain,
            "secure" => $this->secure,
            "httponly" => $this->httponly,
            "samesite" => $this->samesite
         ]);
      }
   }

   public function read(string $name): string {
      if ($this->cookie_security) {
         $parts = explode($this->separator, $_COOKIE[$name]);
         [$data, $hash] = $parts;

         if (!hash_equals(hash_hmac('sha256', $data, $this->encryption_key), $hash)) {
            throw new SystemException("Cookie integrity check failed [{$name}]");
         }

         return $data;
      } else {
         return $_COOKIE[$name];
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

   public function setHttpOnly(bool $http): self {
      $this->httponly = $http;
      return $this;
   }

   public function getHttpOnly(): bool {
      return $this->httponly;
   }

   public function setSecure(bool $secure): self {
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
