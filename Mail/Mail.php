<?php

declare(strict_types=1);

namespace System\Mail;

use PHPMailer\PHPMailer\PHPMailer;

class Mail extends PHPMailer {
   private $isHtml;

   public function __construct() {
      parent::__construct();
      $config = config('defines.email');
      $this->isSMTP();
      $this->SMTPAuth = true;
      $this->isHTML(true);
      $this->Host = $config['server'];
      $this->Username = $config['username'];
      $this->Password = $config['userpass'];
      $this->Port = $config['port'];
      $this->CharSet = $config['charset'];
   }

   public function setHost(string $host): self {
      $this->Host = $host;
      return $this;
   }

   public function getHost(): string {
      return $this->Host;
   }

   public function setPort($port): self {
      $this->Port = $port;
      return $this;
   }

   public function getPort(): int {
      return $this->Port;
   }

   public function setUsername(string $username): self {
      $this->Username = $username;
      return $this;
   }

   public function getUsername(): string {
      return $this->Username;
   }

   public function setPassword(string $password): self {
      $this->Password = $password;
      return $this;
   }

   public function getPassword(): string {
      return $this->Password;
   }

   public function setCharset(string $charset): self {
      $this->CharSet = $charset;
      return $this;
   }

   public function getCharset(): string {
      return $this->CharSet;
   }

   public function setHtml(bool $html): self {
      $this->isHTML($html);
      $this->isHtml = $html;
      return $this;
   }

   public function getHtml(): bool {
      return $this->isHtml;
   }

   public function setSubject(string $subject): self {
      $this->Subject = $subject;
      return $this;
   }

   public function getSubject(): string {
      return $this->Subject;
   }

   public function setBody(string $body): self {
      $this->Body = $body;
      return $this;
   }

   public function getBody(): string {
      return $this->Body;
   }

   public function setAltBody($altBody): self {
      $this->AltBody = $altBody;
      return $this;
   }

   public function getAltBody(): string {
      return $this->AltBody;
   }

   public function getError(): string {
      return $this->ErrorInfo;
   }

   public function __call(string $method, array $args) {
      return call_user_func_array([new PHPMailer, $method], $args);
   }

   function __destruct() {
      parent::__destruct();
   }
}
