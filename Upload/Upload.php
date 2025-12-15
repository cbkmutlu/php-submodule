<?php

declare(strict_types=1);

namespace System\Upload;

use System\Language\Language;
use System\Exception\SystemException;

class Upload {
   private $file;
   private $path;
   private $allowed_types;
   private $allowed_mimes;
   private $max_width = 0;
   private $min_width = 0;
   private $max_height = 0;
   private $min_height = 0;
   private $max_size = 0;
   private $min_size = 0;
   private $error = [];

   public function __construct(
      private Language $language
   ) {
      $config = import_config('defines.upload');
      $this->path = $config['path'];
      $this->allowed_types = $config['allowed_types'];
      $this->allowed_mimes = $config['allowed_mimes'];
   }

   public function handle(array $file, ?string $filename = null): bool {
      if (empty($file['tmp_name'])) {
         $this->error['err_no_file'] = $this->language->system('upload.err_no_file');
         return false;
      }

      $pathname = ROOT_DIR . $this->path;
      $filename = $this->checkFileName($pathname, $filename ?? $file['name']);

      $this->file = $file;
      $this->checkTypes();
      $this->checkMimes();
      $this->checkDimension();
      $this->checkSize();
      $this->checkPath();

      if (!is_uploaded_file($file['tmp_name'])) {
         throw new SystemException('File upload error');
      }

      if (!move_uploaded_file($file['tmp_name'], $pathname . '/' . $filename)) {
         throw new SystemException('File upload error');
      }

      return empty($this->error);
   }

   public function error(): array {
      return $this->error;
   }

   public function setPath(string $path): self {
      $this->path = $path;
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setAllowedTypes(array $types): self {
      $this->allowed_types = $types;
      return $this;
   }

   public function getAllowedTypes(): array {
      return $this->allowed_types;
   }

   public function setAllowedMimes(array $mimes): self {
      $this->allowed_mimes = $mimes;
      return $this;
   }

   public function getAllowedMimes(): array {
      return $this->allowed_mimes;
   }

   public function setMaxWidth(int $width): self {
      $this->max_width = $width;
      return $this;
   }

   public function getMaxWidth(): int {
      return $this->max_width;
   }

   public function setMinWidth(int $width): self {
      $this->min_width = $width;
      return $this;
   }

   public function getMinWidth(): int {
      return $this->min_width;
   }

   public function setMaxHeight(int $height): self {
      $this->max_height = $height;
      return $this;
   }

   public function getMaxHeight(): int {
      return $this->max_height;
   }

   public function setMinHeight(int $height): self {
      $this->min_height = $height;
      return $this;
   }

   public function getMinHeight(): int {
      return $this->min_height;
   }

   public function setMaxSize(int $size): self {
      $this->max_size = $size;
      return $this;
   }

   public function getMaxSize(): int {
      return $this->max_size;
   }

   public function setMinSize(int $size): self {
      $this->min_size = $size;
      return $this;
   }

   public function getMinSize(): int {
      return $this->min_size;
   }

   private function checkFileName(string $directory, string $filename): string {
      $pathinfo = pathinfo($filename);
      $basename = $pathinfo['filename'];
      $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
      $i = 1;
      $filename = $basename . $extension;

      while (file_exists($directory . '/' . $filename)) {
         $filename = $basename . '(' . $i . ')' . $extension;
         $i++;
      }

      return $filename;
   }

   private function checkDimension(): void {
      if ($this->max_height > 0 || $this->min_height > 0 || $this->max_width > 0 || $this->min_width > 0) {
         $mime = mime_content_type($this->file['tmp_name']);
         if (!str_starts_with($mime, 'image/')) {
            return;
         }
      }

      $size = getimagesize($this->file['tmp_name']);
      if ($size === false) {
         $this->error['err_file_size'] = $this->language->system('upload.err_file_size');
         return;
      }

      [$width, $height] = $size;

      if (($this->max_width > 0 && $width > $this->max_width) || ($this->max_height > 0 && $height > $this->max_height)) {
         $this->error['err_max_dimension'] = $this->language->system('upload.err_max_dimension', [$this->max_width, $this->max_height]);
      }

      if (($this->min_width > 0 && $width < $this->min_width) || ($this->min_height > 0 && $height < $this->min_height)) {
         $this->error['err_min_dimension'] = $this->language->system('upload.err_min_dimension', [$this->min_width, $this->min_height]);
      }
   }

   private function checkSize(): void {
      if ($this->max_size > 0 && $this->file['size'] > $this->max_size * 1024) {
         $this->error['err_max_size'] = $this->language->system('upload.err_max_size', [$this->max_size]);
      }

      if ($this->min_size > 0 && $this->file['size'] < $this->min_size * 1024) {
         $this->error['err_min_size'] = $this->language->system('upload.err_min_size', [$this->min_size]);
      }
   }

   private function checkTypes(): void {
      $type = pathinfo($this->file['name'], PATHINFO_EXTENSION);
      if (!in_array($type, $this->allowed_types)) {
         $this->error['err_file_type'] = $this->language->system('upload.err_file_type');
      }
   }

   private function checkMimes(): void {
      $file = mime_content_type($this->file['tmp_name']);
      $matched = false;

      foreach ($this->allowed_mimes as $mime) {
         $pattern = '#^' . str_replace('\*', '.*', preg_quote($mime, '#')) . '$#i';
         if (preg_match($pattern, $file)) {
            $matched = true;
            break;
         }
      }

      if (!$matched) {
         $this->error['err_file_type'] = $this->language->system('upload.err_file_type');
      }
   }

   private function checkPath(): void {
      $pathname = ROOT_DIR . $this->path;
      if (!check_path($pathname)) {
         throw new SystemException('File upload directory [' . $pathname . '] is invalid');
      }

      if (!check_permission($pathname)) {
         throw new SystemException('File upload directory [' . $pathname . '] is not writable');
      }
   }
}
