<?php

declare(strict_types=1);

namespace System\Log;

use System\Log\LogException;
use System\Language\Language;

class Log {
   private $path;
   private $prefix;
   private $file_format;
   private $content_format;
   private $extension;

   public function __construct(
      private Language $language
   ) {
      $config = import_config('defines.log');
      $this->path = APP_DIR . $config['path'];
      $this->prefix = $config['prefix'];
      $this->file_format = $config['file_format'];
      $this->content_format = $config['content_format'];
      $this->extension = $config['extension'];
   }

   public function emergency(string $message): bool {
      return $this->writeFile('emergency', $message);
   }

   public function alert(string $message): bool {
      return $this->writeFile('alert', $message);
   }

   public function critical(string $message): bool {
      return $this->writeFile('critical', $message);
   }

   public function error(string $message): bool {
      return $this->writeFile('error', $message);
   }

   public function warning(string $message): bool {
      return $this->writeFile('warning', $message);
   }

   public function notice(string $message): bool {
      return $this->writeFile('notice', $message);
   }

   public function info(string $message): bool {
      return $this->writeFile('info', $message);
   }

   public function debug(string $message): bool {
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

   private function writeFile(string $level, string $message): bool {
      if (is_array($message)) {
         $message = serialize($message);
      }

      $message = '[' . date($this->content_format) . '] - [' . $level . '] ' . $message;

      $this->checkPath();
      $path = $this->path . '/' . $this->prefix . date($this->file_format) . $this->extension;
      if (!file_put_contents($path, $message . "\n", FILE_APPEND | LOCK_EX)) {
         throw new LogException("Log file write error [{$path}]");
      }

      return true;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new LogException("Log file upload directory is invalid [{$this->path}]");
      }

      if (!check_permission($this->path)) {
         throw new LogException("Log file upload directory is not writable [{$this->path}]");
      }
   }
}
