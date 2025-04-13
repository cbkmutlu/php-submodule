<?php

declare(strict_types=1);

namespace System\Jwt;

use System\Exception\ExceptionHandler;

class Jwt {
   private $secret;
   private $algorithm;
   private $leeway;
   private $expire;
   private $algorithms = [
      'HS256' => ['hash' => 'SHA256', 'type' => 'sym'],
      'HS384' => ['hash' => 'SHA384', 'type' => 'sym'],
      'HS512' => ['hash' => 'SHA512', 'type' => 'sym'],
      'RS256' => ['hash' => 'SHA256', 'type' => 'asym'],
      'RS384' => ['hash' => 'SHA384', 'type' => 'asym'],
      'RS512' => ['hash' => 'SHA512', 'type' => 'asym'],
      'ES256' => ['hash' => 'SHA256', 'type' => 'asym'],
      'ES384' => ['hash' => 'SHA384', 'type' => 'asym']
   ];
   private $claims = [
      'iss' => null,
      'aud' => null,
      'jti' => null
   ];
   private $resolver = null;
   private $revoker = null;
   private $audience = false;
   private $issuer = false;

   public function __construct() {
      $config = config('defines.jwt');
      $this->secret = $config['secret'];
      $this->algorithm = $config['algorithm'];
      $this->leeway = $config['leeway'];
      $this->expire = $config['expire'];
   }

   public function encode(array $payload, string $secret = $this->secret, string $algorithm = $this->algorithm, array $header = [], ?string $kid = null): string {
      $this->checkAlgorithm($algorithm);

      $header = array_merge([
         'typ' => 'JWT',
         'alg' => $algorithm,
         'kid' => $kid
      ], $header);

      $timestamp = time();
      $payload = array_merge([
         'jti' => bin2hex(random_bytes(16)),
         'iat' => $timestamp,
         'nbf' => $timestamp,
         'exp' => $timestamp + $this->expire
      ], $payload);

      $headerEncoded = $this->base64UrlEncode($this->jsonEncode($header));
      $payloadEncoded = $this->base64UrlEncode($this->jsonEncode($payload));
      $signatureEncoded = $this->createSignature("$headerEncoded.$payloadEncoded", $secret, $algorithm);

      return "$headerEncoded.$payloadEncoded." . $this->base64UrlEncode($signatureEncoded);
   }

   public function decode(string $token, $key = $this->secret): object {
      $parts = explode('.', $token);
      if (count($parts) !== 3) {
         throw new ExceptionHandler('Error', 'Invalid token structure');
      }

      [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
      $header = $this->jsonDecode($this->base64UrlDecode($headerEncoded));
      $payload = $this->jsonDecode($this->base64UrlDecode($payloadEncoded));
      $signature = $this->base64UrlDecode($signatureEncoded);

      $this->checkAlgorithm($header->alg);

      if ($this->resolver) {
         return ($this->resolver)($header->kid ?? null);
      }

      if ($this->verifySignature("$headerEncoded.$payloadEncoded", $signature, $key, $header->alg) === false) {
         throw new ExceptionHandler('Error', 'Signature verification failed');
      }

      $this->checkClaim($payload);

      if ($this->revoker && isset($payload->jti) && ($this->revoker)($payload->jti)) {
         throw new ExceptionHandler('Error', 'Token revoked');
      }

      return $payload;
   }

   public function setResolver(callable $resolver): void {
      $this->resolver = $resolver;
   }

   public function setRevoker(callable $revoker): void {
      $this->revoker = $revoker;
   }

   public function setClaims(array $claims): void {
      $this->claims = array_merge($this->claims, $claims);
   }

   public function setLeeway(int $leeway): void {
      $this->leeway = $leeway;
   }

   private function createSignature(string $data, string $key, string $algorithm): string {
      $type = $this->algorithms[$algorithm]['type'];
      $secret = openssl_get_privatekey($key);

      if ($type === 'asym') {
         $result = openssl_sign($data, $signature, $secret, $this->algorithms[$algorithm]['hash']);

         if ($result === false) {
            throw new ExceptionHandler('Error', 'OpenSSL unable to sign data');
         }

         return $signature;
      } else {
         return hash_hmac($this->algorithms[$algorithm]['hash'], $data, $key, true);
      }
   }

   private function verifySignature(string $data, string $signature, mixed $key, string $algorithm): bool {
      $type = $this->algorithms[$algorithm]['type'];
      $secret = openssl_get_privatekey($key);

      if ($type === 'asym') {
         return openssl_verify($data, $signature, $secret, $this->algorithms[$algorithm]['hash']) === 1;
      } else {
         return hash_equals(hash_hmac($this->algorithms[$algorithm]['hash'], $data, $key, true), $signature);
      }
   }

   private function checkClaim(object $payload): void {
      $timestamp = time();

      if (isset($payload->nbf) && ($payload->nbf - $this->leeway) > $timestamp) {
         throw new ExceptionHandler('Error', 'Token not yet valid');
      }

      if (isset($payload->exp) && ($timestamp + $this->leeway) >= $payload->exp) {
         throw new ExceptionHandler('Error', 'Token expired');
      }

      if ($this->issuer && isset($payload->iss) && $payload->iss !== $this->claims['iss']) {
         throw new ExceptionHandler('Error', 'Invalid issuer');
      }

      if ($this->audience && isset($payload->aud) && $payload->aud !== $this->claims['aud']) {
         throw new ExceptionHandler('Error', 'Invalid audience');
      }

      if ($this->claims['jti'] !== null) {
         if (!isset($payload->jti)) {
            throw new ExceptionHandler('Error', 'Missing JTI in token');
         }
         if ($payload->jti !== $this->claims['jti']) {
            throw new ExceptionHandler('Error', 'Invalid token ID');
         }
      }
   }

   private function checkAlgorithm(string $algorithm): void {
      if (empty($algorithm)) {
         throw new ExceptionHandler('Error', 'Empty algorithm');
      }

      if (!isset($this->algorithms[$algorithm])) {
         throw new ExceptionHandler('Error', 'Unsupported algorithm');
      }

      if (str_starts_with($algorithm, 'RS') || str_starts_with($algorithm, 'ES')) {
         if (!extension_loaded('openssl')) {
            throw new ExceptionHandler('Error', 'OpenSSL extension required');
         }
      }
   }

   private function jsonEncode(mixed $data): string {
      $json = json_encode($data);

      if (function_exists('json_last_error') && $errno = json_last_error()) {
         $this->jsonError($errno);
      } elseif ($json === 'null' && !is_null($data)) {
         throw new ExceptionHandler('Error', 'Null result with non-null input');
      }

      return $json;
   }

   private function jsonDecode(string $data): object {
      if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
         $obj = json_decode($data, false, 512, JSON_BIGINT_AS_STRING);
      } else {
         $max_int_length = strlen((string) PHP_INT_MAX) - 1;
         $json_without_bigints = preg_replace('/:\s*(-?\d{' . $max_int_length . ',})/', ': "$1"', $data);
         $obj = json_decode($json_without_bigints);
      }

      if (function_exists('json_last_error') && $errno = json_last_error()) {
         $this->jsonError($errno);
      } elseif ($obj === null && $data !== 'null') {
         throw new ExceptionHandler('Error', 'Null result with non-null input');
      }

      return $obj;
   }

   private function jsonError(int $errno): void {
      $messages = [
         JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
         JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
         JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
         JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
         JSON_ERROR_UTF8 => 'Malformed UTF-8 characters'
      ];

      throw new ExceptionHandler('Error', isset($messages[$errno]) ? $messages[$errno] : 'Unknown JSON error: ' . $errno);
   }

   private function base64UrlEncode(string $data): string {
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
   }

   private function base64UrlDecode(string $data): string {
      $data = strtr($data, '-_', '+/');
      $mod4 = strlen($data) % 4;
      if ($mod4) {
         $data .= str_repeat('=', 4 - $mod4);
      }
      return base64_decode($data);
   }
}
