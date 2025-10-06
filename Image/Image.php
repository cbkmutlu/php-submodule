<?php

declare(strict_types=1);

namespace System\Image;

use System\Exception\SystemException;

class Image {
   private $image;
   private $width;
   private $height;
   private $mime;
   private $path;
   private $quality;
   private $background;

   private const SUPPORTED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-bmp', 'image/x-ms-bmp', 'image/x-windows-bmp'];

   public function __construct() {
      if (!extension_loaded('gd')) {
         throw new SystemException('GD extension is not active');
      }

      $config = import_config('defines.image');
      $this->path = ROOT_DIR . DS . $config['path'];
      $this->quality = $config['quality'];
      $this->background = $config['background'];

      ini_set('gd.jpeg_ignore_warning', '1');
   }

   public function data(string $path): self {
      $image = file_get_contents($path, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
      if ($image === false) {
         throw new SystemException("Could not load image [{$path}]");
      }

      $info = getimagesizefromstring($image);
      if ($info === false) {
         throw new SystemException('Invalid image data');
      }

      $this->mime = $info['mime'];
      if (!in_array($this->mime, self::SUPPORTED_MIMES)) {
         throw new SystemException('Unsupported image type');
      }

      $this->image = imagecreatefromstring($image);
      $this->width = $info[0];
      $this->height = $info[1];

      return $this;
   }

   public function resize(?int $width = null, ?int $height = null, ?int $x = null, ?int $y = null, bool $crop = false): self {
      if (!$this->image || (is_null($width) && is_null($height))) {
         return $this;
      }

      if ($width && is_null($height)) {
         $ratio = $width / $this->width;
         $height = (int) round($this->height * $ratio);
      } elseif ($height && is_null($width)) {
         $ratio = $height / $this->height;
         $width = (int) round($this->width * $ratio);
      }

      if ($crop) {
         return $this->cropImage($width, $height, $x, $y);
      }

      return $this->fillImage($width, $height, $x, $y);
   }

   public function rotate(float $degrees): self {
      $image = $this->image;
      if ($image === false) {
         return $this;
      }

      $background = imagecolorallocatealpha($image, ...$this->getColor($this->background));
      imagefill($image, 0, 0, $background);

      $this->image = imagerotate($image, $degrees, $background);
      $this->width = imagesx($this->image);
      $this->height = imagesy($this->image);

      return $this;
   }

   public function text(string $text, int $x, int $y, array $options = []): self {
      if (!$this->image) {
         return $this;
      }

      $defaults = [
         'size' => 24,
         'color' => [0, 0, 0],
         'font' => null,
         'angle' => 0,
         'x' => 0,
         'y' => 0
      ];

      $options = array_merge($defaults, $options);
      $color = imagecolorallocatealpha($this->image, ...$this->getColor($options['color']));
      $font = ($options['font'] && is_file($options['font']));

      if ($font) {
         $bbox = imagettfbbox($options['size'], $options['angle'], $options['font'], $text);
         $min_x = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
         $max_x = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
         $min_y = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
         $max_y = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
         $text_width = (int) ($max_x - $min_x);
         $text_height = (int) ($max_y - $min_y);
      } else {
         $size = (int) (min(5, max(1, ($options['size'] / 5))));
         $char_width = imagefontwidth($size);
         $char_height = imagefontheight($size);
         $text_width = $char_width * strlen($text);
         $text_height = $char_height;
         $min_x = 0;
         $max_x = $text_width;
         $min_y = 0;
         $max_y = $text_height;
      }

      if (is_string($options['x'])) {
         $x = strtolower($options['x']);
         if ($x === 'center') {
            $options['x'] = (int) (($this->width / 2) - ($text_width / 2) - $min_x);
         } elseif ($x === 'right') {
            $options['x'] = (int) ($this->width - $text_width - 10 - $min_x);
         } else {
            $options['x'] = 10 - $min_x;
         }
      }

      if (is_string($options['y'])) {
         $y = strtolower($options['y']);
         if ($y === 'middle') {
            $options['y'] = (int) (($this->height / 2) + ($text_height / 2) - $max_y);
         } elseif ($y === 'bottom') {
            $options['y'] = ($font) ? ($this->height - 10) : ($this->height - $text_height - 10);
         } else {
            $options['y'] = ($font) ? ($text_height + 10) : 10;
         }
      }

      if ($font) {
         imagettftext($this->image, $options['size'], $options['angle'], $options['x'], $options['y'], $color, $options['font'], $text);
      } else {
         imagestring($this->image, $size, $options['x'], $options['y'], $text, $color);
      }

      return $this;
   }

   public function path(string $path): self {
      $this->path = ROOT_DIR . DS . $path;
      return $this;
   }

   public function background(array|string $color): self {
      if (is_array($color) && count($color) > 2) {
         $this->background = $color;
      } elseif ($color === 'transparent') {
         $this->background = [0, 0, 0, 0];
      }

      return $this;
   }

   public function quality(int $quality): self {
      $this->quality = max(0, min(100, $quality));
      return $this;
   }

   public function save(string $file, ?string $mime = null): bool {
      if (!$this->image) {
         return false;
      }

      if (is_null($mime) || !in_array($mime, self::SUPPORTED_MIMES)) {
         $mime = $this->mime;
      }

      $this->checkPath();
      $path = $this->path . '/' . $file;

      ob_start();
      $this->createImage($this->image, $mime);
      $data = ob_get_contents();
      ob_end_clean();

      if (!file_put_contents($path, $data)) {
         throw new SystemException("Image file save error [{$path}]");
      }

      return true;
   }

   public function show(?string $mime = null): void {
      if (!$this->image || headers_sent()) {
         return;
      }

      if (is_null($mime) || !in_array($mime, self::SUPPORTED_MIMES)) {
         $mime = $this->mime;
      }

      header('Content-Type: ' . $mime);
      $this->createImage($this->image, $mime);
   }

   private function createImage(mixed $image, string $mime): void {
      switch ($mime) {
         case 'image/jpeg':
            imageinterlace($this->image, null);
            imagejpeg($this->image, null, $this->quality);
            break;
         case 'image/png':
            imagesavealpha($this->image, true);
            imagepng($this->image, null, (int) (($this->quality / 100) * 9));
            break;
         case 'image/gif':
            imagesavealpha($this->image, true);
            imagegif($this->image, null);
            break;
         case 'image/webp':
            if (!function_exists('imagewebp')) {
               throw new SystemException('WebP image type is not supported');
            }
            imagesavealpha($this->image, true);
            imagewebp($this->image, null, $this->quality);
            break;
         case 'image/bmp':
         case 'image/x-bmp':
         case 'image/x-ms-bmp':
         case 'image/x-windows-bmp':
            imagebmp($this->image, null, true);
            break;
         default:
            throw new SystemException('Unsupported image type');
      }
   }

   private function getColor(array $color): array {
      [$r, $g, $b] = $color;
      $a = isset($color[3]) ? 127 - (($color[3] * 127) / 100) : 0;
      return [(int) $r, (int) $g, (int) $b, (int) $a];
   }

   private function cropImage(int $width, int $height, ?int $x = null, ?int $y = null): self {
      $image = imagecreatetruecolor($width, $height);
      if ($image === false) {
         return $this;
      }

      $background = imagecolorallocatealpha($image, ...$this->getColor($this->background));
      imagefill($image, 0, 0, $background);

      $x = is_null($x) ? (int) (($this->width - $width) / 2) : $x;
      $y = is_null($y) ? (int) (($this->height - $height) / 2) : $y;

      $srcX = (int) max(0, $x);
      $srcY = (int) max(0, $y);
      $dstX = (int) max(0, -$x);
      $dstY = (int) max(0, -$y);

      $cropWidth = (int) min($width, $this->width - $srcX);
      $cropHeight = (int) min($height, $this->height - $srcY);

      imagecopyresampled($image, $this->image, $dstX, $dstY, $srcX, $srcY, $cropWidth, $cropHeight, $cropWidth, $cropHeight);

      $this->image = $image;
      $this->width = $width;
      $this->height = $height;

      return $this;
   }

   private function fillImage(int $width, int $height, ?int $x = null, ?int $y = null): self {
      $image = imagecreatetruecolor($width, $height);
      if ($image === false) {
         return $this;
      }

      $background = imagecolorallocatealpha($image, ...$this->getColor($this->background));
      imagecolortransparent($image, $background);
      imagefill($image, 0, 0, $background);

      $sourceRatio = $this->width / $this->height;
      $targetRatio = $width / $height;

      if ($sourceRatio > $targetRatio) {
         $resizeWidth = $width;
         $resizeHeight = (int) ceil($width / $sourceRatio);
      } else {
         $resizeWidth = (int) ceil($height * $sourceRatio);
         $resizeHeight = $height;
      }

      $x = is_null($x) ? (int) (($width - $resizeWidth) / 2) : $x;
      $y = is_null($y) ? (int) (($height - $resizeHeight) / 2) : $y;

      imagecopyresampled($image, $this->image, $x, $y, 0, 0, $resizeWidth, $resizeHeight, $this->width, $this->height);

      $this->image = $image;
      $this->width = $width;
      $this->height = $height;

      return $this;
   }

   private function checkPath(): void {
      if (!check_path($this->path)) {
         throw new SystemException("Image file upload directory is invalid [{$this->path}]");
      }

      if (!check_permission($this->path)) {
         throw new SystemException("Image file upload directory is not writable [{$this->path}]");
      }
   }

   public function __destruct() {
      if ($this->image) {
         imagedestroy($this->image);
      }
   }
}
