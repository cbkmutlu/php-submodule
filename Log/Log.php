<?php

declare(strict_types=1);

namespace System\Log;

use System\Exception\ExceptionHandler;

class Log {
   private $path;
   private $prefix;
   private $format;
   private $extension;

   public function __construct() {
      $config = config('defines.log');
      $this->path = $config['path'];
      $this->prefix = $config['prefix'];
      $this->format = $config['format'];
      $this->extension = $config['extension'];
   }

   public function emergency(string $message): void {
      $this->write('emergency', $message);
   }

   public function alert(string $message): void {
      $this->write('alert', $message);
   }

   public function critical(string $message): void {
      $this->write('critical', $message);
   }

   public function error(string $message): void {
      $this->write('error', $message);
   }

   public function warning(string $message): void {
      $this->write('warning', $message);
   }

   public function notice(string $message): void {
      $this->write('notice', $message);
   }

   public function info(string $message): void {
      $this->write('info', $message);
   }

   public function debug(string $message): void {
      $this->write('debug', $message);
   }

   public function setPath(string $path): self {
      $this->path = $path;
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

   public function setFormat(string $format): self {
      $this->format = $format;
      return $this;
   }

   public function getFormat(): string {
      return $this->format;
   }

   public function setExtension(string $extension): self {
      $this->extension = $extension;
      return $this;
   }

   public function getExtension(): string {
      return $this->extension;
   }

   private function write(string $level, string $message): void {
      if (is_array($message)) {
         $message = serialize($message);
      }

      $message = '[' . date('Y-m-d H:i:s') . '] - [' . $level . '] ---> ' . $message;
      $this->save($message);
   }

   private function save(string $message): void {

      $file = $this->prefix . date($this->format) . $this->extension;
      $file = fopen(APP_DIR . $this->path . $file, 'a');

      if (fwrite($file, $message . "\n") === false) {
         throw new ExceptionHandler('Error', 'Log file could not be created. Check write permissions.');
      }

      fclose($file);
   }
}
