<?php

declare(strict_types=1);

namespace System\Date;

use DateTimeZone;
use IntlDateFormatter;
use System\Language\Language;
use System\Exception\SystemException;

class Date {
   const ATOM     = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T20:55:30+02:00
   const COOKIE   = "EEEE, dd-MMM-yy HH:mm:ss z";   // Friday, 21-Feb-14 20:56:21 EET
   const ISO8601  = "yyyy-MM-dd'T'HH:mm:ssO";       // 2014-02-21T20:57:15+0200
   const RFC822   = "EEE, dd MMM yy HH:mm:ss O";    // Fri, 21 Feb 2014 20:58:24 +0200
   const RFC850   = "EEEE, dd-MMM-yy HH:mm:ss z";   // Friday, 21-Feb-14 20:59:23 EET
   const RFC1036  = "EEE, dd MMM yy HH:mm:ss O";    // Fri, 21 Feb 14 21:00:17 +0200
   const RFC1123  = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:00:58 +0200
   const RFC2822  = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:01:35 +0200
   const RFC3339  = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T21:02:31+02:00
   const RSS      = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:03:26 +0200
   const W3C      = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T21:04:09+02:00
   const GENERIC  = "yyyy-MM-dd HH:mm:ss";          // 2014-02-21 21:04:55
   const GENERIC3 = "yyyy-MM-dd HH:mm:ss.SSS";      // 2014-02-21 21:04:55.000
   const DEFAULT  = "d MMMM y EEEE HH:mm:ss zzzz";  // 21 February 2014 Friday 21:04:55 EET

   private $timezone;
   private $locale;
   private $date_type;
   private $time_type;
   private $calendar;
   private $timestamp;
   private $pattern;
   private $formatter;
   private $datetime;

   public function __construct(
      private Language $language
   ) {
      $config          = import_config('defines.language.locales')[$this->language->getLocale()];
      $this->pattern   = $config['pattern'];
      $this->timezone  = $config['timezone'];
      $this->locale    = $config['locale'];
      $this->date_type = $config['date_type'];
      $this->time_type = $config['time_type'];
      $this->calendar  = $config['calendar'];

      $this->formatter = new IntlDateFormatter($this->locale, $this->date_type, $this->time_type, $this->timezone, $this->calendar, $this->pattern);
   }

   public function getDate(?string $pattern = null): ?string {
      $this->formatter->setPattern($pattern ?? $this->pattern);
      return $this->formatter->format($this->datetime) ?: null;
   }

   public function getTimestamp(bool $micro = false): ?int {
      if (!is_null($this->datetime)) {
         return $micro ? ($this->datetime->getTimestamp() * 1000) + intval($this->datetime->format('u') / 1000) : $this->datetime->getTimestamp();
      }
      return null;
   }

   public function getYear(): ?string {
      return $this->datetime->format('Y') ?: null;
   }

   public function getMonth(): ?string {
      return $this->datetime->format('m') ?: null;
   }

   public function getMonthString(bool $short = false): ?string {
      $this->formatter->setPattern($short ? 'MMM' : 'MMMM');
      return $this->formatter->format($this->datetime) ?: null;
   }

   public function getDay(): ?string {
      return $this->datetime->format('d') ?: null;
   }

   public function getDayString(bool $short = false): ?string {
      $this->formatter->setPattern($short ? 'EEE' : 'EEEE');
      return $this->formatter->format($this->datetime) ?: null;
   }

   public function getHour(bool $mode = true): ?string {
      return $this->datetime->format($mode ? 'H' : 'h') ?: null;
   }

   public function getMinute(): ?string {
      return $this->datetime->format('i') ?: null;
   }

   public function getSecond(): ?string {
      return $this->datetime->format('s') ?: null;
   }

   public function getMicroSecond(): ?string {
      return $this->datetime->format('u') ?: null;
   }

   public function getDayOfWeek(): ?string {
      return $this->datetime->format('w') ?: null;
   }

   public function getDayOfYear(): ?string {
      return $this->datetime->format('z') ?: null;
   }

   public function getWeekOfYear(): ?string {
      return $this->datetime->format('W') ?: null;
   }

   public function getDaysInMonth(): ?string {
      return $this->datetime->format('t') ?: null;
   }

   public function isLeapYear(): ?bool {
      return (bool) $this->datetime->format('L') ?: null;
   }

   public function setDate(mixed $date, ?string $format = null): self {
      if (is_null($date)) {
         return $this;
      }

      $clone = clone $this;

      if (is_null($format)) {
         $clone->datetime = date_create($date, new DateTimeZone($clone->timezone));
         $clone->timestamp = $clone->datetime->getTimestamp();
      } else {
         $d = date_create_from_format($format, $date, new DateTimeZone($clone->timezone));
         if ($d) {
            $clone->datetime = $d;
            $clone->timestamp = $d->getTimestamp();
         } else {
            throw new SystemException('Invalid date format');
         }
      }

      return $clone;
   }

   public function setTimestamp(int|float $timestamp): self {
      if (!is_null($this->datetime)) {
         $this->timestamp = $timestamp;
         $this->datetime->setTimestamp($timestamp);
      }
      return $this;
   }

   public function setTimezone(string $timezone): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setTimezone(new DateTimeZone($timezone));
         $this->formatter->setTimeZone(new DateTimeZone($timezone));
      }
      return $this;
   }

   public function setLocale(string $locale): self {
      if (!is_null($this->datetime)) {
         $this->formatter = new IntlDateFormatter($locale, $this->date_type, $this->time_type, $this->timezone, $this->calendar);
         $this->pattern = $this->formatter->getPattern();
      }
      return $this;
   }

   public function setYear(int $year): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setDate($year, (int) $this->getMonth(), (int) $this->getDay());
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function setMonth(int $month): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setDate((int) $this->getYear(), $month, (int) $this->getDay());
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function setDay(int $day): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setDate((int) $this->getYear(), (int) $this->getMonth(), $day);
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function setHour(int $hour): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setTime($hour, (int) $this->getMinute(), (int) $this->getSecond());
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function setMinute(int $minute): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setTime((int) $this->getHour(), $minute, (int) $this->getSecond());
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function setSecond(int $second): self {
      if (!is_null($this->datetime)) {
         $this->datetime->setTime((int) $this->getHour(), (int) $this->getMinute(), $second);
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function modifyDate(string $modify): self {
      if (!is_null($this->datetime)) {
         $this->datetime->modify($modify);
         $this->timestamp = $this->datetime->getTimestamp();
      }
      return $this;
   }

   public function compareDate(Date $date): array {
      if (is_null($this->datetime) || is_null($date->datetime)) {
         return [];
      }

      $interval = $this->datetime->diff($date->datetime);
      $h = $interval->h + ($interval->days * 24);
      $m = $interval->i + ($h * 60);
      $s = $interval->s + ($m * 60);

      return [
         'years' => $interval->y,
         'months' => $interval->m,
         'days' => $interval->d,
         'hours' => $interval->h,
         'minutes' => $interval->i,
         'seconds' => $interval->s,
         'isEqual' => ($interval->invert === 0 && $interval->days === 0 && $interval->h === 0 && $interval->i === 0 && $interval->s === 0) ? 1 : 0,
         'isBefore' => ($this->datetime < $date->datetime) ? 1 : 0,
         'isAfter' => ($this->datetime > $date->datetime) ? 1 : 0,
         'diff' => [
            'years' => $interval->y,
            'months' => $interval->y * 12 + $interval->m,
            'days' => $interval->days,
            'hours' => $h,
            'minutes' => $m,
            'seconds' => $s
         ]
      ];
   }

   public function humanDate(): string {
      $interval = $this->datetime->diff(date_create('now', new DateTimeZone($this->timezone)));

      if ($interval->y > 0) {
         return $this->language->system('date.years_ago', [$interval->y]);
      } elseif ($interval->m > 0) {
         return $this->language->system('date.months_ago', [$interval->m]);
      } elseif ($interval->d >= 7) {
         $weeks = floor($interval->d / 7);
         return $this->language->system('date.weeks_ago', [$weeks]);
      } elseif ($interval->d > 0) {
         return $this->language->system('date.days_ago', [$interval->d]);
      } elseif ($interval->h > 0) {
         return $this->language->system('date.hours_ago', [$interval->h]);
      } elseif ($interval->i > 0) {
         return $this->language->system('date.minutes_ago', [$interval->i]);
      } elseif ($interval->s > 0) {
         return $this->language->system('date.seconds_ago', [$interval->s]);
      } else {
         return $this->language->system('date.just');
      }
   }
}
