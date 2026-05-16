<?php

declare(strict_types=1);

namespace System\Exception;

use System\Http\{Request, Response};
use ErrorException;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class ExceptionHandler {
    public function __construct(
        protected Response $response,
        protected Request $request
    ) {
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
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

            if ($exit) {
                exit();
            }

            throw new ErrorException($type . ': ' . $errstr, 0, $errno, $errfile, $errline);
        }
    }

    public function handleException(Throwable $exception): void {
        if ($this->request->isJson()) {
            $this->resultApi($exception);
        } else {
            $this->resultWeb($exception);
        }
    }

    private function resultApi(Throwable $exception): void {
        $code = $exception->getCode() ?: 500;
        $message = $exception->getMessage() ?: 'Internal Server Error';
        $message = json_decode($message) ?? $message;
        $this->response->json([
            'data' => null,
            'error' => $message
        ], $code);
    }

    private function resultWeb(Throwable $exception): void {
        $code = $exception->getCode() ?: 500;
        http_response_code($code);

        $config = import_config('defines.header');
        header('Access-Control-Allow-Origin: ' . $config['allow-origin']);
        header('Access-Control-Allow-Headers: ' . $config['allow-headers']);
        header('Access-Control-Allow-Methods: ' . $config['allow-methods']);
        header('Access-Control-Allow-Credentials: ' . $config['allow-credentials']);
        header('Content-Type: text/html; charset=UTF-8');

        if (get_env('APP_ENV') === 'development') {
            $whoops = new Run;
            $whoops->pushHandler(new PrettyPageHandler);
            $whoops->register();
            $whoops->sendHttpCode($code);
            throw $exception;
        }
    }
}
