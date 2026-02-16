<?php

declare(strict_types=1);

namespace System\Language;

use System\Exception\SystemException;
use System\Session\Session;
use App\Core\Enums\LanguageEnum;

class Language {
   private $locale;
   private $translations = [];

   public function __construct(
      private Session $session
   ) {
      $config = import_config('defines.language');

      if ($this->session->has('session_locale')) {
         $this->locale = $this->session->get('session_locale');
      } else {
         $this->locale = $config['default'];
      }
   }

   public function setLocale(string|int $locale): self {
      if (is_int($locale)) {
         $locale = LanguageEnum::resolve($locale);
      }

      $this->session->set('session_locale', $locale);
      $this->locale = $locale;

      return $this;
   }

   public function getLocale(): string {
      return $this->locale;
   }

   public function module(string $params, ?array $printf = null, ?string $locale = null): string {
      $locale = $locale ?? $this->locale;
      [$file, $key] = explode('.', $params);
      $path = APP_DIR . 'Modules/' . strtolower($file) . '/Languages/' . strtolower($locale) . '.php';
      $file = $locale . '_module_' . strtolower($file);

      return $this->checkFile($file, $path, $key, $printf, $locale);
   }

   public function system(string $params, ?array $printf = null, ?string $locale = null): string {
      $locale = $locale ?? $this->locale;
      [$file, $key] = explode('.', $params);
      $path = SYSTEM_DIR . 'Language/' . $locale . '/' . strtolower($file) . '.php';
      $file = $locale . '_system_' . strtolower($file);

      return $this->checkFile($file, $path, $key, $printf, $locale);
   }

   private function checkFile(string $file, string $path, string $key, ?array $printf, ?string $locale): string {
      if (!isset($this->translations[$file])) {
         if (!is_file($path)) {
            throw new SystemException('Language file [' . $path . '] not found');
         }
         $this->translations[$file] = require_once $path;
      }

      if ($this->session->has('session_locale')) {
         $session_locale = $this->session->get('session_locale');
         if ($session_locale !== $locale) {
            $this->locale = $session_locale;
         }
      }

      if (!isset($this->translations[$file][$key])) {
         return $key;
      }
      $message = $this->translations[$file][$key];

      return is_array($printf) ? vsprintf($message, $printf) : $message;
   }
}
