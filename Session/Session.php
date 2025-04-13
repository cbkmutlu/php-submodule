<?php

declare(strict_types=1);

namespace System\Session;

class Session {
   private $config = [];

   public function __construct() {
      $this->config = config('defines.session');

      if ($this->config['cookie_httponly'] === true) {
         ini_set('session.cookie_httponly', 1);
      }

      if ($this->config['use_only_cookies'] === true) {
         ini_set('session.use_only_cookies', 1);
      }

      ini_set('session.cookie_samesite', $this->config['samesite']);
      ini_set('session.gc_maxlifetime', $this->config['lifetime']);
      ini_set('session.use_trans_sid', '0');
      ini_set('session.use_strict_mode', '1');
      session_set_cookie_params($this->config['lifetime']);

      $this->start($this->config['session_name']);
   }

   public function start(?string $name = null): void {
      if (!isset($_SESSION)) {
         session_start(['name' => $name]);
         $this->save('session_hash', $this->generateHash());
         $this->regenerate();
      } else {
         if (hash_equals($this->read('session_hash'), $this->generateHash()) === false) {
            $this->destroy();
         }
      }
   }

   public function destroy(): void {
      session_destroy();
   }

   public function save(string $name, mixed $data = null): void {
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

   public function read(?string $name): mixed {
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

   public function exist($name): bool {
      return isset($_SESSION[$name]);
   }

   public function flash(?string $data = null, ?string $url = null): mixed {
      if (!is_null($data)) {
         $flash = $this->save('flash', $data);

         if (!is_null($url)) {
            header("Location: $url");
         }

         return $flash;
      } else {
         if ($this->exist('flash')) {
            $flash = $this->read('flash');
            $this->delete('flash');

            return $flash;
         }

         return null;
      }
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

   private function generateHash(): string {
      if (array_key_exists('REMOTE_ADDR', $_SERVER) && array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
         return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] . $this->config['encryption_key'] . $_SERVER['HTTP_USER_AGENT'], $this->config['encryption_key']);
      }

      return hash_hmac('sha256', $this->config['encryption_key'], $this->config['encryption_key']);
   }
}
