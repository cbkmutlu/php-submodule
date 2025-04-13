<?php

declare(strict_types=1);

namespace System\Database;

use PDO;
use PDOStatement;
use PDOException;
use System\Exception\ExceptionHandler;

class Database {
   private ?PDO $pdo = null;
   private ?PDOStatement $state = null;
   private $query = null;
   private $total = 0;
   private $error = null;
   private $positional = false;
   private $progress = false;
   public $prefix = null;

   public function __construct() {
      $this->connect();
   }

   public function connect(?string $connection = null): self {
      $config = config('defines.database');
      $attr = [
         PDO::ATTR_PERSISTENT => $config['persistent'],
         PDO::ATTR_EMULATE_PREPARES => $config['prepares'],
         PDO::ATTR_ERRMODE => $config['error_mode'],
         PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode']
      ];
      $connection = is_null($connection) ? $config['default'] : $connection;
      $config = $config['connections'][$connection];
      $this->prefix = $config['db_prefix'];

      if ($config['db_driver'] === 'mysql' || $config['db_driver'] === 'pgsql' || $config['db_driver'] === '') {
         $dsn = $config['db_driver'] . ':host=' . $config['db_host'] . ';dbname=' . $config['db_name'];
      } elseif ($config['db_driver'] === 'sqlite') {
         $dsn = 'sqlite:' . $config['db_name'];
      } elseif ($config['db_driver'] === 'oracle') {
         $dsn = 'oci:dbname=' . $config['db_host'] . '/' . $config['db_name'];
      } elseif ($config['db_driver'] === 'mssql') {
         $dsn = 'sqlsrv:Server=' . $config['db_host'] . ';Database=' . $config['db_name'];
      }

      try {
         $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $attr);
         $this->pdo->exec("SET NAMES '" . $config['db_charset'] . "' COLLATE '" . $config['db_collation'] . "'");
         $this->pdo->exec("SET CHARACTER SET '" . $config['db_charset'] . "'");
         $this->pdo->exec("SET CHARACTER_SET_CONNECTION='" . $config['db_charset'] . "'");
      } catch (PDOException $e) {
         throw new ExceptionHandler('Connection Error: ', $e->getMessage());
      }

      return $this;
   }

   public function query($query, $params = array()): self {
      $this->state = $this->pdo->prepare($query);
      try {
         $this->state->execute($params);
         $this->query = $query;
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         if ($this->progress) {
            $this->rollback();
         }

         throw new ExceptionHandler('Query Error', $e->getMessage());
      }
   }

   public function execute($params = array()): self {
      try {
         if ($this->positional) {
            $this->state->execute();
         } else {
            $this->state->execute($params);
         }
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         if ($this->progress) {
            $this->rollback();
         }

         throw new ExceptionHandler('Execution Error', $e->getMessage());
      }
   }

   public function prepare($query): self {
      try {
         $this->positional = false;
         $this->state = $this->pdo->prepare($query);
         $this->query = $query;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Prepare Error', $e->getMessage());
      }
   }

   public function bind(mixed $parameter, mixed $variable, mixed $data_type = \PDO::PARAM_STR, $length = 0): self {
      try {
         $this->positional = true;

         if ($length) {
            $this->state->bindParam($parameter, $variable, $data_type, $length);
         } else {
            $this->state->bindParam($parameter, $variable, $data_type);
         }
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Bind Error', $e->getMessage());
      }
   }

   public function escape(string $data): string {
      try {
         return $this->pdo->quote($data);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Escape Error', $e->getMessage());
      }
   }

   public function transaction(): self {
      try {
         $this->pdo->beginTransaction();
         $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
         $this->progress = true;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Transaction Begin Error', $e->getMessage());
      }
   }

   public function commit(): self {
      try {
         $this->pdo->commit();
         $this->progress = false;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Transaction Commit Error', $e->getMessage());
      }
   }

   public function rollback(): self {
      try {
         $this->pdo->rollBack();
         $this->progress = false;
         return $this;
      } catch (PDOException $e) {
         throw new ExceptionHandler('Transaction Rollback Error', $e->getMessage());
      }
   }

   public function getAll(?int $fetch = null): mixed {
      try {
         if (is_null($fetch)) {
            return $this->state->fetchAll();
         }

         return $this->state->fetchAll($fetch);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Fetch All Error', $e->getMessage());
      }
   }

   public function getRow(?int $fetch = null): mixed {
      try {
         if (is_null($fetch)) {
            return $this->state->fetch();
         }

         return $this->state->fetch($fetch);
      } catch (PDOException $e) {
         throw new ExceptionHandler('Fetch All Error', $e->getMessage());
      }
   }

   public function getLastId(): string {
      try {
         return $this->pdo->lastInsertId();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Last Insert Id Error', $e->getMessage());
      }
   }

   public function getLastRow(string $table): mixed {
      try {
         $result = $this->query("SELECT MAX(id) FROM $table");
         return $result->getRow();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Last Row Error', $e->getMessage());
      }
   }

   public function getLastQuery(): string {
      return $this->query;
   }

   public function getTotalQuery(): int {
      return $this->total;
   }

   public function getAffectedRows(): int {
      try {
         return $this->state->rowCount();
      } catch (PDOException $e) {
         throw new ExceptionHandler('Affected Rows Error', $e->getMessage());
      }
   }

   public function getError(): void {
      if (!is_null($this->error)) {
         throw new ExceptionHandler('Error', $this->error);
      }
   }

   public function __destruct() {
      if ($this->state) {
         $this->state->closeCursor();
      }
      $this->pdo = null;
   }
}
