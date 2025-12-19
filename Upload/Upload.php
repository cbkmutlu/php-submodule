<?php

declare(strict_types=1);

namespace System\Upload;

use System\Language\Language;
use System\Exception\SystemException;

interface UploadAdapter {
   public function upload(array $file, string $path, string $name): bool;
   public function unlink(string|array $files, string $path): bool;
}

class Upload {
   private array $allowed_types;
   private array $allowed_mimes;
   private int $max_width = 0;
   private int $min_width = 0;
   private int $max_height = 0;
   private int $min_height = 0;
   private int $max_size = 0;
   private int $min_size = 0;
   private array $error = [];
   private string $path;
   private string $dir = '';
   private UploadAdapter $adapter;

   public function __construct(
      private Language $language
   ) {
      $config              = import_config('defines.upload');
      $this->adapter       = new $config['adapter']();
      $this->allowed_types = $config['allowed_types'];
      $this->allowed_mimes = $config['allowed_mimes'];
      $this->path          = ROOT_DIR . $config['path'];
      $this->checkPath();
   }

   public function setPath(string $path): self {
      $this->path = ROOT_DIR . $path;
      $this->checkPath();
      return $this;
   }

   public function getPath(): string {
      return $this->path;
   }

   public function setDir(string $dir): self {
      $this->dir = $dir;
      $this->path .= '/' . $dir;
      $this->checkPath();
      return $this;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new SystemException('Upload directory [' . $this->path . '] cannot be created');
      }

      if (!check_permission($this->path)) {
         throw new SystemException('Upload directory [' . $this->path . '] is not writable');
      }
   }

   public function handle(array $files): array {
      $this->error = [];

      $result = [];
      if (!is_array($files['name'])) {
         $files = [
            'name'      => [$files['name']],
            'tmp_name'  => [$files['tmp_name']],
            'type'      => [$files['type']],
            'error'     => [$files['error']],
            'size'      => [$files['size']],
         ];
      }

      foreach ($files['name'] as $i => $name) {
         $file = [
            'name'     => $files['name'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'type'     => $files['type'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
         ];

         if (empty($file['tmp_name'])) {
            $this->error['err_no_file'] = $this->language->system('upload.err_no_file');
            continue;
         }

         $this->checkTypes($file);
         $this->checkMimes($file);
         $this->checkDimension($file);
         $this->checkSize($file);

         if (!empty($this->error)) {
            continue;
         }

         $name = bin2hex(random_bytes(16)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
         $this->adapter->upload($file, $this->path, $name);
         $result[] = $this->dir . '/' . $name;
      }

      if (empty($result) && !empty($this->error)) {
         throw new SystemException(json_encode($this->error, JSON_UNESCAPED_UNICODE), 400);
      }

      return $result;
   }

   public function unlink(string|array $files): bool {
      return $this->adapter->unlink($files, $this->path);
   }

   public function error(): array {
      return $this->error;
   }

   public function setAdapter(UploadAdapter $adapter): self {
      $this->adapter = $adapter;
      return $this;
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

   private function checkDimension(array $file): void {
      if ($this->max_height || $this->min_height || $this->max_width || $this->min_width) {
         $mime = mime_content_type($file['tmp_name']);
         if (!str_starts_with($mime, 'image/')) {
            return;
         }
      }

      $size = getimagesize($file['tmp_name']);
      if ($size === false) {
         $this->error['err_file_size'] = $this->language->system('upload.err_file_size');
         return;
      }

      [$width, $height] = $size;

      if (($this->max_width && $width > $this->max_width) || ($this->max_height && $height > $this->max_height)) {
         $this->error['err_max_dimension'] = $this->language->system('upload.err_max_dimension', [$this->max_width, $this->max_height]);
      }

      if (($this->min_width && $width < $this->min_width) || ($this->min_height && $height < $this->min_height)) {
         $this->error['err_min_dimension'] = $this->language->system('upload.err_min_dimension', [$this->min_width, $this->min_height]);
      }
   }

   private function checkSize(array $file): void {
      if ($this->max_size && $file['size'] > $this->max_size * 1024) {
         $this->error['err_max_size'] = $this->language->system('upload.err_max_size', [$this->max_size]);
      }

      if ($this->min_size && $file['size'] < $this->min_size * 1024) {
         $this->error['err_min_size'] = $this->language->system('upload.err_min_size', [$this->min_size]);
      }
   }

   private function checkTypes(array $file): void {
      $type = pathinfo($file['name'], PATHINFO_EXTENSION);
      if (!in_array($type, $this->allowed_types)) {
         $this->error['err_file_type'] = $this->language->system('upload.err_file_type');
      }
   }

   private function checkMimes(array $file): void {
      $mime = mime_content_type($file['tmp_name']);
      $matched = false;

      foreach ($this->allowed_mimes as $pattern) {
         $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
         if (preg_match($regex, $mime)) {
            $matched = true;
            break;
         }
      }

      if (!$matched) {
         $this->error['err_file_type'] = $this->language->system('upload.err_file_type');
      }
   }
}
