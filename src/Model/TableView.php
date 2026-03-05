<?php

namespace OwlConcept\TableBundle\Model;

class TableView
{
    /**
     * @param string     $id
     * @param Column[]   $columns
     * @param array[]    $rows
     * @param string     $mode           'server' or 'client'
     * @param Pagination $pagination
     * @param ?string    $sortField
     * @param string     $sortDirection  'asc' or 'desc'
     * @param array      $activeFilters
     * @param string     $cssClassPrefix
     * @param array[]    $allRows        Full dataset (client mode only)
     * @param ?string    $headerClass    Global CSS class applied to all <th> elements
     */
    public function __construct(
        private readonly string $id,
        private readonly array $columns,
        private readonly array $rows,
        private readonly string $mode,
        private readonly Pagination $pagination,
        private readonly ?string $sortField = null,
        private readonly string $sortDirection = 'asc',
        private readonly array $activeFilters = [],
        private readonly string $cssClassPrefix = 'owl-table',
        private readonly array $allRows = [],
        private readonly ?string $headerClass = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** @return Column[] */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getPagination(): Pagination
    {
        return $this->pagination;
    }

    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function getActiveFilters(): array
    {
        return $this->activeFilters;
    }

    public function getCssClassPrefix(): string
    {
        return $this->cssClassPrefix;
    }

    public function getAllRows(): array
    {
        return $this->allRows;
    }

    public function getHeaderClass(): ?string
    {
        return $this->headerClass;
    }

    public function isServerMode(): bool
    {
        return $this->mode === 'server';
    }

    public function isClientMode(): bool
    {
        return $this->mode === 'client';
    }

    public function hasFilters(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->isFilterable()) {
                return true;
            }
        }

        return false;
    }

    /** @return Column[] */
    public function getFilterableColumns(): array
    {
        return array_filter(
            $this->columns,
            fn(Column $col) => $col->isFilterable()
        );
    }

    public function getSortParams(string $columnKey): array
    {
        $direction = 'asc';
        if ($this->sortField === $columnKey && $this->sortDirection === 'asc') {
            $direction = 'desc';
        }

        return [
            'sort' => $columnKey,
            'direction' => $direction,
            'page' => 1,
        ];
    }

    public function getJsonData(): string
    {
        return json_encode($this->allRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }

    public function getJsonColumns(): string
    {
        $defs = [];
        foreach ($this->columns as $col) {
            $defs[] = [
                'key' => $col->getKey(),
                'label' => $col->getLabel(),
                'sortable' => $col->isSortable(),
                'filterable' => $col->isFilterable(),
                'filterType' => $col->getFilterType(),
                'filterOptions' => $col->getFilterOptions(),
            ];
        }

        return json_encode($defs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }
}
