<?php

declare(strict_types=1);

namespace System\Jwt;

use System\Jwt\JwtException;

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
   private $resolver;
   private $revoker;
   private $audience;
   private $issuer;

   public function __construct() {
      $config = import_config('defines.jwt');
      $this->secret = $config['secret'];
      $this->algorithm = $config['algorithm'];
      $this->leeway = $config['leeway'];
      $this->expire = $config['expire'];
   }

   public function createToken(array $payload, ?string $secret = null, ?string $algorithm = null, array $header = [], ?string $kid = null): string {
      if (is_null($secret)) {
         $secret = $this->secret;
      }

      if (is_null($algorithm)) {
         $algorithm = $this->algorithm;
      }

      $this->validateAlgorithm($algorithm);

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

      $headerEncoded = $this->base64Encode(json_encode($header, JSON_THROW_ON_ERROR));
      $payloadEncoded = $this->base64Encode(json_encode($payload, JSON_THROW_ON_ERROR));
      $signatureEncoded = $this->createSignature("$headerEncoded.$payloadEncoded", $secret, $algorithm);

      return "$headerEncoded.$payloadEncoded." . $this->base64Encode($signatureEncoded);
   }

   public function parseToken(?string $token = null, ?string $secret = null): array {
      if (is_null($token)) {
         throw new JwtException('Token not found or invalid', 401);
      }

      if (is_null($secret)) {
         $secret = $this->secret;
      }

      $parts = explode('.', $token);
      if (count($parts) !== 3) {
         throw new JwtException('Invalid token', 401);
      }

      [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
      try {
         $header = json_decode($this->base64Decode($headerEncoded), true, 512, JSON_THROW_ON_ERROR);
         $payload = json_decode($this->base64Decode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);
      } catch (\Exception $e) {
         throw new JwtException('Invalid token encoding', 403);
      }
      $signature = $this->base64Decode($signatureEncoded);

      $this->validateAlgorithm($header['alg']);

      if ($this->resolver) {
         return ($this->resolver)($header['kid'] ?? null);
      }

      if (!$this->verifySignature("$headerEncoded.$payloadEncoded", $signature, $secret, $header['alg'])) {
         throw new JwtException('Signature verification failed', 403);
      }

      $this->validateClaim($payload);

      if ($this->revoker && isset($payload['jti']) && ($this->revoker)($payload['jti'])) {
         throw new JwtException('Token revoked', 403);
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

         if (!$result) {
            throw new JwtException('OpenSSL unable to sign data', 500);
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

   private function validateClaim(array $payload): void {
      $timestamp = time();

      if (isset($payload['nbf']) && ($payload['nbf'] - $this->leeway) > $timestamp) {
         throw new JwtException('Token not yet valid', 401);
      }

      if (isset($payload['exp']) && ($timestamp + $this->leeway) >= $payload['exp']) {
         throw new JwtException('Token expired', 401);
      }

      if ($this->issuer && isset($payload['iss']) && $payload['iss'] !== $this->claims['iss']) {
         throw new JwtException('Invalid issuer', 401);
      }

      if ($this->audience && isset($payload['aud']) && $payload['aud'] !== $this->claims['aud']) {
         throw new JwtException('Invalid audience', 401);
      }

      if ($this->claims['jti']) {
         if (!isset($payload['jti'])) {
            throw new JwtException('Missing JTI in token', 401);
         }
         if ($payload['jti'] !== $this->claims['jti']) {
            throw new JwtException('Invalid token ID', 401);
         }
      }
   }

   private function validateAlgorithm(?string $algorithm = null): void {
      if (is_null($algorithm)) {
         throw new JwtException('Empty algorithm', 500);
      }

      if (!isset($this->algorithms[$algorithm])) {
         throw new JwtException('Unsupported algorithm', 500);
      }

      if (str_starts_with($algorithm, 'RS') || str_starts_with($algorithm, 'ES')) {
         if (!extension_loaded('openssl')) {
            throw new JwtException('OpenSSL extension required', 500);
         }
      }
   }

   private function base64Encode(string $data): string {
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
   }

   private function base64Decode(string $data): string {
      $data = strtr($data, '-_', '+/');
      $mod4 = strlen($data) % 4;
      if ($mod4) {
         $data .= str_repeat('=', 4 - $mod4);
      }
      return base64_decode($data);
   }
}
