<?php

declare(strict_types=1);

namespace System\Csrf;

use System\Session\Session;

class Csrf {
   private $session;
   private $token = 'csrf_token';

   public function __construct(Session $session) {
      $this->session = $session;
   }

   /**
    * generate
    *
    * @return string
    */
   public function generate(): string {
      $this->session->save($this->token, base64_encode(openssl_random_pseudo_bytes(32)));
      return $this->session->read($this->token);
   }

   /**
    * validate
    *
    * @param string $token
    *
    * @return bool
    */
   public function validate(string $token): bool {
      if ($this->session->exist($this->token) && $token === $this->session->read($this->token)) {
         $this->session->delete($this->token);
         return true;
      }

      return false;
   }
}