<?php

declare(strict_types=1);

namespace System\Benchmark;

class Benchmark {
   private $startTime;
   private $endTime;
   private $memoryUsage;
   private $memoryPeak;

   public function run(callable $callable): mixed {
      $arguments = func_get_args();
      array_shift($arguments);

      $this->start();
      $result = call_user_func_array($callable, $arguments);
      $this->end();

      return $result;
   }

   public function start(): void {
      $this->startTime = microtime(true);
   }

   public function end(): void {
      $this->endTime   = microtime(true);
      $this->memoryUsage = memory_get_usage();
   }

   public function getTime(bool $raw = false, ?string $format = null): string|float {
      $elapsedTime = $this->endTime - $this->startTime;
      return $raw ? $elapsedTime : $this->checkTime($elapsedTime, $format);
   }

   public function getMemoryUsage(bool $raw = false, ?string $format = null): string|float {
      return $raw ? $this->memoryUsage : $this->checkSize($this->memoryUsage, $format);
   }

   public function getMemoryPeak(bool $raw = false, ?string $format = null): string|float {
      $this->memoryPeak = memory_get_peak_usage();
      return $raw ? $this->memoryPeak : $this->checkSize($this->memoryPeak, $format);
   }

   private function checkSize(int $size, ?string $format = null, int $round = 3): string {
      $mod = 1024;

      if (is_null($format)) {
         $format = '%.2f%s';
      }

      $units = explode(' ', 'b kb mb gb tb');

      for ($i = 0; $size > $mod; $i++) {
         $size /= $mod;
      }

      if (0 === $i) {
         $format = preg_replace('/(%.[\d]+f)/', '%d', $format);
      }

      return sprintf($format, round($size, $round), $units[$i]);
   }

   private function checkTime(float $microtime, ?string $format = null, int $round = 3): string {
      if (is_null($format)) {
         $format = '%.3f%s';
      }

      if ($microtime >= 1) {
         $unit = 's';
         $time = round($microtime, $round);
      } else {
         $unit = 'ms';
         $time = round($microtime * 1000);
      }

      $format = preg_replace('/(%.[\d]+f)/', '%d', $format);

      return sprintf($format, $time, $unit);
   }
}
