<?php

declare(strict_types=1);

namespace System\Log;

use System\Log\LogException;

class Log {
   private string $path;
   private string $prefix;
   private string $file_format;
   private string $content_format;
   private string $extension;

   public function __construct() {
      $config               = import_config('defines.log');
      $this->path           = APP_DIR . $config['path'];
      $this->prefix         = $config['prefix'];
      $this->file_format    = $config['file_format'];
      $this->content_format = $config['content_format'];
      $this->extension      = $config['extension'];

      $this->checkPath();
   }

   public function emergency(string|array $message): bool {
      return $this->writeFile('emergency', $message);
   }

   public function alert(string|array $message): bool {
      return $this->writeFile('alert', $message);
   }

   public function critical(string|array $message): bool {
      return $this->writeFile('critical', $message);
   }

   public function error(string|array $message): bool {
      return $this->writeFile('error', $message);
   }

   public function warning(string|array $message): bool {
      return $this->writeFile('warning', $message);
   }

   public function notice(string|array $message): bool {
      return $this->writeFile('notice', $message);
   }

   public function info(string|array $message): bool {
      return $this->writeFile('info', $message);
   }

   public function debug(string|array $message): bool {
      return $this->writeFile('debug', $message);
   }

   public function setPath(string $path): self {
      $this->path = APP_DIR . $path;
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setPrefix(string $prefix): self {
      $this->prefix = $prefix;
      return $this;
   }
   public function getPrefix(): string {
      return $this->prefix;
   }

   public function setFileFormat(string $format): self {
      $this->file_format = $format;
      return $this;
   }

   public function getFileFormat(): string {
      return $this->file_format;
   }

   public function setContentFormat(string $format): self {
      $this->content_format = $format;
      return $this;
   }

   public function getContentFormat(): string {
      return $this->content_format;
   }

   public function setExtension(string $extension): self {
      $this->extension = $extension;
      return $this;
   }

   public function getExtension(): string {
      return $this->extension;
   }

   /**
    * Log mesajını dosyaya yazar.
    *
    * @param string $level Log seviyesi (emergency, alert, critical, error, warning, notice, info, debug)
    * @param string|array $message Log mesajı. Eğer array ise JSON formatına çevrilir.
    * @return bool
    * @throws LogException
    */
   private function writeFile(string $level, string|array $message): bool {
      if (is_array($message)) {
         $message = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
      } else {
         $message = (string) $message;
      }

      $message = '[' . date($this->content_format) . '] - [' . $level . '] ' . $message;

      $name = $this->prefix . date($this->file_format) . $this->extension;
      $path = rtrim($this->path, '/') . '/' . $name;

      $result = file_put_contents($path, $message . "\n", FILE_APPEND | LOCK_EX);
      if ($result === false) {
         throw new LogException('Log file [' . $path . '] write error');
      }

      return true;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new LogException('Log directory [' . $this->path . '] cannot be created');
      }

      if (!check_permission($this->path)) {
         throw new LogException('Log directory [' . $this->path . '] not writable');
      }
   }
}
