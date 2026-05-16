<?php

declare(strict_types=1);

namespace System\Cookie;

use System\Exception\SystemException;

class Cookie {
    private string $encryption_key;
    private bool $cookie_security;
    private bool $httponly;
    private bool $secure;
    private string $separator;
    private string $path;
    private string $domain;
    private string $samesite;

    public function __construct() {
        $config                = import_config('defines.cookie');
        $this->encryption_key  = $config['encryption_key'];
        $this->cookie_security = $config['cookie_security'];
        $this->httponly        = $config['httponly'];
        $this->secure          = $config['secure'];
        $this->separator       = $config['separator'];
        $this->path            = $config['path'];
        $this->domain          = $config['domain'];
        $this->samesite        = $config['samesite'];
    }

    public function set(string $key, string $value, int $expire = 0): void {
        if ($expire > 0) {
            $expire = time() + ($expire * 60 * 60);
        }

        if ($this->cookie_security) {
            $value .= $this->separator . hash_hmac('sha256', $value, $this->encryption_key);
        }

        setcookie($key, $value, [
            'expires'  => $expire,
            'path'     => $this->path,
            'domain'   => $this->domain,
            'secure'   => $this->secure,
            'httponly' => $this->httponly,
            'samesite' => $this->samesite
        ]);

        $_COOKIE[$key] = $value;
    }

    public function get(string $key, ?string $default = null): ?string {
        if (!isset($_COOKIE[$key])) {
            return $default;
        }
        $cookie = $_COOKIE[$key];

        if ($this->cookie_security) {
            $parts = explode($this->separator, $cookie, 2);
            if (count($parts) !== 2) {
                throw new SystemException("Cookie [$key] integrity check failed");
            }

            [$data, $hash] = $parts;
            if (!hash_equals(hash_hmac('sha256', $data, $this->encryption_key), $hash)) {
                throw new SystemException('Cookie [' . $key . '] integrity check failed');
            }

            return $data;
        }

        return $cookie;
    }

    public function delete(string $key): void {
        if ($this->has($key)) {
            unset($_COOKIE[$key]);
            setcookie($key, '', time() - 3600, $this->path, $this->domain, $this->secure, $this->httponly);
        }
    }

    public function has(string $key): bool {
        return isset($_COOKIE[$key]);
    }

    public function setPath(string $path): self {
        $this->path = $path;
        return $this;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function setHttpOnly(bool $http): self {
        $this->httponly = $http;
        return $this;
    }

    public function getHttpOnly(): bool {
        return $this->httponly;
    }

    public function setSecure(bool $secure): self {
        $this->secure = $secure;
        return $this;
    }

    public function getSecure(): bool {
        return $this->secure;
    }

    public function setDomain(string $domain): self {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain(): string {
        return $this->domain;
    }
}
