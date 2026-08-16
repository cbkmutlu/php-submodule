<?php

declare(strict_types=1);

namespace System\View;

use System\Exception\SystemException;
use Throwable;

class View {
    private string $cacheDir = APP_DIR . 'Storage/Cache/Views/';

    public function __construct() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function import(string $file, array $data = []): string {
        [, $templatePath] = $this->resolvePath($file);
        return $this->evaluateTemplate($templatePath, $data);
    }

    public function render(string $file, array $data = [], bool $cache = false): string {
        [$basePath, $templatePath] = $this->resolvePath($file);

        $cacheFile = $this->cacheDir . md5($templatePath) . '.php';

        if (!$cache || !is_file($cacheFile) || filemtime($templatePath) > filemtime($cacheFile)) {
            $this->compileTemplate($templatePath, $basePath, $cacheFile);
        }

        return $this->evaluateTemplate($cacheFile, $data);
    }

    private function resolvePath(string $file): array {
        [$module, $template] = explode('@', $file, 2);
        $basePath = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/';
        $templatePath = $basePath . $template;

        if (!is_file($templatePath)) {
            throw new SystemException('View file not found [' . $templatePath . ']');
        }

        return [$basePath, $templatePath];
    }

    private function compileTemplate(string $templatePath, string $basePath, string $cacheFile): void {
        $code = file_get_contents($templatePath);

        if (preg_match('/<!--\s*main:(?<template>[a-zA-Z0-9_.\-\/]+)\s*-->/', $code, $matches) === 1) {
            $baseFilePath = $basePath . trim($matches['template']);
            if (!is_file($baseFilePath)) {
                throw new SystemException('Base view file not found [' . $baseFilePath . ']');
            }

            $base = file_get_contents($baseFilePath);
            $blocks = $this->replaceBlocks($code);
            $code = $this->replaceYields($base, $blocks);
        }

        $code = $this->replaceImports($basePath, $code);
        $code = $this->replaceVariables($code);
        $code = $this->replaceConditions($code);

        file_put_contents($cacheFile, $code);
    }

    private function evaluateTemplate(string $file, array $data): string {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw new SystemException('View Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        return ob_get_clean();
    }

    private function replaceVariables(string $code): string {
        return preg_replace_callback('/{{\s*(.+?)\s*}}/', function ($m) {
            $var = trim($m[1]);
            if (!str_starts_with($var, '$') && !str_contains($var, '(') && !str_contains($var, '->')) {
                $var = '$' . $var;
            }
            return '<?= htmlspecialchars((string) (' . $var . ') ?? \'\') ?>';
        }, $code);
    }

    private function replaceConditions(string $code): string {
        $code = preg_replace_callback(
            '#<!--\s*(if|elseif|foreach|while) \((.+?)\)\s*-->#',
            function ($match) {
                $keyword = $match[1];
                $condition = trim($match[2]);
                return '<?php ' . $keyword . '(' . $condition . '): ?>';
            },
            $code
        );

        $code = preg_replace_callback(
            '#<!--\s*(else)\s*-->#',
            function ($match) {
                return '<?php ' . $match[1] . ': ?>';
            },
            $code
        );

        $code = preg_replace_callback(
            '#<!--\s*(endif|endforeach|endwhile)\s*-->#',
            function ($match) {
                return '<?php ' . $match[1] . '; ?>';
            },
            $code
        );

        return $code;
    }

    private function replaceBlocks(string $code): array {
        preg_match_all('#<!--\s*block:(?<name>\w+)\s*-->(?<content>.*?)<!--\s*endblock\s*-->#s', $code, $matches, PREG_SET_ORDER);
        $blocks = [];

        foreach ($matches as $match) {
            $blocks[$match['name']] = $match['content'];
        }

        return $blocks;
    }

    private function replaceYields(string $code, array $blocks): string {
        preg_match_all('#<!--\s*yield:(?<name>\w+)\s*-->#', $code, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match['name'];
            if (isset($blocks[$name])) {
                $block = $blocks[$name];
                $code = preg_replace('#<!--\s*yield:' . preg_quote($name, '#') . '\s*-->#', $block, $code);
            }
        }

        return $code;
    }

    private function replaceImports(string $dir, string $code): string {
        preg_match_all('#<!--\s*import:(?<template>.*?)\s*-->#', $code, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return $code;
        }

        foreach ($matches as $match) {
            $template = trim($match['template']);
            $includePath = $dir . $template;
            $contents = is_file($includePath) ? file_get_contents($includePath) : '';
            $code = str_replace($match[0], $contents, $code);
        }

        return $code;
    }
}
