<?php

declare(strict_types=1);

namespace System\Cli;

use Exception;
use System\Database\Database;

class Cli {
   private $colors;
   private $config;
   private Database $database;

   public function __construct() {
      $this->colors['black']         = '0;30';
      $this->colors['dark_gray']     = '1;30';
      $this->colors['blue']          = '0;34';
      $this->colors['light_blue']    = '1;34';
      $this->colors['green']         = '0;32';
      $this->colors['light_green']   = '1;32';
      $this->colors['cyan']          = '0;36';
      $this->colors['light_cyan']    = '1;36';
      $this->colors['red']           = '0;31';
      $this->colors['light_red']     = '1;31';
      $this->colors['purple']        = '0;35';
      $this->colors['light_purple']  = '1;35';
      $this->colors['brown']         = '0;33';
      $this->colors['yellow']        = '1;33';
      $this->colors['light_gray']    = '0;37';
      $this->colors['white']         = '1;37';
      $this->config = import_config('defines.app');
      $this->database = new Database();
   }

   public function run(array $params): string {
      [$isProduction, $params] = $this->parseArgs($params);

      if (!$isProduction) {
         putenv('APP_ENV=development');
      }

      $command = $params[0] ?? null;
      $param1  = $params[1] ?? null;
      $param2  = $params[2] ?? null;

      if ($command === 'serve') {
         import_env($this->config['env']);
         return $this->serve($param1);
      } elseif ($command === 'module') {
         return $this->module($param1);
      } elseif ($command === 'hash' && $param1) {
         return $this->hash($param1);
      } elseif ($command === 'key') {
         return $this->key();
      } elseif (($command === 'migration' || $command === 'mg') && $param1) {
         import_env($this->config['env']);
         return $this->migration($param1, $param2);
      } elseif (($command === 'database' || $command === 'db') && $param1) {
         import_env($this->config['env']);
         return $this->database($param1);
      } else {
         return $this->help();
      }
   }

   private function parseArgs(array $params): array {
      $flags = [
         '-p',
         '-prod',
         '-production',
      ];

      $env = null;
      $clean = [];

      foreach ($params as $param) {
         if (in_array($param, $flags, true)) {
            $env = 'production';
            continue;
         }

         $clean[] = $param;
      }
      return [$env, $clean];
   }

   private function help(): string {
      return
         $this->info('serve [host]:[port]', 'light_blue') . "\t\t" . 'Start the development server (host and port are optional, default host: 127.0.0.1, default port: 8000)' . "\n" .
         $this->info('hash [value]', 'light_blue') . "\t\t\t" . 'Hash the given value' . "\n" .
         $this->info('key', 'light_blue') . "\t\t\t\t" . 'Generate a random encryption (secret) key' . "\n" .
         $this->info('module [name]', 'light_blue') . "\t\t\t" . 'Create a new module' . "\n\n" .
         $this->info('migration create [name]', 'light_blue') . "\t\t" . 'Create a new migration' . "\t\t" . '(mg create module/name for module migrations)' . "\n" .
         $this->info('migration run', 'light_blue') . "\t\t\t" . 'Run all pending migrations' . "\t" . '(mg run -p for production)' . "\n" .
         $this->info('migration rollback', 'light_blue') . "\t\t" . 'Rollback the last migration' . "\t" . '(mg rollback -p for production)' . "\n" .
         $this->info('migration reset', 'light_blue') . "\t\t\t" . 'Rollback all migrations' . "\t\t" . '(mg reset -p for production)' . "\n" .
         $this->info('migration refresh', 'light_blue') . "\t\t" . 'Reset and run all migrations' . "\t" . '(mg refresh -p for production)' . "\n" .
         $this->info('migration clear', 'light_blue') . "\t\t\t" . 'Clear migration.json file' . "\n\n" .
         $this->info('database create', 'light_blue') . "\t\t\t" . 'Create database from .env' . "\t" . '(db create -p for production)' . "\n" .
         $this->info('database drop', 'light_blue') . "\t\t\t" . 'Drop database if exists' . "\t\t" . '(db drop -p for production)' . "\n" .
         $this->info('database refresh', 'light_blue') . "\t\t" . 'Drop and create database' . "\t" . '(db refresh -p for production)' . "\n" .
         $this->info('database seed', 'light_blue') . "\t\t\t" . 'Run database seeders' . "\t\t" . '(db seed -p for production)' . "\n";
   }

   private function serve(?string $host = null): string {
      $defaultIp = '127.0.0.1';
      $defaultPort = 8000;
      $ip = $defaultIp;
      $port = $defaultPort;

      if ($host) {
         if (str_contains($host, ':')) {
            [$ip, $port] = explode(':', $host, 2);
            $port = (int) $port ?: $defaultPort;
         } else {
            $ip = $host;
         }
      }

      $path = realpath(__DIR__ . '/../../Public');
      if (!$path) {
         return $this->error('✗ Public directory not found');
      }

      $command = sprintf(
         'php -S %s:%d -t %s %s',
         escapeshellarg($ip),
         $port,
         escapeshellarg($path),
         escapeshellarg($path . '/index.php')
      );

      return (string) shell_exec($command);
   }

   private function hash(string $value): string {
      $hash = password_hash($value, PASSWORD_ARGON2ID, ['cost' => 10]);

      if (!$hash) {
         return $this->error('✗ Bcrypt hash not supported');
      }

      return $this->success('Hash: ' . $hash);
   }

   private function key(): string {
      $data = bin2hex(random_bytes(32));
      return $this->success('Key: ' . $data);
   }

   private function module(string $module): string {
      $path = 'App/Modules/' . $module;
      if (is_file($path . '/' . $module . 'Controller.php')) {
         return $this->error('✗ Module already exists: ' . $path);
      }

      $this->dir($path);
      $list = ['Controller', 'Service', 'Repository', 'Request', 'Response'];
      foreach ($list as $item) {
         $template = file_get_contents('System/Cli/' . $item . '.temp');
         $content = str_replace('{class}', $module, $template);
         file_put_contents($path . '/' . $module . $item . '.php', $content);
      }

      return $this->success('Module successfully created: ' . $path);
   }

   private function migration(string $param1, ?string $param2 = null): string {
      // create
      if ($param1 === 'create') {
         // module migration
         // php cli migration create Module1/User
         // App/Modules/Module1/Migrations/2022_01_01_001_user.php
         if (is_string($param2) && preg_match('#^[A-Za-z_][A-Za-z0-9_]*\/[A-Za-z_][A-Za-z0-9_]*$#', $param2)) {
            [$module, $class] = explode('/', $param2, 2);
            $path = 'App/Modules/' . $module . '/Migrations';
            $search = 'App/Modules/*/Migrations';
         }
         // default migration
         // php cli migration create User
         // App/Migrations/2022_01_01_001_user.php
         elseif (is_string($param2) && preg_match('#^[A-Za-z_][A-Za-z0-9_]*$#', $param2)) {
            $class = $param2;
            $path = 'App/Migrations';
            $search = 'App/Migrations';
         }
         // invalid command
         else {
            return $this->error('✗ Invalid migration command');
         }

         $prefix = date('Y_m_d');
         $max = 0;
         foreach (glob(ROOT_DIR . $search . '/*.php') as $migration) {
            $filename = basename($migration);
            require_once $migration;
            if (preg_match('/^' . $prefix . '_(\d{3})_/', $filename, $matches)) {
               $num = (int) $matches[1];
               if ($num > $max) {
                  $max = $num;
               }
            }
         }

         $name = $prefix . '_' . sprintf('%03d', $max + 1) . '_' . $class;

         if (class_exists($class)) {
            return $this->error('✗ Migration already exists: ' . $class);
         }

         $file = $path . '/' . $name . '.php';
         $template = file_get_contents('System/Cli/migration.temp');
         $content = str_replace('{class}', $class, $template);
         $this->dir($path);
         file_put_contents($file, $content);

         return $this->success('✓ Migration successfully created: ' . $file);
      } elseif ($param1 === 'clear') {
         $json = 'App/Config/migration.json';
         file_put_contents($json, json_encode([], JSON_PRETTY_PRINT));

         return $this->success('✓ Migration file cleared: ' . $json);
      }

      // run, rollback, reset, refresh
      $path = $this->config['migrations'];
      $files = glob(ROOT_DIR . $path . '/*.php');
      if (empty($files)) {
         return $this->error('✗ No migration files found');
      }

      $json = 'App/Config/migration.json';
      if (!is_file($json)) {
         file_put_contents($json, json_encode([], JSON_PRETTY_PRINT));
      }

      $migrations = json_decode(file_get_contents($json), true);
      $count = (count($migrations) > 0) ? max($migrations) : 0;
      $last = array_filter($migrations, function ($value) use ($count) {
         return $value === $count;
      });
      $migrate = false;

      foreach ($files as $migration) {
         try {
            $this->database->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
            require_once $migration;

            $class = substr(basename($migration), 15, -4);
            if (!class_exists($class)) {
               continue;
            }

            $instance = new $class($this->database);

            if ($param1 === 'run') {
               if (!isset($migrations[$class])) {
                  $instance->up();
                  $migrations[$class] = $count + 1;
                  $migrate = true;
               }
            } elseif ($param1 === 'rollback') {
               if (isset($last[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } elseif ($param1 === 'reset') {
               if (isset($migrations[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } elseif ($param1 === 'refresh') {
               if (isset($migrations[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
               }
               if (!isset($migrations[$class])) {
                  $instance->up();
                  $migrations[$class] = $count + 1;
               }
               $migrate = true;
            } else {
               return $this->error('✗ Invalid migration command');
            }
         } catch (Exception $e) {
            return $this->error('✗ Migration failed [' . $class . ']: ' . $e->getMessage());
         } finally {
            $this->database->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
         }
      }

      if ($migrate) {
         file_put_contents($json, json_encode($migrations, JSON_PRETTY_PRINT));
         return $this->success('✓ Migration successfully completed');
      } else {
         return $this->info('No valid migrations executed');
      }
   }

   private function database(string $param1): string {
      $config = import_config('defines.database');
      $connection = $config['connections'][$config['default']];
      $name = $connection['db_name'];
      $collation = $connection['db_collation'];
      $charset = $connection['db_charset'];

      try {
         if ($param1 === 'create') {
            $this->database->pdo(false)->exec("CREATE DATABASE IF NOT EXISTS `{$name}` COLLATE `{$collation}` DEFAULT CHARACTER SET `{$charset}`");
            return $this->success('✓ Database successfully created: ' . $name);
         } elseif ($param1 === 'drop') {
            $this->database->pdo(false)->exec("DROP DATABASE IF EXISTS `{$name}`");
            return $this->success('✓ Database successfully dropped: ' . $name);
         } elseif ($param1 === 'refresh') {
            $this->database->pdo(false)->exec("DROP DATABASE IF EXISTS `{$name}`");
            $this->database->pdo(false)->exec("CREATE DATABASE IF NOT EXISTS `{$name}` COLLATE `{$collation}` DEFAULT CHARACTER SET `{$charset}`");
            return $this->success('✓ Database successfully refreshed: ' . $name);
         } elseif ($param1 === 'seed') {
            return $this->seed();
         }
      } catch (Exception $e) {
         return $this->error('✗ Database command failed: ' . $e->getMessage());
      }

      return $this->error('✗ Invalid database command');
   }

   private function seed(): string {
      $path = $this->config['seeds'];
      $files = glob(ROOT_DIR . $path . '/*.php');

      if (empty($files)) {
         return $this->error('✗ No seeders found');
      }

      $count = 0;
      foreach ($files as $file) {
         require_once $file;
         $className = basename($file, '.php');
         $class = 'App\\Seeds\\' . $className;

         if (class_exists($class)) {
            $seeder = new $class($this->database);
            if (method_exists($seeder, 'run')) {
               echo $this->info($className . "\n");
               $seeder->run();
               $count++;
            }
         }
      }

      if ($count === 0) {
         return $this->info('No valid seeders executed');
      }

      return $this->success('✓ Database seeding completed (' . $count . ' seeders)');
   }

   private function success(string $message): string {
      return $this->write($message, 'light_green');
   }

   private function error(string $message): string {
      return $this->write($message, 'light_red');
   }

   private function info(string $message): string {
      return $this->write($message, 'light_blue');
   }

   private function write(string $string, ?string $color = null): string {
      $colored_string = '';

      if (isset($this->colors[$color])) {
         $colored_string .= "\e[" . $this->colors[$color] . 'm';
      }

      $colored_string .= $string . "\e[0m";
      return $colored_string;
   }

   private function dir(string $path, int $permissions = 0755): bool {
      return is_dir($path) || mkdir($path, $permissions, true);
   }
}
