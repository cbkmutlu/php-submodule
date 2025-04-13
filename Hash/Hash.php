<?php

declare(strict_types=1);

namespace System\Hash;

use System\Exception\ExceptionHandler;

class Hash {
   private $hash_cost;
   private $hash_algorithm;
   private $crypt_algorithm;
   private $crypt_phrase;
   private $crypt_key;

   public function __construct() {
      $this->hash_cost = config('defines.secure.cost');
      $this->hash_algorithm = config('defines.secure.hash_algorithm');
      $this->crypt_algorithm = config('defines.secure.crypt_algorithm');
      $this->crypt_phrase = config('defines.secure.crypt_phrase');
      $this->crypt_key = config('defines.secure.crypt_key');
   }

   public function create(string $value, array $options = []): string {
      if (!array_key_exists('cost', $options)) {
         $options['cost'] = $this->hash_cost;
      }

      $hash = password_hash($value, $this->hash_algorithm, $options);

      if ($hash === false) {
         throw new ExceptionHandler('Error', 'Bcrypt hash not supported');
      }

      return $hash;
   }

   public function verify(string $value, string $hash): bool {
      return password_verify($value, $hash);
   }

   public function refresh(string $hash, array $options = []): bool {
      if (!array_key_exists('cost', $options)) {
         $options['cost'] = $this->hash_cost;
      }

      return password_needs_rehash($hash, $this->hash_algorithm, $options);
   }

   public function encrypt(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->crypt_algorithm));
      $encrypted = openssl_encrypt($value, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);
      return strtr(base64_encode($iv . $encrypted), '+/=', '-,');
   }

   public function decrypt(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $data = base64_decode(strtr($value, '-,', '+/='));
      $iv_length = openssl_cipher_iv_length($this->crypt_algorithm);
      $iv = substr($data, 0, $iv_length);
      $encrypted = substr($data, $iv_length);
      $decrypted = openssl_decrypt($encrypted, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);
      if ($decrypted === false) {
         throw new ExceptionHandler('Error', 'Decryption failed');
      }
      return trim($decrypted);
   }
}
