<?php

namespace OwlConcept\TableBundle\Model;

class Pagination
{
    public function __construct(
        private readonly int $currentPage,
        private readonly int $perPage,
        private readonly int $totalItems,
    ) {
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getTotalPages(): int
    {
        if ($this->totalItems <= 0) {
            return 1;
        }

        return (int) ceil($this->totalItems / $this->perPage);
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    /**
     * @return array<int|null> Page numbers with null for ellipsis
     */
    public function getPageRange(int $delta = 2): array
    {
        $total = $this->getTotalPages();
        $current = $this->currentPage;
        $range = [];

        $range[] = 1;

        $rangeStart = max(2, $current - $delta);
        $rangeEnd = min($total - 1, $current + $delta);

        if ($rangeStart > 2) {
            $range[] = null;
        }

        for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
            $range[] = $i;
        }

        if ($rangeEnd < $total - 1) {
            $range[] = null;
        }

        if ($total > 1) {
            $range[] = $total;
        }

        return $range;
    }
}
