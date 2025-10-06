<?php

declare(strict_types=1);

namespace System\Pagination;

use System\Exception\SystemException;

class Pagination {
   private $max_pages = 5;
   private $current_page = 1;
   private $items_perpage = 10;
   private $total_items;
   private $total_pages;
   private $url_pattern;

   public function setMaxPages(int $value): self {
      if ($value < 3) {
         throw new SystemException('Max pages must be at least 3');
      }
      $this->max_pages = $value;
      return $this;
   }

   public function setItemsPerPage(int $value): self {
      if ($value < 1) {
         throw new SystemException('Items per page must be at least 1');
      }
      $this->items_perpage = $value;
      return $this;
   }

   public function setCurrentPage(int $value): self {
      if ($value < 1) {
         throw new SystemException('Current page must be at least 1');
      }
      $this->current_page = $value;
      return $this;
   }

   public function setTotalItems(int $value): self {
      if ($value < 1) {
         throw new SystemException('Total items must be at least 1');
      }
      $this->total_items = $value;
      return $this;
   }

   public function setUrlPattern(string $pattern): self {
      if (strpos($pattern, '%s') === false) {
         throw new SystemException('Url pattern must contain %s');
      }
      $this->url_pattern = $pattern;
      return $this;
   }

   public function getData(): array {
      $this->total_pages = ceil($this->total_items / $this->items_perpage);
      return [
         'current_page' => $this->current_page,
         'total_pages' => $this->total_pages,
         'items_per_page' => $this->items_perpage,
         'total_items' => $this->total_items,
         'has_previous' => $this->getPrevPage() !== null,
         'has_next' => $this->getNextPage() !== null,
         'previous_page' => $this->getPrevPage(),
         'next_page' => $this->getNextPage(),
         'first_item' => $this->getCurrentPageFirstItem(),
         'last_item' => $this->getCurrentPageLastItem(),
         'pages' => $this->getPages(),
      ];
   }

   private function createPage(float $number, bool $current = false): array {
      return [
         'number'  => $number,
         'url'     => sprintf($this->url_pattern, $number),
         'current' => $current,
      ];
   }

   private function createPageEllipsis(): array {
      return [
         'number'  => '...',
         'url'     => null,
         'current' => false,
      ];
   }

   private function getCurrentPageFirstItem(): ?int {
      $first = ($this->current_page - 1) * $this->items_perpage + 1;
      return ($first > $this->total_items) ? null : $first;
   }

   private function getCurrentPageLastItem(): ?int {
      $first = $this->getCurrentPageFirstItem();
      if ($first === null) {
         return null;
      }

      $last = $first + $this->items_perpage - 1;
      return ($last > $this->total_items) ? $this->total_items : $last;
   }

   private function getNextPage(): ?int {
      return ($this->current_page < $this->total_pages) ? $this->current_page + 1 : null;
   }

   private function getPrevPage(): ?int {
      return ($this->current_page > 1) ? $this->current_page - 1 : null;
   }

   private function getPages(): array {
      if ($this->total_pages <= 1) {
         return [];
      }

      $pages = [];

      if ($this->total_pages <= $this->max_pages) {
         for ($i = 1; $i <= $this->total_pages; $i++) {
            $pages[] = $this->createPage($i, $i == $this->current_page);
         }
      } else {
         $adjacents = floor(($this->max_pages - 3) / 2);

         if ($this->current_page + $adjacents > $this->total_pages) {
            $start = $this->total_pages - $this->max_pages + 2;
         } else {
            $start = $this->current_page - $adjacents;
         }

         if ($start < 2) {
            $start = 2;
         }

         $end = $start + $this->max_pages - 3;

         if ($end >= $this->total_pages) {
            $end = $this->total_pages - 1;
         }

         $pages[] = $this->createPage(1, $this->current_page == 1);

         if ($start > 2) {
            $pages[] = $this->createPageEllipsis();
         }

         for ($i = $start; $i <= $end; $i++) {
            $pages[] = $this->createPage($i, $i == $this->current_page);
         }

         if ($end < $this->total_pages - 1) {
            $pages[] = $this->createPageEllipsis();
         }

         $pages[] = $this->createPage($this->total_pages, $this->current_page == $this->total_pages);
      }

      return $pages;
   }
}
