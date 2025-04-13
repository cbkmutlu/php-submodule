<?php

declare(strict_types=1);

namespace System\Upload;

use System\Language\Language;

class Upload {
   private $file = [];
   private $path = ROOT_DIR . '/Public/upload';
   private $filename = null;
   private $allowed_types = [];
   private $allowed_mimes = [];
   private $max_width = 0;
   private $min_width = 0;
   private $max_height = 0;
   private $min_height = 0;
   private $max_size = 0;
   private $min_size = 0;
   private $error = '';
   private $language;

   public function __construct(Language $language) {
      $this->language = $language;
   }

   public function handle(array $file = []): bool {
      if (empty($file)) {
         $this->error = $this->language->get('no_file_error', 'no_file_error');
         return false;
      }

      $this->file = $file;

      if ($this->checkTypes() && $this->checkMimes() && $this->checkDimension() && $this->checkSize() && $this->checkPath()) {
         if (is_uploaded_file($this->file['tmp_name'])) {
            if (is_null($this->filename)) {
               $this->filename = $this->file['name'];
            }

            if (move_uploaded_file($this->file['tmp_name'], $this->path . '/' . $this->filename)) {
               return true;
            }

            $this->error = $this->language->get('upload', 'upload_error');
         }
      }

      return false;
   }

   public function setPath(string $path): self {
      $this->path = ROOT_DIR . $path;
      return $this;
   }

   public function setFilename(string $name): self {
      $this->filename = $name;
      return $this;
   }

   public function setAllowedTypes(array $types = []): self {
      $this->allowed_types = $types;
      return $this;
   }

   public function setAllowedMimes(array $mimes = []): self {
      $this->allowed_mimes = $mimes;
      return $this;
   }

   public function setMaxWidth(int $width): self {
      $this->max_width = $width;
      return $this;
   }

   public function setMinWidth(int $width): self {
      $this->min_width = $width;
      return $this;
   }

   public function setMaxHeight(int $height): self {
      $this->max_height = $height;
      return $this;
   }

   public function setMinHeight(int $height): self {
      $this->min_height = $height;
      return $this;
   }

   public function setMaxSize(int $size): self {
      $this->max_size = $size;
      return $this;
   }

   public function setMinSize(int $size): self {
      $this->min_size = $size;
      return $this;
   }

   public function getError(): string {
      return $this->error;
   }

   private function checkDimension(): bool {
      if ($this->max_height > 0 || $this->min_height > 0 || $this->max_width > 0 || $this->min_width > 0) {
         $mime = mime_content_type($this->file['tmp_name']);
         if (strpos($mime, 'image/') === false) {
            return true;
         }
         list($width, $height) = getimagesize($this->file['tmp_name']);
      }

      if ($this->max_width > 0 || $this->max_height > 0) {
         if ($width > $this->max_width || $height > $this->max_height) {
            $this->error = $this->language->get('upload', 'max_dimension_error', [$this->max_width, $this->max_height]);
            return false;
         }
      }

      if ($this->min_width > 0 || $this->min_height > 0) {
         if ($width < $this->min_width || $height < $this->min_height) {
            $this->error = $this->language->get('upload', 'min_dimension_error', [$this->max_width, $this->max_height]);
            return false;
         }
      }

      return true;
   }

   private function checkSize(): bool {
      if ($this->max_size > 0) {
         if ($this->file['size'] > $this->max_size * 1024) {
            $this->error = $this->language->get('upload', 'max_size_error', $this->max_size);
            return false;
         }
      }

      if ($this->min_size > 0) {
         if ($this->file['size'] < $this->min_size * 1024) {
            $this->error = $this->language->get('upload', 'min_size_error', $this->min_size);
            return false;
         }
      }

      return true;
   }

   private function checkTypes(): bool {
      if (count($this->allowed_types) > 0) {
         $type = pathinfo($this->file['name'], PATHINFO_EXTENSION);
         if (!in_array($type, $this->allowed_types)) {
            $this->error = $this->language->get('upload', 'file_type_error');
            return false;
         }
      }

      return true;
   }

   private function checkMimes(): bool {
      if (count($this->allowed_mimes) === 0) {
         return true;
      }

      $file = mime_content_type($this->file['tmp_name']);
      foreach ($this->allowed_mimes as $mime) {
         if (preg_match('/^[a-z]+\/\*$/', $mime)) {
            $mime = rtrim($mime, '*');
            if (strpos($file, $mime) !== false) {
               return true;
            }
         } else {
            if ($file === $mime) {
               return true;
            }
         }
      }

      $this->error = $this->language->get('upload', 'file_type_error');
      return false;
   }

   private function checkPath(): bool {
      if (!file_exists($this->path)) {
         $this->error = $this->language->get('upload', 'wrong_upload_path_error', $this->path);
         return false;
      }

      if (!is_writable($this->path)) {
         $this->error = $this->language->get('upload', 'permission_error');
         return false;
      }

      return true;
   }
}
