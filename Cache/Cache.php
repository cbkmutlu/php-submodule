<?php

declare(strict_types=1);

namespace System\Cache;

use System\Exception\ExceptionHandler;

class Cache {
   private $filename;
   private $path;
   private $extension;
   private $expire;

   public function __construct() {
      $config = config('defines.cache');
      $this->path = APP_DIR . $config['path'];
      $this->extension = $config['extension'];
      $this->expire = $config['expire'];
      $this->filename = $config['filename'];
   }

   public function save(string $name, mixed $data, ?int $expiration = null): void {
      if (is_null($expiration)) {
         $expiration = $this->expire;
      }

      $storedData = [
         'time' => time(),
         'expire' => $expiration,
         'data' => serialize($data)
      ];

      $content = $this->checkCache();

      if (is_array($content)) {
         $content[$name] = $storedData;
      } else {
         $content = [$name => $storedData];
      }

      $content = json_encode($content);
      file_put_contents($this->checkDir(), $content);
   }

   public function read(string $name, string $filename): mixed {
      $content = $this->checkCache($filename);
      if (!isset($content[$name]['data'])) {
         return null;
      }

      return json_decode($content[$name]['data'], true);
   }

   public function delete(string $name): void {
      $content = $this->checkCache();
      if (is_array($content)) {
         if (isset($content[$name])) {
            unset($content[$name]);
            $content = json_encode($content);
            file_put_contents($this->checkDir(), $content);
         } else {
            throw new ExceptionHandler("Hata", "delete() - Key {" . $name . "} bulunamadı");
         }
      }
   }

   public function deleteAll(): void {
      if (file_exists($this->checkDir())) {
         $file = fopen($this->checkDir(), 'w');
         fclose($file);
      }
   }

   public function deleteExpired(): int {
      $counter = 0;
      $cacheContent = $this->checkCache();
      if (is_array($cacheContent)) {
         foreach ($cacheContent as $key => $value) {
            if ($this->checkExpire($value['time'], $value['expire']) === true) {
               unset($cacheContent[$key]);
               $counter++;
            }
         }

         if ($counter > 0) {
            $cacheContent = json_encode($cacheContent);
            file_put_contents($this->checkDir(), $cacheContent);
         }
      }
      return $counter;
   }

   public function exist(string $name): bool {
      $this->deleteExpired();
      if ($this->checkCache() !== false) {
         $content = $this->checkCache();
         return isset($content[$name]['data']);
      }

      return false;
   }

   public function setPath(string $path): self {
      $this->path = APP_DIR . $path;
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setFilename(string $name): self {
      $this->filename = $name;
      return $this;
   }

   public function getFilename(): string {
      return $this->filename;
   }

   public function setExtension(string $extension): self {
      $this->extension = $extension;
      return $this;
   }

   public function getExtension(): string {
      return $this->extension;
   }

   private function checkCache(?string $filename = null): mixed {
      if ($this->checkDir() === false) {
         return false;
      }

      if (!file_exists($this->checkDir($filename))) {
         return false;
      }

      $file = file_get_contents($this->checkDir($filename));
      return json_decode($file, true);
   }

   private function checkDir(string $filename = null): string {
      if (!is_dir($this->getPath()) && !mkdir($this->getPath(), 0775, true)) {
         throw new ExceptionHandler("Hata", "Cache dizini oluşturulamadı" . $this->getPath());
      } elseif (!is_readable($this->getPath()) || !is_writable($this->getPath())) {
         if (!chmod($this->getPath(), 0775)) {
            throw new ExceptionHandler("Hata", $this->getPath() . " dizini okuma ve yazma izinlerine sahip olmalıdır");
         }
      }

      if (is_null($filename)) {
         $filename = preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower($this->getFilename()));
      }

      return $this->getPath() . '/' . hash('sha256', $filename) . $this->getExtension();
   }

   private function checkExpire(int $time, int $expiration): mixed {
      if ($expiration === 0) {
         return false;
      }

      return time() - $time > $expiration;
   }
}
