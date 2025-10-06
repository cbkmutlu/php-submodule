<?php

declare(strict_types=1);

namespace System\Benchmark;

use System\Language\Language;

class Benchmark {
   private $start_time;
   private $end_time;
   private $memory_usage;
   private $memory_peak;

   public function __construct(
      private Language $language
   ) {
   }

   public function run(object $callback): void {
      $arguments = func_get_args();
      array_shift($arguments);

      $this->start();
      call_user_func_array($callback, $arguments);
      $this->end();
   }

   public function start(): void {
      $this->start_time = microtime(true);
   }

   public function end(): void {
      $this->end_time = microtime(true);
      $this->memory_usage = memory_get_usage();
   }

   public function getTime(bool $raw = false): string|float {
      $time = $this->end_time - $this->start_time;
      $format = format_time($time);
      return $raw ? $time : $this->language->system('date.x_' . $format['unit'], [$format['value']]);
   }

   public function getMemoryUsage(bool $raw = false): string|float {
      return $raw ? $this->memory_usage : format_size($this->memory_usage);
   }

   public function getMemoryPeak(bool $raw = false): string|float {
      $this->memory_peak = memory_get_peak_usage();
      return $raw ? $this->memory_peak : format_size($this->memory_peak);
   }
}
