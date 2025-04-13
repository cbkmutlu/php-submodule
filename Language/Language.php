<?php

declare(strict_types=1);

namespace System\Language;

use System\Exception\ExceptionHandler;
use System\Session\Session;

class Language {
   private $session;
   private $locale;
   private $translations = [];
   private $config = [];

   public function __construct(Session $session) {
      $this->config = config('defines.language');
      $this->session = $session;

      if ($this->session->exist('session_locale')) {
         $this->locale = $this->session->read('session_locale');
      } else {
         $this->locale = $this->config['default'];
         $this->session->save('session_locale', $this->config['default']);
      }
   }

   /**
    * locale
    *
    * @param string|null $locale
    *
    * @return string
    */
   public function locale(?string $locale = null): string {
      if (is_null($locale)) {
         return $this->locale;
      }

      $this->locale = empty($locale) ? $this->config['default'] : $locale;
      $this->session->save('session_locale', $this->locale);
      return $this->locale;
   }

   /**
    * set
    *
    * @param string $locale
    *
    * @return self
    */
   public function set(string $locale): self {
      $this->locale = $locale;
      return $this;
   }

   /**
    * get
    *
    * @param string $file
    * @param string $key
    * @param mixed|null $change
    *
    * @return mixed
    */
   public function get(string $file = '', string $key = '', mixed $change = null): mixed {
      if (!is_string($file) || !is_string($key)) {
         return false;
      }

      $explode = explode('@', $file);
      if (isset($explode[1])) {
         $path = SYSTEM_DIR . 'Language/' . $this->locale . '/' . ucwords($explode[1]) . '.php';
         $file = $explode[1];
      } else {
         $path = APP_DIR . 'Modules/' . ucwords($explode[0]) . '/Languages/' . $this->locale . '.php';
         $file = $explode[0];
      }

      $locale = $this->locale . '_' . $file;
      if (!array_key_exists($locale, $this->translations)) {
         if (file_exists($path)) {
            $this->translations[$locale] = require_once $path;
         } else {
            throw new ExceptionHandler('Dosya bulunamadı', '<b>Language : </b> ' . $file);
         }
      }

      if ($this->session->exist('session_locale')) {
         $session_locale = $this->session->read('session_locale');
         if ($session_locale !== $this->locale) {
            $this->locale = $session_locale;
         }
      }

      if (array_key_exists($key, $this->translations[$locale])) {
         $message = $this->translations[$locale][$key];

         if (is_array($change)) {
            return vsprintf($message, $change);
         } else if (!is_null($change)) {
            return sprintf($message, $change);
         }

         return $message;
      }

      return false;
   }
}
