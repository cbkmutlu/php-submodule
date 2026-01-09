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
      $secret = $secret ?? $this->secret;
      $iv_length = openssl_cipher_iv_length($this->cipher);
      $iv = random_bytes($iv_length);
      $key = hash($this->phrase, $secret, true);
      $encrypted = openssl_encrypt($value, $this->cipher, $key, 0, $iv);

      if ($encrypted === false) {
         throw new SystemException('Encryption failed');
      }

      return $this->base64Encode($iv . $encrypted);
   }

   public function decode(string $value, ?string $secret = null): string {
      $secret = $secret ?? $this->secret;
      $value = $this->base64Decode($value);
      $iv_length = openssl_cipher_iv_length($this->cipher);
      $iv = substr($value, 0, $iv_length);
      $key = hash($this->phrase, $secret, true);
      $decrypted = openssl_decrypt(substr($value, $iv_length), $this->cipher, $key, 0, $iv);

      if ($decrypted === false) {
         throw new SystemException('Decryption failed');
      }

      return trim($decrypted);
   }

   public function hash(string $value, array $options = []): string {
      $options['cost'] = $options['cost'] ?? $this->cost;
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
      $options['cost'] = $options['cost'] ?? $this->cost;

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
