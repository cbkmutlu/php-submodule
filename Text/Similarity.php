<?php

declare(strict_types=1);

namespace System\Text;

class Similarity {
   private $excludeTr = ['VE', 'DA', 'DE', 'MI', 'MU', 'MÜ', 'AMA', 'İLE', 'BİR', 'BU', 'ŞU', 'O'];
   private $excludeEn = ['AND', 'THE', 'IS', 'ARE', 'A', 'AN', 'IN', 'ON', 'OF', 'TO', 'FOR', 'WITH', 'AT', 'BY'];
   private $excludeCustom;
   private $words;
   private $source;
   private $target;
   private $cosine = 0.6;
   private $similar = 0.3;
   private $levenshtein = 0.1;

   public function __construct(array $excludeCustom = [], float $cosine = 0.6, float $similar = 0.3, float $levenshtein = 0.1) {
      $this->excludeCustom = $excludeCustom;

      $total = $cosine + $similar + $levenshtein;
      if ($total > 0) {
         $this->cosine = $cosine / $total;
         $this->similar = $similar / $total;
         $this->levenshtein = $levenshtein / $total;
      }
   }

   public function compare(string $text1, string $text2, bool $weight = true): float {
      if (empty($text1) && empty($text2)) {
         return 1.0;
      }
      if (empty($text1) || empty($text2)) {
         return 0.0;
      }

      $this->source = $this->segment($text1);
      $this->target = $this->segment($text2);
      $this->wordAnalyse();
      $rate = $this->rateCosine();

      if ($weight) {
         $similar = $this->rateSimilar($text1, $text2);
         $levenshtein = $this->rateLevenshtein($text1, $text2);

         $rate = ($this->cosine * $rate) + ($this->similar * $similar) + ($this->levenshtein * $levenshtein);
      }

      return ($rate) ? $rate : 0.0;
   }

   private function rateCosine(): float {
      $sum = $sumT1 = $sumT2 = 0.0;

      foreach ($this->words as $counts) {
         $c1 = $counts[0] ?? 0;
         $c2 = $counts[1] ?? 0;
         $sum += $c1 * $c2;
         $sumT1 += $c1 ** 2;
         $sumT2 += $c2 ** 2;
      }

      if ($sumT1 === 0 || $sumT2 === 0) {
         return 0.0;
      }

      return $sum / (sqrt($sumT1) * sqrt($sumT2));
   }

   private function rateSimilar(string $text1, string $text2): float {
      $rate = similar_text($text1, $text2, $percentage);
      return $percentage / 100;
   }

   private function rateLevenshtein(string $text1, string $text2): float {
      if (mb_strlen($text1) > 255 || mb_strlen($text2) > 255) {
         return $this->wordLevenshtein($text1, $text2);
      }

      $distance = levenshtein($text1, $text2);
      $length = max(mb_strlen($text1), mb_strlen($text2));

      if ($length === 0) {
         return 1.0;
      }

      return 1 - ($distance / $length);
   }

   private function wordLevenshtein(string $text1, string $text2): float {
      $words1 = $this->segment($text1);
      $words2 = $this->segment($text2);

      $joinedWords1 = implode(' ', $words1);
      $joinedWords2 = implode(' ', $words2);

      if (mb_strlen($joinedWords1) > 255 || mb_strlen($joinedWords2) > 255) {
         $joinedWords1 = mb_substr($joinedWords1, 0, 255);
         $joinedWords2 = mb_substr($joinedWords2, 0, 255);
      }

      $distance = levenshtein($joinedWords1, $joinedWords2);
      $length = max(mb_strlen($joinedWords1), mb_strlen($joinedWords2));

      if ($length === 0) {
         return 1.0;
      }

      return 1 - ($distance / $length);
   }

   private function wordAnalyse(): void {
      $exclude = array_merge($this->excludeTr, $this->excludeEn, $this->excludeCustom);

      foreach ($this->source as $word) {
         if (!in_array($word, $exclude)) {
            $this->words[$word][0] = ($this->words[$word][0] ?? 0) + 1;
         }
      }

      foreach ($this->target as $word) {
         if (!in_array($word, $exclude)) {
            $this->words[$word][1] = ($this->words[$word][1] ?? 0) + 1;
         }
      }
   }

   private function segment(string $text): array {
      $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
      $clean = mb_strtoupper($clean, 'UTF-8');
      return preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
   }
}
