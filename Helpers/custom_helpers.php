<?php

declare(strict_types=1);

use System\Router\Router;
use System\Exception\SystemException;

if (!function_exists('dd')) {
   function dd(mixed $data, bool $stop = false): void {
      echo '<pre>';
      print_r($data);
      echo '</pre>';

      if ($stop === true || ENV !== 'production') {
         exit();
      }
   }
}

if (!function_exists('check_equal')) {
   function check_equal(mixed $safe, mixed $store): bool {
      $safe_len = strlen($safe);
      $store_len = strlen($store);

      if ($store_len != $safe_len) {
         return false;
      }

      $result = 0;

      for ($i = 0; $i < $store_len; $i++) {
         $result |= (ord($safe[$i]) ^ ord($store[$i]));
      }

      return $result === 0;
   }
}

if (!function_exists('check_path')) {
   function check_path(string $path, int $permissions = 0755): bool {
      if (!is_dir($path) && !mkdir($path, $permissions, true)) {
         return false;
      }

      return true;
   }
}

if (!function_exists('check_permission')) {
   function check_permission(string $path, int $permissions = 0755): bool {
      if (!is_readable($path) || !is_writable($path)) {
         if (!chmod($path, $permissions)) {
            return false;
         }
      }

      return true;
   }
}

if (!function_exists('format_mask')) {
   function format_mask(string $value, string $mask = "*"): string {
      if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
         [$name, $domain] = explode('@', $value, 2);

         if ($name === '' || $domain === '') {
            return $value;
         }

         if (strlen($name) <= 2) {
            return str_repeat($mask, strlen($name)) . '@' . $domain;
         }

         $count = intval(floor(strlen($name) / 2));
         return substr($name, 0, $count) . str_repeat($mask, strlen($name) - $count) . '@' . $domain;
      } else {
         if (strlen($value) <= 2) {
            return str_repeat($mask, strlen($value));
         }

         $count = intval(floor(strlen($value) / 2));
         return substr($value, 0, $count) . str_repeat($mask, strlen($value) - $count);
      }
   }
}

if (!function_exists('format_thousand')) {
   function format_thousand(int $num): string {
      if ($num < 1000) {
         return (string)$num;
      }

      $units = ['k', 'm', 'b', 't'];
      $exp = floor(log($num, 1000));

      return round($num / (1000 ** $exp), 1) . $units[$exp - 1];
   }
}

if (!function_exists('format_size')) {
   function format_size(int $bytes, int $precision = 3): string {
      if ($bytes < 1024) {
         return $bytes . 'b';
      }

      $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
      $exp = floor(log($bytes, 1024));

      return round($bytes / (1024 ** $exp), $precision) . $units[$exp];
   }
}

if (!function_exists('format_time')) {
   function format_time(float $time): array {
      $units = ['year' => 31536000, 'month' => 2592000, 'week' => 604800, 'day' => 86400, 'hour' => 3600, 'minute' => 60, 'second' => 1, 'millisecond' => 0.001];

      foreach ($units as $unit => $value) {
         if ($time >= $value) {
            return [
               'unit' => $unit,
               'value' => round($time / $value, 1)
            ];
         }
      }

      return [
         'unit' => 'millisecond',
         'value' => round($time * 1000)
      ];
   }
}

if (!function_exists('calculate_reading')) {
   function calculate_reading(string $content, int $speed = 2): array {
      $words = round(str_word_count(strip_tags($content)));
      $time = max(60, ceil($words / $speed));

      return format_time($time);
   }
}

// calculate_distance(32.9697, -96.80322, 29.46786, -98.53506, "M")
if (!function_exists('calculate_distance')) {
   function calculate_distance(float $lat1, float $lon1, float $lat2, float $lon2, string $unit): float {
      $theta = $lon1 - $lon2;
      $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
      $dist = acos($dist);
      $dist = rad2deg($dist);
      $miles = $dist * 60 * 1.1515;
      $unit = strtoupper($unit);

      if ($unit == "K") {
         return ($miles * 1.609344);
      } else if ($unit == "N") {
         return ($miles * 0.8684);
      } else {
         return $miles;
      }
   }
}

if (!function_exists('array_serialize')) {
   function array_serialize(array $data, bool $prepend = true): string {
      $query = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
      return $prepend && $query ? '?' . $query : $query;
   }
}

if (!function_exists('array_keys_diff')) {
   function array_keys_diff(array $array1, array $array2): bool {
      return array_diff($array2, array_keys($array1)) || array_diff(array_keys($array1), $array2);
   }
}

if (!function_exists('escape_xss')) {
   function escape_xss(mixed $data): mixed {
      if (is_string($data)) {
         return trim(htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      }
      return $data;
   }
}

if (!function_exists('escape_html')) {
   function escape_html(string $data): string {
      return strip_tags(htmlentities(trim(stripslashes($data)), ENT_NOQUOTES, "UTF-8"));
   }
}

if (!function_exists('import_asset')) {
   function import_asset(?string $file = null, mixed $version = null): mixed {
      if (!is_null($file)) {
         if (!file_exists(ROOT_DIR . '/Public/' . $file)) {
            throw new SystemException("File not found in Public directory [{$file}]");
         }

         if (!is_null($version)) {
            return ROOT_DIR . '/Public/' . $file . '?' . $version;
         }

         return ROOT_DIR . '/Public/' . $file;
      }

      return ROOT_DIR . '/Public/';
   }
}

if (!function_exists('import_config')) {
   function import_config(string $params): array {
      [$file, $value] = explode('.', $params, 2);

      if (!file_exists($path = APP_DIR . 'Config/' . ucwords($file) . '.php')) {
         throw new SystemException("File not found in Config directory [{$path}]");
      }

      $config = require $path;
      $keys = explode('.', $value);

      foreach ($keys as $key) {
         if (!isset($config[$key])) {
            throw new SystemException("Invalid key [{$key}]");
         }
         $config = $config[$key];
      }

      return $config;
   }
}

if (!function_exists('import_env')) {
   function import_env(string $file = '.env'): void {
      $file = ROOT_DIR . '/' . $file;
      if (!file_exists($file)) {
         return;
      }

      $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
         if (str_starts_with(trim($line), '#')) {
            continue;
         }

         [$key, $value] = explode('=', $line, 2);
         $key = trim($key);
         $value = trim($value, "'\"");
         putenv("$key=$value");
      }
   }
}

if (!function_exists('random_guid')) {
   function random_guid(array $haystack = []): string {
      $timestamp = dechex(time());
      $uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';

      $uuid = preg_replace_callback('/[xy]/', function ($matches) {
         $rand = rand(0, 15);
         return $matches[0] === 'x' ? $rand : ($rand & 0x3 | 0x8);
      }, $uuid);

      if (in_array($uuid, $haystack)) {
         return random_guid($haystack);
      }

      return substr($uuid, 0, -8) . $timestamp;
   }
}

if (!function_exists('random_string')) {
   function random_string(int $length = 8, array $options = []): string {
      $defaults = [
         'upperCase' => true,
         'lowerCase' => true,
         'numbers' => true
      ];

      $options = array_merge($defaults, $options);

      $characters = [
         'upperCase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
         'lowerCase' => 'abcdefghijklmnopqrstuvwxyz',
         'numbers' => '0123456789'
      ];

      $result = '';
      if ($options['numbers']) {
         $result = $result . $characters['numbers'];
      }

      if ($options['upperCase']) {
         $result = $result . $characters['upperCase'];
      }

      if ($options['lowerCase']) {
         $result = $result . $characters['lowerCase'];
      }

      return substr(str_shuffle($result), 0, $length);
   }
}

if (!function_exists('random_mnemonic')) {
   function random_mnemonic(int $letters = 6, int $digits = 0): string {
      $result = '';
      $charset = [
         0 => ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'],
         1 => ['a', 'e', 'i', 'o', 'u']
      ];

      for ($i = 0; $i < $letters; $i++) {
         $result .= $charset[$i % 2][array_rand($charset[$i % 2])];
      }

      for ($i = 0; $i < $digits; $i++) {
         $result .= mt_rand(0, 9);
      }

      return $result;
   }
}

if (!function_exists('uri_get')) {
   function uri_get(): string {
      $path = array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1);
      $path = implode('/', $path) . '/';
      $uri = substr($_SERVER['REQUEST_URI'], strlen($path));

      if (strpos($uri, '?') !== false) {
         $uri = substr($uri, 0, strpos($uri, '?'));
      }

      return '/' . trim($uri, '/');
   }
}

if (!function_exists('uri_parse')) {
   function uri_parse(string $uri, array $expressions): array {
      $segments = explode('/', ltrim($uri, '/'));

      return array_map(function ($segment) use ($expressions) {
         if (preg_match('/[\[{\(](.*)[\]}\)]/U', $segment, $match)) {
            $key = $match[1];
            return $expressions[$key] ?? $segment;
         }
         return $segment;
      }, $segments);
   }
}

if (!function_exists('url_route')) {
   function url_route(string $name, array $params = []): string {
      return BASE_DIR . '/' . Router::url($name, $params);
   }
}

if (!function_exists('url_redirect')) {
   function url_redirect(string $url, int $delay = 0): void {
      if ($delay > 0) {
         header('Refresh: ' . $delay . ';url=' . $url);
      } else {
         header('Location: ' . $url);
      }
      exit();
   }
}

if (!function_exists('url_back')) {
   function url_back(): void {
      header('Location: ' . $_SERVER['HTTP_REFERER']);
      exit();
   }
}

if (!function_exists('url_slug')) {
   function url_slug(string $string, array $options = []): string {
      $string = mb_convert_encoding((string)$string, 'UTF-8', mb_list_encodings());

      $defaults = [
         'delimiter' => '-',
         'limit' => null,
         'lowercase' => true,
         'replacements' => [],
         'transliterate' => true
      ];

      $options = array_merge($defaults, $options);

      $char_map = [
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
         'ÿ' => 'y',
         'þ' => 'th',

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
      ];

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

if (!function_exists('turkish_suffix')) {
   function turkish_suffix(string $name, string $suffixType = "in"): string {
      // Türkçe karakter setleri
      $hardConsonants = ['ç', 'f', 'h', 'k', 'p', 's', 'ş', 't'];
      $vowels = ['a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü'];
      $nameLower = trim(mb_strtolower($name, 'UTF-8'));
      $lastChar = mb_substr($nameLower, -1, 1, 'UTF-8');
      $lastVowel = null;

      for ($i = mb_strlen($nameLower, 'UTF-8') - 1; $i >= 0; $i--) {
         $char = mb_substr($nameLower, $i, 1, 'UTF-8');
         if (in_array($char, $vowels)) {
            $lastVowel = $char;
            break;
         }
      }

      $suffix = '';
      switch ($suffixType) {
         case "in": // iyelik eki -> Ahmet'in
            if (in_array($lastChar, $vowels)) {
               if (in_array($lastChar, ['a', 'ı'])) {
                  $suffix = "'nın";
               } elseif (in_array($lastChar, ['e', 'i'])) {
                  $suffix = "'nin";
               } elseif (in_array($lastChar, ['o', 'u'])) {
                  $suffix = "'nun";
               } elseif (in_array($lastChar, ['ö', 'ü'])) {
                  $suffix = "'nün";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı'])) {
                  $suffix = "'ın";
               } elseif (in_array($lastVowel, ['e', 'i'])) {
                  $suffix = "'in";
               } elseif (in_array($lastVowel, ['o', 'u'])) {
                  $suffix = "'un";
               } elseif (in_array($lastVowel, ['ö', 'ü'])) {
                  $suffix = "'ün";
               }
            }
            break;

         case "e": // yönelme hali -> Ahmet'e
            if (in_array($lastChar, $vowels)) {
               if (in_array($lastChar, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'ya";
               } elseif (in_array($lastChar, ['e', 'i', 'ö', 'ü'])) {
                  $suffix = "'ye";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'a";
               } elseif (in_array($lastVowel, ['e', 'i', 'ö', 'ü'])) {
                  $suffix = "'e";
               }
            }
            break;

         case "i": // belirtme hali -> Ahmet'i
            if (in_array($lastChar, $vowels)) {
               if (in_array($lastChar, ['a', 'ı'])) {
                  $suffix = "'yı";
               } elseif (in_array($lastChar, ['e', 'i'])) {
                  $suffix = "'yi";
               } elseif (in_array($lastChar, ['o', 'u'])) {
                  $suffix = "'yu";
               } elseif (in_array($lastChar, ['ö', 'ü'])) {
                  $suffix = "'yü";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı'])) {
                  $suffix = "'ı";
               } elseif (in_array($lastVowel, ['e', 'i'])) {
                  $suffix = "'i";
               } elseif (in_array($lastVowel, ['o', 'u'])) {
                  $suffix = "'u";
               } elseif (in_array($lastVowel, ['ö', 'ü'])) {
                  $suffix = "'ü";
               }
            }
            break;

         case "de": // bulunma hali -> Ahmet'te / Ayşe'de
            if (in_array($lastChar, $hardConsonants)) {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'ta";
               } else {
                  $suffix = "'te";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'da";
               } else {
                  $suffix = "'de";
               }
            }
            break;

         case "den": // ayrılma hali -> Ahmet'ten / Ayşe'den
            if (in_array($lastChar, $hardConsonants)) {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'tan";
               } else {
                  $suffix = "'ten";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'dan";
               } else {
                  $suffix = "'den";
               }
            }
            break;

         case "le": // beraberlik hali -> Ayşeyle / Ahmetle
            if (in_array($lastChar, $vowels)) {
               if (in_array($lastChar, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'yla";
               } else {
                  $suffix = "'yle";
               }
            } else {
               if (in_array($lastVowel, ['a', 'ı', 'o', 'u'])) {
                  $suffix = "'la";
               } else {
                  $suffix = "'le";
               }
            }
            break;
      }

      // Varsayılan ek
      if (empty($suffix)) {
         $suffix = "'" . $suffixType;
      }

      return $name . $suffix;
   }
}
