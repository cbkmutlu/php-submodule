<?php

declare(strict_types=1);

namespace System\Secure;

use System\Exception\SystemException;

class Secure {
   private $crypt_algorithm;
   private $crypt_phrase;
   private $crypt_key;
   private $hash_cost;
   private $hash_algorithm;

   public function __construct() {
      $config = import_config('defines.secure');
      $this->crypt_algorithm = $config['crypt_algorithm'];
      $this->crypt_phrase = $config['crypt_phrase'];
      $this->crypt_key = $config['crypt_key'];
      $this->hash_cost = $config['hash_cost'];
      $this->hash_algorithm = $config['hash_algorithm'];
   }

   // crypt encode
   public function encode(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $iv = random_bytes(openssl_cipher_iv_length($this->crypt_algorithm));
      $encrypted = openssl_encrypt($value, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);

      return strtr(base64_encode($iv . $encrypted), '+/=', '-,');
   }

   // crypt decode
   public function decode(string $value, ?string $key = null): string {
      if (is_null($key)) {
         $key = $this->crypt_key;
      }

      $data = base64_decode(strtr($value, '-,', '+/='));
      $iv_length = openssl_cipher_iv_length($this->crypt_algorithm);
      $iv = substr($data, 0, $iv_length);
      $encrypted = substr($data, $iv_length);
      $decrypted = openssl_decrypt($encrypted, $this->crypt_algorithm, hash($this->crypt_phrase, $key, true), 0, $iv);

      if (!$decrypted) {
         throw new SystemException('Decoding failed');
      }

      return trim($decrypted);
   }

   // create hash
   public function hash(string $value, array $options = []): string {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->hash_cost;
      }

      $hash = password_hash($value, $this->hash_algorithm, $options);

      if (!$hash) {
         throw new SystemException('Hash not supported');
      }

      return $hash;
   }

   // verify hash
   public function verify(string $value, string $hash): bool {
      return password_verify($value, $hash);
   }

   // refresh hash
   public function refresh(string $hash, array $options = []): bool {
      if (!isset($options['cost'])) {
         $options['cost'] = $this->hash_cost;
      }

      return password_needs_rehash($hash, $this->hash_algorithm, $options);
   }
}
