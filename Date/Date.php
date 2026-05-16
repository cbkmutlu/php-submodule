<?php

declare(strict_types=1);

namespace System\Date;

use System\Exception\SystemException;
use System\Language\Language;
use DateTime;
use DateTimeZone;
use IntlDateFormatter;

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

    private string $timezone;
    private string $locale;
    private int $date_type;
    private int $time_type;
    private int $calendar;
    private int $timestamp = 0;
    private string $pattern;
    private IntlDateFormatter $formatter;
    private ?DateTime $datetime = null;

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
        if ($this->datetime === null) {
            return null;
        }
        $this->formatter->setPattern($pattern ?? $this->pattern);
        return $this->formatter->format($this->datetime) ?: null;
    }

    public function getTimestamp(bool $micro = false): ?int {
        if ($this->datetime === null) {
            return null;
        }

        return $micro ? ($this->timestamp * 1000) + intval($this->datetime->format('u') / 1000) : $this->timestamp;
    }

    public function getYear(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('Y');
    }

    public function getMonth(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('m');
    }

    public function getMonthString(bool $short = false): ?string {
        if ($this->datetime === null) {
            return null;
        }

        $this->formatter->setPattern($short ? 'MMM' : 'MMMM');
        return $this->formatter->format($this->datetime);
    }

    public function getDay(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('d');
    }

    public function getDayString(bool $short = false): ?string {
        if ($this->datetime === null) {
            return null;
        }

        $this->formatter->setPattern($short ? 'EEE' : 'EEEE');
        return $this->formatter->format($this->datetime);
    }

    public function getHour(bool $mode = true): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format($mode ? 'H' : 'h');
    }

    public function getMinute(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('i');
    }

    public function getSecond(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('s');
    }

    public function getMicroSecond(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('u');
    }

    public function getDayOfWeek(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('w');
    }

    public function getDayOfYear(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('z');
    }

    public function getWeekOfYear(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('W');
    }

    public function getDaysInMonth(): ?string {
        if ($this->datetime === null) {
            return null;
        }

        return $this->datetime->format('t');
    }

    public function isLeapYear(): ?bool {
        if ($this->datetime === null) {
            return null;
        }

        return (bool) $this->datetime->format('L');
    }

    public function setDate(mixed $date, ?string $format = null): self {
        if ($date === null) {
            return $this;
        }

        $clone = clone $this;
        if ($format === null) {
            $datetime = date_create($date, new DateTimeZone($clone->timezone));
        } else {
            $datetime = date_create_from_format($format, $date, new DateTimeZone($clone->timezone));
        }

        if ($datetime === false) {
            throw new SystemException('Invalid date format');
        }

        $clone->datetime = $datetime;
        $clone->timestamp = $datetime->getTimestamp();
        return $clone;
    }

    public function setTimestamp(int $timestamp): self {
        if ($this->datetime !== null) {
            $this->timestamp = $timestamp;
            $this->datetime->setTimestamp($timestamp);
        }
        return $this;
    }

    public function setTimezone(string $timezone): self {
        if ($this->datetime !== null) {
            $this->datetime->setTimezone(new DateTimeZone($timezone));
            $this->formatter->setTimeZone(new DateTimeZone($timezone));
        }
        return $this;
    }

    public function setLocale(string $locale): self {
        if ($this->datetime !== null) {
            $this->formatter = new IntlDateFormatter($locale, $this->date_type, $this->time_type, $this->timezone, $this->calendar);
            $this->pattern = $this->formatter->getPattern();
        }
        return $this;
    }

    public function setYear(int $year): self {
        if ($this->datetime !== null) {
            $this->datetime->setDate($year, (int) $this->getMonth(), (int) $this->getDay());
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function setMonth(int $month): self {
        if ($this->datetime !== null) {
            $this->datetime->setDate((int) $this->getYear(), $month, (int) $this->getDay());
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function setDay(int $day): self {
        if ($this->datetime !== null) {
            $this->datetime->setDate((int) $this->getYear(), (int) $this->getMonth(), $day);
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function setHour(int $hour): self {
        if ($this->datetime !== null) {
            $this->datetime->setTime($hour, (int) $this->getMinute(), (int) $this->getSecond());
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function setMinute(int $minute): self {
        if ($this->datetime !== null) {
            $this->datetime->setTime((int) $this->getHour(), $minute, (int) $this->getSecond());
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function setSecond(int $second): self {
        if ($this->datetime !== null) {
            $this->datetime->setTime((int) $this->getHour(), (int) $this->getMinute(), $second);
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function modifyDate(string $modify): self {
        if ($this->datetime !== null) {
            $this->datetime->modify($modify);
            $this->timestamp = $this->datetime->getTimestamp();
        }
        return $this;
    }

    public function compareDate(Date $date): array {
        if ($this->datetime === null || $date->datetime === null) {
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
