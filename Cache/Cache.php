<?php

declare(strict_types=1);

namespace System\Cache;

use System\Exception\SystemException;

class Cache {
   private $namespace;
   private $path;
   private $extension;
   private $expire;

   public function __construct() {
      $config          = import_config('defines.cache');
      $this->path      = APP_DIR . $config['path'];
      $this->namespace = $config['namespace'];
      $this->extension = $config['extension'];
      $this->expire    = $config['expire'];

      $this->checkPath();
   }

   public function get(string $key, mixed $default = null): mixed {
      $file = $this->filePath($key);

      $raw = @file_get_contents($file);
      if ($raw === false) {
         return $default;
      }

      if ($this->isExpired($file)) {
         $this->delete($key);
         return $default;
      }

      $payload = json_decode($raw, true);
      if (!isset($payload['data'])) {
         return $default;
      }

      return $payload['data'];
   }

   public function set(string $key, mixed $value, ?int $expire = null): void {
      $expire = $expire ?? $this->expire;
      $file = $this->filePath($key);
      $temp = $file . '.' . uniqid('', true);
      $value = ['data' => $value, 'expire' => time() + $expire];
      $data = json_encode($value, JSON_THROW_ON_ERROR);

      if (file_put_contents($temp, $data, LOCK_EX) === false) {
         throw new SystemException('Cache [' . $file . '] write failed');
      }

      if ($expire > 0) {
         touch($temp, time() + $expire);
      }

      rename($temp, $file);
   }

   public function exists(string $key): bool {
      return is_file($this->filePath($key));
   }

   public function delete(string $key): void {
      $file = $this->filePath($key);
      if (is_file($file)) {
         unlink($file);
      }
   }

   public function clearAll(): void {
      foreach (glob($this->path . '/' . $this->namespace . '_*') as $file) {
         unlink($file);
      }
   }

   public function clearExpired(): int {
      $deleted = 0;
      $now = time();

      foreach (glob($this->path . '/' . $this->namespace . '_*') as $file) {
         if (!is_file($file)) {
            continue;
         }

         $expireAt = filemtime($file);
         if ($expireAt !== false && $expireAt < $now) {
            unlink($file);
            $deleted++;
         }
      }

      return $deleted;
   }

   private function isExpired(string $file): bool {
      $expireAt = filemtime($file);

      if ($expireAt === false) {
         return true;
      }

      return $expireAt < time();
   }

   private function filePath(string $key): string {
      $safeKey = hash('sha256', $this->namespace . ':' . $key);
      return $this->path . '/' . $this->namespace . '_' . $safeKey . $this->extension;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new SystemException('Cache directory [' . $this->path . '] cannot be created');
      }

      if (!check_permission($this->path)) {
         throw new SystemException('Cache directory [' . $this->path . '] not writable');
      }
   }
}
