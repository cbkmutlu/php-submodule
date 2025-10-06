<?php

declare(strict_types=1);

namespace System\View;

use System\Exception\SystemException;

class View {
   private $theme;

   public function import(string $theme, array $data = [], bool $cache = false): void {
      [$module, $file] = explode('@', $theme);
      if (is_null($this->theme)) {
         $path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/';
      } else {
         $path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $this->theme . '/';
      }

      if (!is_file($path . $file)) {
         throw new SystemException("View file not found [{$path}{$file}]");
      }

      extract($data);
      require_once $path . $file;
   }

   public function render(string $theme, array $data = [], bool $cache = false): string {
      [$module, $template] = explode('@', $theme);
      if (is_null($this->theme)) {
         $path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/';
      } else {
         $path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $this->theme . '/';
      }

      if (!is_file($path . $template)) {
         throw new SystemException("View file not found [{$path}{$template}]");
      }

      $code = file_get_contents($path . $template);

      if (preg_match('/<!--\s*main:(?<template>[a-zA-Z0-9_.\-\/]+)\s*-->/', $code, $matches) === 1) {
         if (!is_file($path . $matches["template"])) {
            throw new SystemException("Base view file not found [{$path}{$matches["template"]}]");
         }

         $base = file_get_contents($path . $matches["template"]);
         $blocks = $this->blocks($code);
         $code = $this->yields($base, $blocks);
      }

      $code = $this->includes($path, $code);
      $code = $this->variables($code);
      $code = $this->conditions($code);

      extract($data, EXTR_SKIP);
      ob_start();
      try {
         eval("?>$code");
      } catch (SystemException $e) {
         ob_end_clean();
         throw new SystemException($e->getMessage());
      }

      return ob_get_clean();
   }

   private function variables(string $code): string {
      return preg_replace("#{{\s*(\S+)\s*}}#", "<?= htmlspecialchars(\$$1 ?? '') ?>", $code);
   }

   private function conditions(string $code): string {
      $code = preg_replace_callback(
         '#<!--\s*(if|elseif|foreach|while) \((.+?)\)\s*-->#',
         function ($match) {
            $keyword = $match[1];
            $condition = trim($match[2]);
            return "<?php $keyword($condition): ?>";
         },
         $code
      );

      $code = preg_replace_callback(
         '#<!--\s*(else)\s*-->#',
         function ($match) {
            return "<?php {$match[1]}: ?>";
         },
         $code
      );

      $code = preg_replace_callback(
         '#<!--\s*(endif|endforeach|endwhile)\s*-->#',
         function ($match) {
            return "<?php {$match[1]}; ?>";
         },
         $code
      );

      return $code;
   }

   private function blocks(string $code): array {
      preg_match_all('#<!--\s*block:(?<name>\w+)\s*-->(?<content>.*?)<!--\s*endblock\s*-->#s', $code, $matches, PREG_SET_ORDER);
      $blocks = [];

      foreach ($matches as $match) {
         $blocks[$match['name']] = $match['content'];
      }

      return $blocks;
   }

   private function yields(string $code, array $blocks): string {
      preg_match_all('#<!--\s*yield:(?<name>\w+)\s*-->#', $code, $matches, PREG_SET_ORDER);

      foreach ($matches as $match) {
         $name = $match["name"];
         if (isset($blocks[$name])) {
            $block = $blocks[$name];
            $code = preg_replace('#<!--\s*yield:' . preg_quote($name, '#') . '\s*-->#', $block, $code);
         }
      }

      return $code;
   }

   private function includes(string $dir, string $code): string {
      preg_match_all('#<!--\s*import:(?<template>.*?)\s*-->#', $code, $matches, PREG_SET_ORDER);

      if (empty($matches)) {
         return $code;
      }

      foreach ($matches as $match) {
         $template = trim($match["template"]);
         $contents = file_get_contents($dir . $template);
         $code = str_replace($match[0], $contents, $code);
      }

      return $code;
   }

   public function theme(string $theme): self {
      $this->theme = $theme;
      return $this;
   }
}
