<?php

declare(strict_types=1);

namespace System\Database;

use System\Database\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

class Database {
   private ?PDO $pdo = null;
   private PDOStatement $state;
   private $query;
   private $total = 0;
   private $progress = 0;
   private $prefix;
   private $positional;
   private $table;
   private $debug;

   public function connect(?string $connection = null, bool $name = true): self {
      $config = import_config('defines.database');
      $attr = [
         PDO::ATTR_PERSISTENT         => $config['persistent'],
         PDO::ATTR_EMULATE_PREPARES   => $config['prepares'],
         PDO::ATTR_ERRMODE            => $config['error_mode'],
         PDO::ATTR_DEFAULT_FETCH_MODE => $config['fetch_mode'],
         PDO::ATTR_STRINGIFY_FETCHES  => $config['stringify'],
         PDO::MYSQL_ATTR_FOUND_ROWS   => $config['update_rows']
      ];
      $connection = $connection ?? $config['default'];
      $config = $config['connections'][$connection];

      if (!$this->prefix) {
         $this->prefix = $config['db_prefix'];
      }

      if ($config['db_driver'] === 'mysql' || $config['db_driver'] === 'pgsql') {
         $port = $config['db_port'] !== '' ? "port={$config['db_port']};" : '';
         $name = $name ? "dbname={$config['db_name']};" : '';
         $dsn = sprintf("%s:host=%s;%s%s", $config['db_driver'], $config['db_host'], $port, $name);
      } elseif ($config['db_driver'] === 'mssql') {
         $port = $config['db_port'] !== '' ? ",{$config['db_port']}" : '';
         $name = $name ? "Database={$config['db_name']}" : 'master';
         $dsn = sprintf("sqlsrv:Server=%s%s;%s", $config['db_host'], $port, $name);
      } elseif ($config['db_driver'] === 'sqlite') {
         $dsn = sprintf("sqlite:%s", $config['db_name']);
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

   public function pdo(bool $name = true): PDO {
      if (!$this->pdo) {
         $this->connect(null, $name);
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
            $filter = array_filter($params, function ($value) {
               return !is_array($value);
            });

            if ($this->debug) {
               echo "<pre>";
               print_r($this->query);
               echo "\n----------------------------------------\n";
               print_r($filter);
               echo "</pre>";
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

   public function escape(mixed $data): string {
      try {
         if ($data === null) {
            return 'NULL';
         }

         if (is_int($data) || is_float($data)) {
            return (string)$data;
         }

         if (is_bool($data)) {
            return $data ? '1' : '0';
         }

         return $this->pdo()->quote($data);
      } catch (PDOException $e) {
         throw new DatabaseException('Escape ' . $e->getMessage());
      }
   }

   public function transaction(): bool {
      try {
         if ($this->progress === 0) {
            $this->pdo()->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
            $this->pdo()->beginTransaction();
         } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->progress + 1));
         }

         $this->progress++;
         return true;
      } catch (PDOException $e) {
         $this->progress = 0;
         throw new DatabaseException('Transaction failed: ' . $e->getMessage());
      }
   }

   public function commit(): bool {
      try {
         if ($this->progress <= 0) {
            return false;
         }

         $this->progress--;
         if ($this->progress === 0) {
            return $this->pdo()->commit();
         }

         return true;
      } catch (PDOException $e) {
         $this->progress = 0;
         throw new DatabaseException('Commit failed: ' . $e->getMessage());
      }
   }

   public function rollback(): bool {
      try {
         if ($this->progress <= 0) {
            return false;
         }

         if ($this->progress > 1) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->progress);
         } else {
            $this->pdo()->rollBack();
         }

         $this->progress--;
         return true;
      } catch (PDOException $e) {
         $this->progress = 0;
         throw new DatabaseException('Rollback failed: ' . $e->getMessage());
      }
   }

   public function prefix(string $prefix): self {
      $this->prefix = $prefix;

      return $this;
   }

   public function fetchAll(): array {
      return $this->state->fetchAll();
   }

   public function fetchOne(): mixed {
      $result = $this->state->fetch();
      return $result === false ? null : $result;
   }

   public function fetchColumn(int $index = 0): mixed {
      $result = $this->state->fetchColumn($index);
      return $result === false ? null : $result;
   }

   public function lastInsertId(): int {
      return (int) $this->pdo()->lastInsertId();
   }

   public function lastInsertRow(?string $table = null, string $primaryKey = 'id'): mixed {
      $table = $table ?? $this->table;
      $result = $this->query("SELECT * FROM {$this->prefix}{$table} WHERE {$primaryKey}=" . $this->lastInsertId());

      return $result->fetchOne();
   }

   public function lastQuery(): string {
      return $this->query;
   }

   public function totalQuery(): int {
      return $this->total;
   }

   public function affectedRows(): int {
      return $this->state->rowCount();
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

      // table as alias
      if (preg_match('/^([a-zA-Z0-9_]+)\s+(?:AS\s+)?([a-zA-Z0-9_]+)$/i', $table, $matches)) {
         $this->table = "`{$matches[1]}` AS {$matches[2]}";
      }

      // table
      else {
         $this->table = "`{$table}`";
      }

      return $this;
   }

   public function select(array $data = ['*']): self {
      $select = rtrim(implode(', ', $data));
      $this->query = "SELECT {$select} FROM {$this->table}";
      return $this;
   }

   public function orderBy(?array $data = null): self {
      if ($data === null) {
         return $this;
      }

      $orderBy = [];
      foreach ($data as $key => $value) {
         // (column = value)
         if (preg_match('/^\((\w+)\s*=\s*(\d+)\)$/', $key, $matches)) {
            $col = $matches[1];
            $num = $matches[2];
            $orderBy[] = "(`{$col}` = {$num})";
            continue;
         }

         // column value
         $orderBy[] = "`{$key}` {$value}";
      }
      $this->query .= " ORDER BY " . implode(', ', $orderBy);

      return $this;
   }

   public function where(array $data): self {
      $conditions = [];

      foreach ($data as $key => $value) {
         // key => [value]
         if (is_array($value)) {

            // key => ['IN', value]
            if ($value[0] === 'IN') {

               // key => ['IN', [value]]
               if (is_array($value[1])) {
                  $columns = implode(',', $value[1]);
                  $conditions[] = "`{$key}` IN ({$columns})";
               }

               // key => ['IN', value]
               else {
                  $conditions[] = "`{$key}` IN ({$value[1]})";
               }
            }

            // key => ['IS NULL']
            else {
               $conditions[] = "`{$key}` {$value[0]}";
            }
         }

         // key => value
         else {
            $conditions[] = "`{$key}` = :{$key}";
         }
      }

      $conditions = implode(' AND ', $conditions);
      if ($conditions) {
         $this->query .= " WHERE $conditions";
      }
      return $this;
   }

   public function update(array $data): self {
      $clauses = [];
      foreach ($data as $key => $value) {
         // key => [value]
         if (is_array($value)) {

            // key => ['CASE', columns, rows]
            if (is_string($value[0]) && $value[0] === 'CASE') {
               $cases = [];
               foreach ($value[2] as $row) {
                  $cases[] = "WHEN {$this->escape($row[$value[1]])} THEN {$this->escape($row[$key])}";
               }

               $cases = implode(' ', $cases);
               $clauses[] = "`{$key}` = CASE `{$value[1]}` {$cases} END";
            }

            // key => ['NOW()'], key => ['NOW(3)']
            elseif (is_string($value[0]) && preg_match('/^NOW\((?:[0-6])?\)$/i', $value[0])) {
               $clauses[] = "`{$key}` = {$value[0]}";
            }

            // key => [value]
            else {
               $clauses[] = "`{$key}` = {$this->escape($value[0])}";
            }
         }

         // key
         elseif (is_int($key)) {
            $clauses[] = "`{$value}` = :{$value}";
         }

         // key => value
         else {
            $clauses[] = "`{$key}` = :{$key}";
         }
      }

      $clauses = implode(', ', $clauses);
      $this->query = "UPDATE {$this->table} SET {$clauses}";
      return $this;
   }

   public function insert(array $data): self {
      // key => [[value], [value]]
      if (array_is_list($data)) {
         $columns = '`' . implode('`, `', array_keys(reset($data))) . '`';
         $values = [];
         foreach ($data as $row) {
            $escape = [];
            foreach ($row as $key => $value) {
               $escape[] = $this->escape($value);
            }
            $values[] = '(' . implode(', ', $escape) . ')';
         }

         $values = implode(', ', $values);
      }

      // key => [value]
      else {
         $columns = '`' . implode('`, `', array_keys($data)) . '`';
         $values = [];
         foreach ($data as $key => $value) {
            // key => [value]
            if (is_array($value)) {

               // key => ['NOW()'], key => ['NOW(3)']
               if (is_string($value[0]) && preg_match('/^NOW\((?:[0-6])?\)$/i', $value[0])) {
                  $values[] = $value[0];
               }

               // key => [value]
               else {
                  $values[] = $this->escape($value[0]);
               }
            }

            // key => value
            else {
               $values[] = ":{$key}";
            }
         }
         $values = '(' . implode(', ', $values) . ')';
      }

      $this->query = "INSERT INTO {$this->table} ({$columns}) VALUES {$values}";
      return $this;
   }

   public function delete(): self {
      $this->query = "DELETE FROM {$this->table}";
      return $this;
   }
}
