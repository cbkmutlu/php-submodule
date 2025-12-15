<?php

declare(strict_types=1);

namespace System\Session;

class Session {
   private $config;

   public function __construct() {
      $this->config = import_config('defines.session');

      if ($this->config['cookie_httponly']) {
         ini_set('session.cookie_httponly', 1);
      }

      if ($this->config['use_only_cookies']) {
         ini_set('session.use_only_cookies', 1);
      }

      ini_set('session.cookie_samesite', $this->config['samesite']);
      ini_set('session.gc_maxlifetime', $this->config['lifetime']);
      ini_set('session.use_trans_sid', '0');
      ini_set('session.use_strict_mode', '1');
      session_set_cookie_params($this->config['lifetime']);
      $this->start();
   }

   public function start(?string $name = null): void {
      if ($this->status()) {
         if (!hash_equals($this->read('session_hash'), $this->generateHash())) {
            $this->destroy();
         }
      } else {
         if (is_null($name)) {
            $name = $this->config['session_name'];
         }

         session_start(['name' => $name]);
         $this->save('session_hash', $this->generateHash());
         $this->regenerate();
      }
   }

   public function destroy(): void {
      session_destroy();
   }

   public function save(string|array $name, mixed $data): void {
      if (is_array($name)) {
         foreach ($name as $key => $value) {
            if (is_int($key)) {
               $_SESSION[$value] = null;
            } else {
               $_SESSION[$key] = $value;
            }
         }
      } else {
         $_SESSION[$name] = $data;
      }
   }

   public function push(string $name, array $data): void {
      if ($this->exist($name) && is_array($this->read($name))) {
         $this->save($name, array_merge($this->read($name), $data));
      }
   }

   public function read(?string $name = null): mixed {
      if (is_null($name)) {
         return $_SESSION;
      }

      return $_SESSION[$name];
   }

   public function delete(string $name): void {
      if ($this->exist($name)) {
         unset($_SESSION[$name]);
      }
   }

   public function exist(string $name): bool {
      return isset($_SESSION[$name]);
   }

   public function flash(?string $data = null, ?string $url = null): ?string {
      if (is_null($data)) {
         if ($this->exist('session_flash')) {
            $flash = $this->read('session_flash');
            $this->delete('session_flash');

            return $flash;
         }
      } else {
         $this->save('session_flash', $data);

         if (!is_null($url)) {
            url_redirect($url);
         }

         return $this->read('session_flash');
      }

      return null;
   }

   public function status(): bool {
      return session_status() === PHP_SESSION_ACTIVE;
   }

   public function regenerate(): void {
      if ($this->status()) {
         $this->save('session_regenerate', time());
         session_regenerate_id(true);
      }
   }

   public function csrf(?string $token = null): bool|string {
      if (is_null($token)) {
         if ($this->status()) {
            $token = bin2hex(random_bytes(32));
            $this->save('session_csrf', $token);
            return $token;
         }
         return false;
      }

      if ($this->exist('session_csrf') && hash_equals($this->read('session_csrf'), $token)) {
         $this->delete('session_csrf');
         return true;
      }

      return false;
   }

   private function generateHash(): string {
      if (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['HTTP_USER_AGENT'])) {
         return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] . $this->config['encryption_key'] . $_SERVER['HTTP_USER_AGENT'], $this->config['encryption_key']);
      }

      return hash_hmac('sha256', $this->config['encryption_key'], $this->config['encryption_key']);
   }
}
