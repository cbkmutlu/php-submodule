<?php

declare(strict_types=1);

namespace System\Exception;

use Throwable;
use ErrorException;

use System\Http\Response;

use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler as WhoopsPrettyPageHandler;

class ExceptionHandler {
   private static Response $response;

   public function __construct(
      Response $response
   ) {
      if (get_env('DEVELOPMENT')) {
         error_reporting(E_ALL);
         ini_set('display_errors', 1);
         ini_set('display_startup_errors', 1);
      } else {
         error_reporting(0);
         ini_set('display_errors', 0);
         ini_set('display_startup_errors', 0);
      }
      self::$response = $response;
   }

   public static function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
      $report = error_reporting();
      if ($report & $errno) {
         $exit = false;
         switch ($errno) {
            // Fatal
            case E_ERROR:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
               $type = 'Fatal Error';
               $exit = true;
               break;

            // Warnings
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
               $type = 'Warning';
               break;

            // Notices
            case E_NOTICE:
            case E_USER_NOTICE:
               $type = 'Notice';
               break;

            // Deprecated
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
               $type = 'Deprecated';
               break;

            // Recoverable
            case E_RECOVERABLE_ERROR:
               $type = 'Catchable';
               break;

            default:
               $type = 'Unknown Error';
               break;
         }

         $exception = new ErrorException($type . ': ' . $errstr, 0, $errno, $errfile, $errline);

         if ($exit) {
            exit();
         } else {
            throw $exception;
         }
      }
   }

   public static function handleException(Throwable $exception): void {
      $content = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
      $accept = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

      if ($content || $accept) {
         self::resultApi($exception);
      } else {
         self::resultWeb($exception);
      }
   }

   public static function resultApi(Throwable $exception): void {
      $code = $exception->getCode() ?: 500;
      $message = $exception->getMessage() ?: 'Internal Server Error';
      $message = json_decode($message) ?? $message;
      self::$response->json([
         'data' => null,
         'error' => $message
      ], $code);
   }

   public static function resultWeb(Throwable $exception): void {
      $code = $exception->getCode() ?: 500;
      http_response_code($code);

      $config = import_config('defines.header');
      header('Access-Control-Allow-Origin: ' . $config['allow-origin']);
      header('Access-Control-Allow-Headers: ' . $config['allow-headers']);
      header('Access-Control-Allow-Methods: ' . $config['allow-methods']);
      header('Access-Control-Allow-Credentials: ' . $config['allow-credentials']);
      header('Content-Type: text/html; charset=UTF-8');

      if (get_env('DEVELOPMENT')) {
         $whoops = new WhoopsRun;
         $whoops->pushHandler(new WhoopsPrettyPageHandler);
         $whoops->register();
         $whoops->sendHttpCode($code);
      }

      throw $exception;
   }
}
