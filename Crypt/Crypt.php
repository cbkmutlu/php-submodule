<?php

declare(strict_types=1);

namespace System\Crypt;

use System\Exception\SystemException;

class Crypt {
   private $secret;
   private $cipher;
   private $phrase;
   private $cost;
   private $algorithm;

   public function __construct() {
      $config          = import_config('defines.crypt');
      $this->secret    = $config['secret'];
      $this->cipher    = $config['cipher'];
      $this->phrase    = $config['phrase'];
      $this->cost      = $config['cost'];
      $this->algorithm = $config['algorithm'];
   }

   public function encode(string $value, ?string $secret = null): string {
      if (is_null($secret)) {
         $secret = $this->secret;
      }

      $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
      $encrypted = openssl_encrypt($value, $this->cipher, hash($this->phrase, $secret, true), 0, $iv);

      return $this->base64Encode($iv . $encrypted);
   }

   public function decode(string $value, ?string $secret = null): string {
      if (is_null($secret)) {
         $secret = $this->secret;
      }

      $data = $this->base64Decode($value);
      $iv_length = openssl_cipher_iv_length($this->cipher);
      $iv = substr($data, 0, $iv_length);
      $encrypted = substr($data, $iv_length);
      $decrypted = openssl_decrypt($encrypted, $this->cipher, hash($this->phrase, $secret, true), 0, $iv);

      if (!$decrypted) {
         throw new SystemException('Decoding failed');
      }

      return trim($decrypted);
   }

   public function hash(string $value, array $options = []): string {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->cost;
      }

      $hash = password_hash($value, $this->algorithm, $options);

      if (!$hash) {
         throw new SystemException('Hash not supported');
      }

      return $hash;
   }

   public function verify(string $value, string $hash): bool {
      return password_verify($value, $hash);
   }

   public function refresh(string $hash, array $options = []): bool {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->cost;
      }

      return password_needs_rehash($hash, $this->algorithm, $options);
   }

   private function base64Encode(string $data): string {
      return strtr(rtrim(base64_encode($data), '='), '+/', '-_');
   }

   private function base64Decode(string $data): string {
      $data = strtr($data, '-_', '+/');
      $padding = strlen($data) % 4;
      if ($padding) {
         $data .= str_repeat('=', 4 - $padding);
      }

      $decoded = base64_decode($data, true); // strict
      if ($decoded === false) {
         throw new SystemException('Invalid base64 input');
      }

      return $decoded;
   }
}
