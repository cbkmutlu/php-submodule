<?php

declare(strict_types=1);

use System\Exception\ExceptionHandler;
use System\Starter\Starter;

if (!function_exists('authorization')) {
   function authorization() {
      $headers = null;
      if (isset($_SERVER['Authorization'])) {
         $headers = $_SERVER["Authorization"];
      } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
         $headers = $_SERVER["HTTP_AUTHORIZATION"];
      } else {
         if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
         } else {
            $headers = getallheaders();
         }

         $headers = array_combine(array_map('ucwords', array_keys($headers)), array_values($headers));
         if (isset($headers['Authorization'])) {
            $headers = $headers['Authorization'];
         }
      }

      return trim($headers);
   }
}

if (!function_exists('import')) {
   function import(string $file, bool $once = false): mixed {
      if (!file_exists($file = APP_DIR . $file . '.php')) {
         throw new ExceptionHandler('Dosya bulunamadı.', '<b>File : </b>' . $file . '.php');
      }

      return ($once === true) ? require_once $file : require $file;
   }
}

if (!function_exists('asterisks')) {
   function asterisks(string $value): string {
      if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
         [$name, $domain] = explode('@', $value, 2);

         if ($name === '' || $domain === '') {
            return $value;
         }

         if (strlen($name) <= 2) {
            return str_repeat('*', strlen($name)) . '@' . $domain;
         }

         $count = intval(floor(strlen($name) / 2));
         return substr($name, 0, $count) . str_repeat('*', strlen($name) - $count) . '@' . $domain;
      } else {
         if (strlen($value) <= 2) {
            return str_repeat('*', strlen($value));
         }

         $count = intval(floor(strlen($value) / 2));
         return substr($value, 0, $count) . str_repeat('*', strlen($value) - $count);
      }
   }
}

if (!function_exists('escape')) {
   function escape(string $data): string {
      return strip_tags(htmlentities(trim(stripslashes($data)), ENT_NOQUOTES, "UTF-8"));
   }
}

if (!function_exists('equal')) {
   function equal(mixed $safe, mixed $store): bool {
      $safeLen = strlen($safe);
      $storeLen = strlen($store);

      if ($storeLen != $safeLen) {
         return false;
      }

      $result = 0;

      for ($i = 0; $i < $storeLen; $i++) {
         $result |= (ord($safe[$i]) ^ ord($store[$i]));
      }

      return $result === 0;
   }
}

if (!function_exists('dd')) {
   function dd(mixed $data, bool $stop = true): void {
      echo '<pre>';
      print_r($data);
      echo '</pre>';

      if ($stop === true) {
         exit();
      }
   }
}

if (!function_exists('redirect')) {
   function redirect(string $url, int $delay = 0): void {
      if ($delay > 0) {
         header("Refresh:" . $delay . ";url=" . $url);
      } else {
         header("Location:" . $url);
      }
      exit();
   }
}

if (!function_exists('back')) {
   function back(): void {
      header('Location: ' . $_SERVER['HTTP_REFERER']);
      exit();
   }
}

if (!function_exists('asset')) {
   function asset(?string $file = null, mixed $version = null): string {
      if (!is_null($file)) {
         if (!file_exists(ROOT_DIR . '/Public/' . $file)) {
            throw new ExceptionHandler('Dosya bulunamadı', '<b>Public : </b> ' . $file);
         }

         if (!is_null($version)) {
            return ROOT_DIR . '/Public/' . $file . '?' . $version;
         }

         return ROOT_DIR . '/Public/' . $file;
      }

      return ROOT_DIR . '/Public/';
   }
}

if (!function_exists('config')) {
   function config(string $params): mixed {
      $keys = explode('.', $params);
      $file = $keys[0];

      if (!file_exists($path = APP_DIR . 'Config/' . ucwords($file) . '.php')) {
         throw new ExceptionHandler('Dosya bulunamadı.', "<b>Config :</b> $path");
      }

      $config = require $path;
      array_shift($keys);

      foreach ($keys as $key) {
         if (!isset($config[$key])) {
            throw new ExceptionHandler("Geçersiz konfigürasyon anahtarı: ", "$params");
         }
         $config = $config[$key];
      }

      return $config;
   }
}


if (!function_exists('counter')) {
   function counter(int $num): string {
      if ($num < 1000) {
         return (string)$num;
      }

      $units = ['k', 'm', 'b', 't'];
      $exp = floor(log($num, 1000));

      return round($num / (1000 ** $exp), 1) . $units[$exp - 1];
   }
}

if (!function_exists('href')) {
   function href(string $url): string {
      return BASE_DIR . '/' . $url;
   }
}

if (!function_exists('route')) {
   function route(string $name, array $params = []): string {
      return href(Starter::router()->url($name, $params));
   }
}

if (!function_exists('sanitize')) {
   function sanitize(mixed $value): string {
      $str = preg_replace('/\x00|<[^>]*>?/', '', $value);
      return str_replace(["'", '"'], ['&#39;', '&#34;'], $str);
   }
}

if (!function_exists('slug')) {
   function slug(string $string, array $options = []): string {
      $string = mb_convert_encoding((string)$string, 'UTF-8', mb_list_encodings());

      $defaults = array(
         'delimiter' => '-',
         'limit' => null,
         'lowercase' => true,
         'replacements' => array(),
         'transliterate' => true,
      );

      $options = array_merge($defaults, $options);

      $char_map = array(
         // Latin
         'À' => 'A',
         'Á' => 'A',
         'Â' => 'A',
         'Ã' => 'A',
         'Ä' => 'A',
         'Å' => 'A',
         'Æ' => 'AE',
         'Ç' => 'C',
         'È' => 'E',
         'É' => 'E',
         'Ê' => 'E',
         'Ë' => 'E',
         'Ì' => 'I',
         'Í' => 'I',
         'Î' => 'I',
         'Ï' => 'I',
         'Ð' => 'D',
         'Ñ' => 'N',
         'Ò' => 'O',
         'Ó' => 'O',
         'Ô' => 'O',
         'Õ' => 'O',
         'Ö' => 'O',
         'Ő' => 'O',
         'Ø' => 'O',
         'Ù' => 'U',
         'Ú' => 'U',
         'Û' => 'U',
         'Ü' => 'U',
         'Ű' => 'U',
         'Ý' => 'Y',
         'Þ' => 'TH',
         'ß' => 'ss',
         'à' => 'a',
         'á' => 'a',
         'â' => 'a',
         'ã' => 'a',
         'ä' => 'a',
         'å' => 'a',
         'æ' => 'ae',
         'ç' => 'c',
         'è' => 'e',
         'é' => 'e',
         'ê' => 'e',
         'ë' => 'e',
         'ì' => 'i',
         'í' => 'i',
         'î' => 'i',
         'ï' => 'i',
         'ð' => 'd',
         'ñ' => 'n',
         'ò' => 'o',
         'ó' => 'o',
         'ô' => 'o',
         'õ' => 'o',
         'ö' => 'o',
         'ő' => 'o',
         'ø' => 'o',
         'ù' => 'u',
         'ú' => 'u',
         'û' => 'u',
         'ü' => 'u',
         'ű' => 'u',
         'ý' => 'y',
         'þ' => 'th',
         'ÿ' => 'y',

         // Latin symbols
         '©' => '(c)',

         // Greek
         'Α' => 'A',
         'Β' => 'B',
         'Γ' => 'G',
         'Δ' => 'D',
         'Ε' => 'E',
         'Ζ' => 'Z',
         'Η' => 'H',
         'Θ' => '8',
         'Ι' => 'I',
         'Κ' => 'K',
         'Λ' => 'L',
         'Μ' => 'M',
         'Ν' => 'N',
         'Ξ' => '3',
         'Ο' => 'O',
         'Π' => 'P',
         'Ρ' => 'R',
         'Σ' => 'S',
         'Τ' => 'T',
         'Υ' => 'Y',
         'Φ' => 'F',
         'Χ' => 'X',
         'Ψ' => 'PS',
         'Ω' => 'W',
         'Ά' => 'A',
         'Έ' => 'E',
         'Ί' => 'I',
         'Ό' => 'O',
         'Ύ' => 'Y',
         'Ή' => 'H',
         'Ώ' => 'W',
         'Ϊ' => 'I',
         'Ϋ' => 'Y',
         'α' => 'a',
         'β' => 'b',
         'γ' => 'g',
         'δ' => 'd',
         'ε' => 'e',
         'ζ' => 'z',
         'η' => 'h',
         'θ' => '8',
         'ι' => 'i',
         'κ' => 'k',
         'λ' => 'l',
         'μ' => 'm',
         'ν' => 'n',
         'ξ' => '3',
         'ο' => 'o',
         'π' => 'p',
         'ρ' => 'r',
         'σ' => 's',
         'τ' => 't',
         'υ' => 'y',
         'φ' => 'f',
         'χ' => 'x',
         'ψ' => 'ps',
         'ω' => 'w',
         'ά' => 'a',
         'έ' => 'e',
         'ί' => 'i',
         'ό' => 'o',
         'ύ' => 'y',
         'ή' => 'h',
         'ώ' => 'w',
         'ς' => 's',
         'ϊ' => 'i',
         'ΰ' => 'y',
         'ϋ' => 'y',
         'ΐ' => 'i',

         // Turkish
         'Ş' => 'S',
         'İ' => 'I',
         'Ç' => 'C',
         'Ü' => 'U',
         'Ö' => 'O',
         'Ğ' => 'G',
         'ş' => 's',
         'ı' => 'i',
         'ç' => 'c',
         'ü' => 'u',
         'ö' => 'o',
         'ğ' => 'g',

         // Russian
         'А' => 'A',
         'Б' => 'B',
         'В' => 'V',
         'Г' => 'G',
         'Д' => 'D',
         'Е' => 'E',
         'Ё' => 'Yo',
         'Ж' => 'Zh',
         'З' => 'Z',
         'И' => 'I',
         'Й' => 'J',
         'К' => 'K',
         'Л' => 'L',
         'М' => 'M',
         'Н' => 'N',
         'О' => 'O',
         'П' => 'P',
         'Р' => 'R',
         'С' => 'S',
         'Т' => 'T',
         'У' => 'U',
         'Ф' => 'F',
         'Х' => 'H',
         'Ц' => 'C',
         'Ч' => 'Ch',
         'Ш' => 'Sh',
         'Щ' => 'Sh',
         'Ъ' => '',
         'Ы' => 'Y',
         'Ь' => '',
         'Э' => 'E',
         'Ю' => 'Yu',
         'Я' => 'Ya',
         'а' => 'a',
         'б' => 'b',
         'в' => 'v',
         'г' => 'g',
         'д' => 'd',
         'е' => 'e',
         'ё' => 'yo',
         'ж' => 'zh',
         'з' => 'z',
         'и' => 'i',
         'й' => 'j',
         'к' => 'k',
         'л' => 'l',
         'м' => 'm',
         'н' => 'n',
         'о' => 'o',
         'п' => 'p',
         'р' => 'r',
         'с' => 's',
         'т' => 't',
         'у' => 'u',
         'ф' => 'f',
         'х' => 'h',
         'ц' => 'c',
         'ч' => 'ch',
         'ш' => 'sh',
         'щ' => 'sh',
         'ъ' => '',
         'ы' => 'y',
         'ь' => '',
         'э' => 'e',
         'ю' => 'yu',
         'я' => 'ya',

         // Ukrainian
         'Є' => 'Ye',
         'І' => 'I',
         'Ї' => 'Yi',
         'Ґ' => 'G',
         'є' => 'ye',
         'і' => 'i',
         'ї' => 'yi',
         'ґ' => 'g',

         // Czech
         'Č' => 'C',
         'Ď' => 'D',
         'Ě' => 'E',
         'Ň' => 'N',
         'Ř' => 'R',
         'Š' => 'S',
         'Ť' => 'T',
         'Ů' => 'U',
         'Ž' => 'Z',
         'č' => 'c',
         'ď' => 'd',
         'ě' => 'e',
         'ň' => 'n',
         'ř' => 'r',
         'š' => 's',
         'ť' => 't',
         'ů' => 'u',
         'ž' => 'z',

         // Polish
         'Ą' => 'A',
         'Ć' => 'C',
         'Ę' => 'e',
         'Ł' => 'L',
         'Ń' => 'N',
         'Ó' => 'o',
         'Ś' => 'S',
         'Ź' => 'Z',
         'Ż' => 'Z',
         'ą' => 'a',
         'ć' => 'c',
         'ę' => 'e',
         'ł' => 'l',
         'ń' => 'n',
         'ó' => 'o',
         'ś' => 's',
         'ź' => 'z',
         'ż' => 'z',

         // Latvian
         'Ā' => 'A',
         'Č' => 'C',
         'Ē' => 'E',
         'Ģ' => 'G',
         'Ī' => 'i',
         'Ķ' => 'k',
         'Ļ' => 'L',
         'Ņ' => 'N',
         'Š' => 'S',
         'Ū' => 'u',
         'Ž' => 'Z',
         'ā' => 'a',
         'č' => 'c',
         'ē' => 'e',
         'ģ' => 'g',
         'ī' => 'i',
         'ķ' => 'k',
         'ļ' => 'l',
         'ņ' => 'n',
         'š' => 's',
         'ū' => 'u',
         'ž' => 'z'
      );

      // Make custom replacements
      $string = preg_replace(array_keys($options['replacements']), $options['replacements'], $string);

      // Transliterate characters to ASCII
      if ($options['transliterate']) {
         $string = str_replace(array_keys($char_map), $char_map, $string);
      }

      // Replace non-alphanumeric characters with our delimiter
      $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $string);

      // Remove duplicate delimiters
      $string = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $string);

      // Truncate slug to max. characters
      $string = mb_substr($string, 0, ($options['limit'] ? $options['limit'] : mb_strlen($string, 'UTF-8')), 'UTF-8');

      // Remove delimiter from ends
      $string = trim($string, $options['delimiter']);

      return $options['lowercase'] ? mb_strtolower($string, 'UTF-8') : $string;
   }
}
