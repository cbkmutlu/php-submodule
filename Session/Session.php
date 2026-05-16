<?php

declare(strict_types=1);

namespace System\Session;

class Session {
    private array $config;

    public function __construct() {
        $this->config = import_config('defines.session');

        if ($this->config['cookie_httponly']) {
            ini_set('session.cookie_httponly', 1);
        }

        if ($this->config['use_only_cookies']) {
            ini_set('session.use_only_cookies', 1);
        }

        ini_set('session.cookie_samesite', $this->config['samesite']);
        ini_set('session.gc_maxlifetime', $this->config['lifetime']);
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params($this->config['lifetime']);
        $this->start();
    }

    public function start(): void {
        if ($this->isActive()) {
            return;
        }

        session_name($this->config['session_name']);
        session_start();
        $this->regenerate();
    }

    public function regenerate(): void {
        if ($this->isActive()) {
            session_regenerate_id(true);
        }
    }

    public function set(string $key, mixed $value): void {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function delete(string $key): void {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy(): void {
        if ($this->isActive()) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function flash(string $key, mixed $value = null): mixed {
        $this->start();
        if ($value === null) {
            $data = $_SESSION['session_flash'][$key] ?? null;
            unset($_SESSION['session_flash'][$key]);
            return $data;
        }

        $_SESSION['session_flash'][$key] = $value;
        return null;
    }

    public function csrf(): string {
        $this->start();
        if (!$this->has('session_csrf')) {
            $_SESSION['session_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['session_csrf'];
    }

    public function verifyCsrf(string $token): bool {
        $this->start();
        if (!$this->has('session_csrf')) {
            return false;
        }

        $valid = hash_equals($_SESSION['session_csrf'], $token);
        unset($_SESSION['session_csrf']);

        return $valid;
    }

    private function isActive(): bool {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
