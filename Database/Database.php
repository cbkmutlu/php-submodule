<?php

declare(strict_types=1);

namespace System\Database;

use PDO;
use PDOException;
use PDOStatement;
use System\Database\DatabaseException;

class Database {
   private ?PDO $pdo = null;
   private PDOStatement $state;
   private $query;
   private $total = 0;
   private $progress;
   private $prefix;
   private $positional;
   private $table;
   private $debug;

   public function connect(?string $connection = null): self {
      $config = import_config('defines.database');
      $attr = [
         PDO::ATTR_PERSISTENT => $config['persistent'],
         PDO::ATTR_EMULATE_PREPARES => $config['prepares'],
         PDO::ATTR_ERRMODE => $config['error_mode'],
         PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode'],
         PDO::ATTR_STRINGIFY_FETCHES => $config['stringify'],
         PDO::MYSQL_ATTR_FOUND_ROWS => $config['update_rows']
      ];
      $connection = is_null($connection) ? $config['default'] : $connection;
      $config = $config['connections'][$connection];

      if (!$this->prefix) {
         $this->prefix = $config['db_prefix'];
      }

      if ($config['db_driver'] === 'mysql' || $config['db_driver'] === 'pgsql') {
         $port = $config['db_port'] !== '' ? "port={$config['db_port']};" : '';
         $dsn = "{$config['db_driver']}:host={$config['db_host']};{$port}dbname={$config['db_name']}";
      } elseif ($config['db_driver'] === 'sqlite') {
         $dsn = "sqlite:{$config['db_name']}";
      } elseif ($config['db_driver'] === 'oracle') {
         $dsn = "oci:dbname={$config['db_host']}:{$config['db_port']}/{$config['db_service_name']}";
      } elseif ($config['db_driver'] === 'mssql') {
         $dsn = "sqlsrv:Server={$config['db_host']},{$config['db_port']};Database={$config['db_name']}";
      }

      try {
         $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $attr);
         $this->pdo->exec("SET NAMES '{$config['db_charset']}' COLLATE '{$config['db_collation']}'");
         $this->pdo->exec("SET CHARACTER SET '{$config['db_charset']}'");
         $this->pdo->exec("SET CHARACTER_SET_CONNECTION='{$config['db_charset']}'");
      } catch (PDOException $e) {
         throw new DatabaseException('Connection ' . $e->getMessage());
      }

      return $this;
   }

   public function pdo(): PDO {
      if (!$this->pdo) {
         $this->connect();
      }

      return $this->pdo;
   }

   public function debug(): self {
      $this->debug = true;

      return $this;
   }

   public function query(string $query, array $params = []): self {
      try {
         $this->state = $this->pdo()->prepare($query);
         $this->state->execute($params);
         $this->query = $query;
         $this->total++;

         return $this;
      } catch (PDOException $e) {
         throw new DatabaseException('Query ' . $e->getMessage());
      }
   }

   public function prepare(?string $query = null): self {
      if ($query) {
         $this->query = $query;
      }

      if ($this->debug) {
         print_r($this->query . "\n");
         $this->debug = false;
         exit();
      }

      try {
         $this->positional = false;
         $this->state = $this->pdo()->prepare($this->query);
         return $this;
      } catch (PDOException $e) {
         throw new DatabaseException('Prepare ' . $e->getMessage());
      }
   }

   public function execute(array $params = []): self {
      try {
         if ($this->positional) {
            $this->state->execute();
         } else {
            // $params = [
            //    'id' => 6,
            //    'image' => ['IS NOT NULL']
            // ];
            $filter = array_filter($params, function ($value) {
               return !is_array($value);
            });

            if ($this->debug) {
               print_r($params);
               print_r($filter);
               $this->debug = false;
               exit();
            }
            $this->state->execute($filter);
         }

         $this->total++;
         $this->positional = null;
         $this->table = null;
         return $this;
      } catch (PDOException $e) {
         throw new DatabaseException('Execute ' . $e->getMessage());
      }
   }

   public function bind(mixed $parameter, mixed $variable, mixed $data_type = PDO::PARAM_STR, $length = 0): self {
      try {
         $this->positional = true;

         if ($length) {
            $this->state->bindParam($parameter, $variable, $data_type, $length);
         } else {
            $this->state->bindParam($parameter, $variable, $data_type);
         }
         return $this;
      } catch (PDOException $e) {
         throw new DatabaseException('Bind ' . $e->getMessage());
      }
   }

   public function escape(string $data): string {
      try {
         return $this->pdo()->quote($data);
      } catch (PDOException $e) {
         throw new DatabaseException('Escape ' . $e->getMessage());
      }
   }

   public function transaction(): bool {
      try {
         if (!$this->progress++) {
            $this->pdo()->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
            return $this->pdo()->beginTransaction();
         }

         $this->pdo()->exec('SAVEPOINT trans' . $this->progress);
         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Transaction ' . $e->getMessage());
      }
   }

   public function commit(): bool {
      try {
         if (!--$this->progress) {
            return $this->pdo()->commit();
         }

         return $this->progress >= 0;
      } catch (PDOException $e) {
         throw new DatabaseException('Commit ' . $e->getMessage());
      }
   }

   public function rollback(): bool {
      try {
         if (--$this->progress) {
            $this->pdo()->exec('ROLLBACK TO trans' . ($this->progress + 1));
            return true;
         }

         return $this->pdo()->rollBack();
      } catch (PDOException $e) {
         throw new DatabaseException('Rollback ' . $e->getMessage());
      }
   }

   public function prefix(string $prefix): self {
      $this->prefix = $prefix;
      return $this;
   }

   public function fetchAll(?string $fetch = null, mixed $args = null, bool $all = true): mixed {
      try {
         if (!is_null($fetch)) {
            $mode = 'PDO::' . $fetch;
            if (!defined($mode)) {
               throw new DatabaseException("Invalid fetch mode: $mode");
            }

            $constant = constant($mode);

            if ($constant === PDO::FETCH_CLASS && $args) {
               $this->state->setFetchMode($constant, $args);
            } else {
               $this->state->setFetchMode($constant);
            }
         }

         return $all ? $this->state->fetchAll() : $this->state->fetch();
      } catch (PDOException $e) {
         throw new DatabaseException('Database Fetch Error: ' . $e->getMessage());
      }
   }

   public function fetch(?string $fetch = null, mixed $args = null): mixed {
      return $this->fetchAll($fetch, $args, false);
   }

   public function lastInsertId(): string {
      try {
         return $this->pdo()->lastInsertId();
      } catch (PDOException $e) {
         throw new DatabaseException('Get Last Id ' . $e->getMessage());
      }
   }

   public function lastInsertRow(?string $table = null): mixed {
      if (is_null($table)) {
         $table = $this->table;
      }

      $result = $this->query("SELECT * FROM {$this->prefix}{$table} WHERE id=" . $this->lastInsertId());
      return $result->fetch();
   }

   public function lastQuery(): string {
      return $this->query;
   }

   public function totalQuery(): int {
      return $this->total;
   }

   public function affectedRows(): int {
      try {
         return $this->state->rowCount();
      } catch (PDOException $e) {
         throw new DatabaseException('Get Affected Rows ' . $e->getMessage());
      }
   }

   public function __destruct() {
      if (isset($this->state)) {
         $this->state->closeCursor();
      }
      if ($this->pdo instanceof PDO) {
         $this->pdo = null;
      }
   }

   public function table(string $table): self {
      $this->pdo();
      $this->table = $table;

      return $this;
   }

   public function where(array $data): self {
      $conditions = [];

      foreach ($data as $key => $value) {
         if (is_array($value)) {
            if ($value[0] === 'IN') {
               if (is_array($value[1])) {
                  $conditions[] = "`{$key}` IN (" . implode(',', $value[1]) . ")";
               } else {
                  $conditions[] = "`{$key}` IN ({$this->escape($value[1])})";
               }
            } else {
               $conditions[] = "`{$key}` {$value[0]}";
            }
         } else {
            $conditions[] = "`{$key}` = :{$key}";
         }
      }

      $conditions = implode(' AND ', $conditions);
      if ($conditions) {
         $this->query .= " WHERE $conditions";
      }
      return $this;
   }

   public function select(array $data = ['*']): self {
      $select = rtrim(implode(', ', $data));
      $this->query = "SELECT {$select} FROM {$this->table}";
      return $this;
   }


   public function update(array $data): self {
      $clauses = [];

      foreach ($data as $key => $value) {
         if (is_int($key)) {
            $clauses[] = "`{$value}` = :{$value}";
         } else {
            $clauses[] = "`{$key}` = {$this->escape((string)$value)}";
         }
      }

      $clauses = implode(', ', $clauses);
      $this->query = "UPDATE {$this->table} SET {$clauses}";
      return $this;
   }

   public function updateCase(array $items, string $column, string $where): self {
      if (empty($items)) {
         throw new DatabaseException('Items array cannot be empty');
      }

      $cases = [];
      foreach ($items as $item) {
         $id = is_object($item) ? $item->{$where} : $item[$where];
         $value = is_object($item) ? $item->{$column} : $item[$column];

         $cases[] = "WHEN {$id} THEN {$this->escape((string)$value)}";
      }

      $cases = implode(' ', $cases);
      $this->query = "UPDATE {$this->table} SET `{$column}` = CASE `{$where}` {$cases} END";
      return $this;
   }

   public function insert(array $data): self {
      $columns = [];
      $values = [];

      foreach ($data as $key => $value) {
         if (is_int($key)) {
            $columns[] = "`{$value}`";
            $values[] = ":{$value}";
         } else {
            $columns[] = "`{$key}`";
            $values[] = "{$this->escape((string)$value)}";
         }
      }

      $columns = implode(', ', $columns);
      $values = implode(', ', $values);
      $this->query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
      return $this;
   }

   public function delete(): self {
      $this->query = "DELETE FROM {$this->table}";
      return $this;
   }
}
