<?php

declare(strict_types=1);

namespace System\Cli;

class Cli {
   private $params;
   private $colors = [];

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
   }

   /**
    * run
    *
    * @param array $params
    *
    * @return string
    */
   public function run(array $params): string {
      $this->params = $params;

      if (!$this->params) {
         return $this->help();
      } else if ($params[0] === 'serve') {
         print_r(getcwd());
         $oldPath = getcwd();
         chdir(getcwd());
         $output = shell_exec('php -S 127.0.0.1:8000');
         chdir($oldPath);
         print_r($output);
      } else {
         if (isset($params[1])) {
            switch ($params[0]) {
               case 'controller':
                  return $this->createController($params[1]);
               case 'model':
                  return $this->createModel($params[1]);
               case 'middleware':
                  return $this->createMiddleware($params[1]);
               case 'listener':
                  return $this->createListener($params[1]);
               case 'migration':
                  return $this->createMigration($params[1]);
               case 'migrate':
                  return $this->migrate($params[1]);
               default:
                  return $this->help();
            }
         } else {
            if ($params[0] === 'key') {
               return $this->createKey();
            }
            return $this->error('Geçersiz komut: ' . $params[0]);
         }
      }
   }

   /**
    * help
    *
    * @return string
    */
   private function help(): string {
      return $this->info('[controller]', 'light_blue') . "\t\t" . 'Controller oluşturur' . "\t" . $this->info('( controller User/Register )') . "\n" .
         $this->info('[model]', 'light_blue') . "\t\t\t" . 'Model oluşturur' . "\t\t" . $this->info('( model User/Register )') . "\n" .
         $this->info('[middleware]', 'light_blue') . "\t\t" . 'Middleware oluşturur' . "\t" . $this->info('( middleware MyMiddleware )') . "\n" .
         $this->info('[listener]', 'light_blue') . "\t\t" . 'Listener oluşturur' . "\t" . $this->info('( listener MyListener )') . "\n" .
         $this->info('[key]', 'light_blue') . "\t\t\t" . '256bit key oluşturur' . "\n" .
         $this->info('[migration]', 'light_blue') . "\t\t" . 'Migration oluşturur' . "\t" . $this->info('( migration MyMigration )') . "\n" .
         $this->info('[migrate --run]', 'light_blue') . "\t\t" . 'Oluşan Migrationları çalıştırır' . "\n" .
         $this->info('[migrate --rollback]', 'light_blue') . "\t" . 'Son çalıştırılan Migrationu geri alır' . "\n" .
         $this->info('[migrate --reset]', 'light_blue') . "\t" . 'Tüm Migrationları temizler' . "\n" .
         $this->info('[migrate --refresh]', 'light_blue') . "\t" . 'Tüm Migrationları yeniler' . "\n";
   }

   private function createKey(): string {
      $key = openssl_random_pseudo_bytes(32);
      return $this->success('Key oluşturuldu: ' . base64_encode($key));
   }

   /**
    * createController
    *
    * @param string $controller
    *
    * @return string
    */
   private function createController(string $controller): string {
      if (strpos($controller, '/') === false) {
         return $this->error('Geçersiz komut: ' . $controller);
      }

      [$module, $class] = explode('/', $controller);
      $location = "App/Modules/$module/Controllers";
      $file = "$location/$class.php";
      $content = <<<PHP
<?php

namespace App\Modules\\$module\Controllers;

use System\Controller\Controller;

class $class extends Controller {
   public function index() {
   }
}

PHP;

      if (file_exists($file)) {
         return $this->error('Controller zaten mevcut: ' . $file);
      }

      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Controller başarıyla oluşturuldu: ' . $location);
   }

   /**
    * createModel
    *
    * @param string $model
    *
    * @return string
    */
   private function createModel(string $model): string {
      if (strpos($model, '/') === false) {
         return $this->error('Geçersiz komut: ' . $model);
      }

      [$module, $class] = explode('/', $model);
      $location = "App/Modules/$module/Models";
      $file = "$location/$class.php";
      $content = <<<PHP
<?php

namespace App\Modules\\$module\Models;

use System\Model\Model;

class $class extends Model {
   public function index() {
   }
}

PHP;

      if (file_exists($file)) {
         return $this->error('Model zaten mevcut: ' . $file);
      }

      $this->dir($location);
      file_put_contents($file, $content);
      return $this->success('Model başarıyla oluşturuldu: ' . $location);
   }

   /**
    * createMiddleware
    *
    * @param string $middleware
    *
    * @return string
    */
   private function createMiddleware(string $middleware): string {
      $file = "App/Middlewares/$middleware.php";
      $content = <<<PHP
<?php

namespace App\Middlewares;

class $middleware {
   public function handle() {
   }
}

PHP;

      if (file_exists($file)) {
         return $this->info('Middleware zaten mevcut: ' . $file);
      }

      file_put_contents($file, $content);
      return $this->success('Middleware başarıyla oluşturuldu: ' . $file);
   }

   /**
    * createListener
    *
    * @param string $listener
    *
    * @return string
    */
   private function createListener(string $listener): string {
      $file = "App/Listeners/$listener.php";
      $content = <<<PHP
<?php

namespace App\Listeners;

class $listener {
   public function handle() {
   }
}

PHP;

      if (file_exists($file)) {
         return $this->info('Listener zaten mevcut: ' . $file);
      }

      file_put_contents($file, $content);
      return $this->success('Listener başarıyla oluşturuldu: ' . $file);
   }

   /**
    * createMigration
    *
    * @param string $migration
    *
    * @return string
    */
   private function createMigration(string $migration): string {
      $location = "App/Migrations";
      $class = str_replace('_', ' ', $migration);
      $class = str_replace(' ', '', strtolower($class));
      $name =  date('Y_m_d_His') . '_' . $class;
      $file = $location . '/' . $name . '.php';
      $content = <<<PHP
<?php

use System\Migration\Migration;

class $class extends Migration {
   public function up() {
      \$this->database->query("CREATE TABLE IF NOT EXISTS $class (
         `id` INT AUTO_INCREMENT PRIMARY KEY,
         `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )");
   }

   public function down() {
      \$this->database->query("DROP TABLE IF EXISTS $class");
   }
}

PHP;


      foreach (scandir($location) as $migration) {
         if (is_file($location . '/' . $migration) && str_ends_with($migration, '.php')) {
            require_once $location . '/' . $migration;
         }
      }

      if (class_exists($class)) {
         return $this->info('Migration zaten mevcut: ' . $class);
      }

      file_put_contents($file, $content);
      return $this->success('Migration başarıyla oluşturuldu: ' . $file);
   }

   /**
    * migrate
    *
    * @param mixed $param
    *
    * @return string
    */
   public function migrate($param): string {
      $json = "App/Migrations/migration.json";
      if (!file_exists($json)) {
         file_put_contents($json, json_encode([], JSON_PRETTY_PRINT));
      }

      $location = "App/Migrations";
      $migrations = json_decode(file_get_contents($json), true);
      $maxValue = (count($migrations) > 0) ? max($migrations) : 0;
      $maxKeys = array_filter($migrations, fn($value) => $value === $maxValue);
      $migrate = false;

      foreach (scandir($location) as $file) {
         if (is_file($location . '/' . $file) && str_ends_with($file, '.php')) {
            require_once $location . '/' . $file;
            $class = substr($file, 18, -4);

            if (class_exists($class)) {
               $instance = new $class();

               if ($param === '--run') {
                  if (!array_key_exists($class, $migrations)) {
                     $instance->up();
                     $migrations[$class] = $maxValue + 1;
                     $migrate = true;
                  }
               } else if ($param === '--rollback') {
                  if (array_key_exists($class, $maxKeys)) {
                     $instance->down();
                     unset($migrations[$class]);
                     $migrate = true;
                  }
               } else if ($param === '--reset') {
                  if (array_key_exists($class, $migrations)) {
                     $instance->down();
                     unset($migrations[$class]);
                     $migrate = true;
                  }
               } else if ($param === '--refresh') {
                  $this->migrate('--reset');
                  return $this->migrate('--run');
               } else {
                  return $this->error('Geçersiz komut: ' . $param);
               }
            }
         }
      }

      if ($migrate) {
         file_put_contents($json, json_encode($migrations, JSON_PRETTY_PRINT));
         return $this->info('Migrate başarıyla yapıldı');
      } else {
         return $this->error('Migrate yapılacak dosya yok');
      }
   }

   /**
    * success
    *
    * @param string $message
    *
    * @return string
    */
   private function success(string $message): string {
      return $this->write($message, 'light_green');
   }

   /**
    * error
    *
    * @param string $message
    *
    * @return string
    */
   private function error(string $message): string {
      return $this->write($message, 'light_red');
   }

   /**
    * info
    *
    * @param string $message
    *
    * @return string
    */
   private function info(string $message): string {
      return $this->write($message, 'light_blue');
   }

   /**
    * write
    *
    * @param string $string
    * @param null $color
    *
    * @return string
    */
   private function write(string $string, $color = null): string {
      $colored_string = "";

      if (isset($this->colors[$color])) {
         $colored_string .= "\e[" . $this->colors[$color] . "m";
      }

      $colored_string .= $string . "\e[0m";

      return $colored_string;
   }

   /**
    * dir
    *
    * @param string $path
    * @param int $permissions
    *
    * @return bool
    */
   private function dir(string $path, int $permissions = 0755): bool {
      return is_dir($path) || mkdir($path, $permissions, true);
   }
}
