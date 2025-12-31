<?php

declare(strict_types=1);

namespace System\Cli;

class Cli {
   private $colors;

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

      try {
         $config = import_config('defines.app');
         import_env($config['env']);
      } catch (\Throwable $th) {
         print($this->error($th->getMessage()));
         exit();
      }
   }

   public function run(array $params): string {
      $command = $params[0];
      $param1 = $params[1] ?? null;
      $param2 = $params[2] ?? null;

      if ($command === 'serve') {
         return $this->serve($param1);
      } else if ($command === 'module') {
         return $this->module($param1);
      } else if ($command === 'hash' && $param1) {
         return $this->hash($param1);
      } else if ($command === 'key') {
         return $this->key();
      } else if ($command === 'migration' && $param1) {
         return $this->migration($param1, $param2);
      } else {
         return $this->help();
      }
   }

   private function help(): string {
      return
         $this->info('[hash]', 'light_blue') . "\t\t\t" . 'hash 123456' . "\n" .
         $this->info('[key]', 'light_blue') . "\t\t\t" . 'key' . "\n" .
         $this->info('[module]', 'light_blue') . "\t\t" . 'module User' . "\n" .
         $this->info('[migration create]', 'light_blue') . "\t" . 'migration create User/Migration' . "\n" .
         $this->info('[migration run]', 'light_blue') . "\n" .
         $this->info('[migration rollback]', 'light_blue') . "\n" .
         $this->info('[migration reset]', 'light_blue') . "\n" .
         $this->info('[migration refresh]', 'light_blue') . "\n";
   }

   private function serve(?string $port = null): string {
      $port = $port ?? 8000;
      $path = realpath(__DIR__ . '/../../Public');

      if (!$path) {
         throw new \RuntimeException('Public directory not found');
      }

      $command = sprintf('php -S 127.0.0.1:%d -t %s %s', $port, escapeshellarg($path), escapeshellarg($path . '/index.php'));
      return (string) shell_exec($command);
   }

   private function hash(string $value): string {
      $hash = password_hash($value, PASSWORD_ARGON2ID, ['cost' => 10]);

      if (!$hash) {
         return $this->error('Bcrypt hash not supported');
      }

      return $this->success('Hash: ' . $hash);
   }

   private function key(): string {
      $data = bin2hex(random_bytes(32));
      return $this->success('Key: ' . $data);
   }

   private function module(string $module): string {
      $path = 'App/Modules/' . $module;
      if (file_exists($path . '/' . $module . 'Controller.php')) {
         return $this->error('Module already exists: ' . $path);
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

   public function migration(string $param1, ?string $param2 = null): string {
      // refresh
      if ($param1 === 'refresh') {
         $this->migration('reset');
         return $this->migration('run');
      }

      // create
      if ($param1 === 'create') {
         if (is_string($param2) && preg_match('#^[A-Za-z_][A-Za-z0-9_]*\/[A-Za-z_][A-Za-z0-9_]*$#', $param2)) {
            [$module, $class] = explode('/', $param2);
            $location = 'App/Modules/' . $module . '/Migrations';
            $search = 'App/Modules/*/Migrations';
         } elseif (is_string($param2) && preg_match('#^[A-Za-z_][A-Za-z0-9_]*$#', $param2)) {
            $class = $param2;
            $location = 'App/Migrations';
            $search = 'App/Migrations';
         } else {
            return $this->error('Invalid migration command');
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
            return $this->info('Migration already exists: ' . $class);
         }

         $file = $location . '/' . $name . '.php';
         $template = file_get_contents('System/Cli/migration.temp');
         $content = str_replace('{class}', $class, $template);
         $this->dir($location);
         file_put_contents($file, $content);

         return $this->success('Migration successfully created: ' . $file);
      }

      // run, rollback, reset
      $json = 'App/Config/migration.json';
      if (!file_exists($json)) {
         file_put_contents($json, json_encode([], JSON_PRETTY_PRINT));
      }

      $location = 'App/Migrations';
      $migrations = json_decode(file_get_contents($json), true);
      $count = (count($migrations) > 0) ? max($migrations) : 0;
      $last = array_filter($migrations, function ($value) use ($count) {
         return $value === $count;
      });
      $migrate = false;

      foreach (glob(ROOT_DIR . $location . '/*.php') as $migration) {
         require_once $migration;

         $class = substr(basename($migration), 15, -4);
         if (!class_exists($class)) {
            continue;
         }

         $instance = new $class();

         try {
            if ($param1 === 'run') {
               if (!isset($migrations[$class])) {
                  $instance->up();
                  $migrations[$class] = $count + 1;
                  $migrate = true;
               }
            } else if ($param1 === 'rollback') {
               if (isset($last[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } else if ($param1 === 'reset') {
               if (isset($migrations[$class])) {
                  $instance->down();
                  unset($migrations[$class]);
                  $migrate = true;
               }
            } else {
               return $this->error('Invalid migration command');
            }
         } catch (\Exception $e) {
            return $this->error('Migration failed: ' . $e->getMessage());
         }
      }

      if ($migrate) {
         file_put_contents($json, json_encode($migrations, JSON_PRETTY_PRINT));
         return $this->info('Migration successfully completed');
      } else {
         return $this->error('No migration to run');
      }
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
