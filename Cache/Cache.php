<?php

declare(strict_types=1);

namespace System\Cache;

use System\Exception\SystemException;

class Cache {
   private $filename;
   private $path;
   private $extension;
   private $expire;

   public function __construct() {
      $config = import_config('defines.cache');
      $this->path = APP_DIR . $config['path'];
      $this->extension = $config['extension'];
      $this->expire = $config['expire'];
      $this->filename = $config['filename'];
   }

   public function save(string $name, mixed $data, ?int $expiration = null): void {
      if (is_null($expiration)) {
         $expiration = $this->expire;
      }

      $values = [
         'time' => time(),
         'expire' => $expiration,
         'data' => serialize($data)
      ];

      $content = $this->checkContent();

      if (is_array($content)) {
         $content[$name] = $values;
      } else {
         $content = [$name => $values];
      }

      $content = json_encode($content);
      $this->writeFile($content);
   }

   public function read(string $name, ?string $filename = null): mixed {
      $content = $this->checkContent($filename);

      if (!isset($content[$name]['data'])) {
         return null;
      }

      return unserialize($content[$name]['data']);
   }

   public function clear(string $name): void {
      $content = $this->checkContent();

      if (is_array($content)) {
         if (isset($content[$name])) {
            unset($content[$name]);
            $content = json_encode($content);
            $this->writeFile($content);
         } else {
            throw new SystemException("Cache key not found [{$name}]");
         }
      }
   }

   public function clearAll(): void {
      $content = $this->checkContent();

      if (is_array($content)) {
         $content = json_encode([]);
         $this->writeFile($content);
      }
   }

   public function clearExpired(): int {
      $content = $this->checkContent();
      $counter = 0;

      if (is_array($content)) {
         foreach ($content as $key => $value) {
            if ($this->checkExpire($value['time'], $value['expire'])) {
               unset($content[$key]);
               $counter++;
            }
         }

         if ($counter > 0) {
            $content = json_encode($content);
            $this->writeFile($content);
         }
      }
      return $counter;
   }

   public function exist(string $name): bool {
      $this->clearExpired();
      $content = $this->checkContent();

      if ($content) {
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

   public function writeFile(string $content): bool {
      $path = $this->checkFile();
      if (!file_put_contents($path, $content)) {
         throw new SystemException("Cache file write error [{$path}]");
      }

      return true;
   }

   private function checkContent(?string $filename = null): mixed {
      $file = $this->checkFile($filename);

      if (file_exists($file)) {
         $content = file_get_contents($file);
         return json_decode($content, true);
      }

      return false;
   }

   private function checkFile(?string $filename = null): string|bool {
      if (is_null($filename)) {
         $filename = preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower($this->filename));
      }

      $this->checkPath();
      return $this->path . '/' . hash('sha256', $filename) . $this->extension;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new SystemException("Cache file upload directory is invalid [{$this->path}]");
      }

      if (!check_permission($this->path)) {
         throw new SystemException("Cache file upload directory is not writable [{$this->path}]");
      }
   }

   private function checkExpire(int $time, int $expiration): bool {
      if ($expiration === 0) {
         return false;
      }

      return time() - $time > $expiration;
   }
}
