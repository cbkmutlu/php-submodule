<?php

declare(strict_types=1);

namespace System\Migration;

use System\Database\Database;

class Migration {
   protected $database;

   public function __construct() {
      $this->database = new Database();
   }

   public function up() {
   }

   public function down() {
   }
}
