<?php

declare(strict_types=1);

/**
 * array_serialize
 *
 * @param array $data
 *
 * @return string
 */
if (!function_exists('array_serialize')) {
   function array_serialize(array $data): string {
      if (!is_array($data)) {
         return null;
      }

      $result = '';
      foreach ($data as $key => $val) {
         $result .= "{$key}={$val}&";
      }

      return rtrim($result, '&');
   }
}
