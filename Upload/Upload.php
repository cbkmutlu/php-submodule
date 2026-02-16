<?php

declare(strict_types=1);

namespace System\Upload;

use System\Container\Container;
use System\Language\Language;
use System\Upload\UploadException;

interface UploadInterface {
   /**
    * Dosyayı depolama alanına yükler
    *
    * @param array $file Yüklenen dosya dizisi ($_FILES formatında)
    * @param string $name Yeni dosya adı (uzantı ile birlikte)
    * @param string $path Ana yükleme yolu (örn: 'public/uploads')
    * @param string|null $dir Opsiyonel alt dizin (örn: 'products/2024')
    * @return string Dosya yolu (örn: '2024/image.jpg')
    * @throws UploadException Yükleme başarısız olursa
    */
   public function upload(array $file, string $name, string $path, ?string $dir = null): string;

   /**
    * Dosyayı depolama alanından siler
    *
    * @param string $file Dosya yolu (örn: '2024/image.jpg')
    * @param string $path Ana yükleme yolu (örn: 'public/uploads')
    * @return bool Başarıyla silinirse true döner
    * @throws UploadException Silme başarısız olursa
    */
   public function unlink(string $file, string $path): bool;
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
   private ?string $dir = null;
   private UploadInterface $handler;

   public function __construct(
      private Language $language,
      private Container $container
   ) {
      $config  = import_config('defines.upload');
      $default = $config['default'];
      $handler = $config['providers'][$default];

      // use resolveClass method instead of new $handler['handler']() for dependency injection
      $this->handler       = $this->container->resolveClass($handler['handler']);
      $this->path          = $handler['path'] ?? $config['path'];
      $this->allowed_types = $handler['allowed_types'] ?? $config['allowed_types'];
      $this->allowed_mimes = $handler['allowed_mimes'] ?? $config['allowed_mimes'];
   }

   public function handle(array $files, ?callable $setName = null): array {
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

         if ($setName) {
            $name = $setName($file, $i);
         } else {
            $name = bin2hex(random_bytes(16)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
         }

         $result[] = $this->handler->upload($file, $name, $this->path, $this->dir);
      }

      if (empty($result) && !empty($this->error)) {
         throw new UploadException(json_encode($this->error, JSON_UNESCAPED_UNICODE), 400);
      }

      return $result;
   }

   public function unlink(string|array $files): bool {
      $files = (array) $files;

      foreach ($files as $file) {
         if (empty($file)) {
            continue;
         }

         $this->handler->unlink($file, $this->path);
      }

      return true;
   }

   public function error(): array {
      return $this->error;
   }


   public function setHandler(UploadInterface $handler): self {
      $this->handler = $handler;
      return $this;
   }

   public function setDir(?string $dir = null): self {
      $this->dir = $dir;
      return $this;
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
