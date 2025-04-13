<?php

declare(strict_types=1);

/**
 * @see https://www.php.net/manual/en/function.getallheaders
 */
if (!function_exists('getallheaders')) {
   function getallheaders() {
      $headers = array();

      $copy_server = array(
         'CONTENT_TYPE'   => 'Content-Type',
         'CONTENT_LENGTH' => 'Content-Length',
         'CONTENT_MD5'    => 'Content-Md5',
      );

      foreach ($_SERVER as $key => $value) {
         if (substr($key, 0, 5) === 'HTTP_') {
            $key = substr($key, 5);
            if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
               $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
               $headers[$key] = $value;
            }
         } elseif (isset($copy_server[$key])) {
            $headers[$copy_server[$key]] = $value;
         }
      }

      if (!isset($headers['Authorization'])) {
         if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
         } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
            $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
            $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
         } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
         }
      }

      return $headers;
   }
}

/**
 * @see https://www.php.net/manual/en/function.hash-equals.php
 */
if (!function_exists('hash_equals')) {
   function hash_equals(string $known_string, string $user_string): ?bool {
      if (func_num_args() !== 2) {
         trigger_error('hash_equals() expects exactly 2 parameters, ' . func_num_args() . ' given', E_USER_WARNING);
         return null;
      }

      if (defined('MB_OVERLOAD_STRING') && (ini_get('mbstring.func_overload'))) {
         $known_length = mb_strlen($known_string, '8bit');
         $user_length = mb_strlen($user_string, '8bit');
      } else {
         $known_length = strlen($known_string);
         $user_length = strlen($user_string);
      }

      if ($known_length !== $user_length) {
         return false;
      }

      $compare = $known_string ^ $user_string;
      $result = 0;

      for ($i = 0, $len = strlen($compare); $i < $len; $i++) {
         $result |= ord($compare[$i]);
      }

      return $result === 0;
   }
}

/**
 * @see https://www.php.net/manual/en/function.openssl-random-pseudo-bytes
 */
if (!function_exists('openssl_random_pseudo_bytes')) {
   function openssl_random_pseudo_bytes(int $length): string {
      if (function_exists('random_bytes')) {
         return random_bytes($length);
      }

      $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
      $length = strlen($chars);
      $bytes = '';

      for ($i = 0; $i < $length; $i++) {
         $bytes .= $chars[random_int(0, $length - 1)];
      }

      return $bytes;
   }
}


/**
 * @see https://www.php.net/manual/en/function.str-starts-with
 */
if (!function_exists('str_starts_with')) {
   function str_starts_with(string $haystack, string $needle): bool {
      return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
   }
}

/**
 * @see https://www.php.net/manual/en/function.str-contains
 */
if (!function_exists('str_contains')) {
   function str_contains(string $haystack, string $needle): bool {
      return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
   }
}

/**
 * @see https://www.php.net/manual/en/function.str-ends-with
 */
if (!function_exists('str_ends_with')) {
   function str_ends_with(string $haystack, string $needle): bool {
      return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
   }
}

if (!function_exists('mb_str_starts_with')) {
   function mb_str_starts_with(string $haystack, string $needle): bool {
      return mb_strlen($needle) === 0 || mb_strpos($haystack, $needle) === 0;
   }
}

if (!function_exists('mb_str_contains')) {
   function mb_str_contains(string $haystack, string $needle): bool {
      return mb_strlen($needle) === 0 || mb_strpos($haystack, $needle) !== false;
   }
}

if (!function_exists('mb_str_ends_with')) {
   function mb_str_ends_with(string $haystack, string $needle): bool {
      return mb_strlen($needle) === 0 || mb_substr($haystack, -mb_strlen($needle)) === $needle;
   }
}
