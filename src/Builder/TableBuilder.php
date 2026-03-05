<?php

namespace OwlConcept\TableBundle\Builder;

use OwlConcept\TableBundle\Model\Column;
use OwlConcept\TableBundle\Model\Pagination;
use OwlConcept\TableBundle\Model\TableView;
use Symfony\Component\HttpFoundation\Request;

class TableBuilder
{
    private string $id = '';
    private string $mode;
    private string $cssClassPrefix;
    private int $perPage;

    /** @var Column[] */
    private array $columns = [];

    /** @var array<int, array<string, mixed>> */
    private array $data = [];

    private int $page = 1;
    private int $totalItems = 0;
    private bool $totalExplicitlySet = false;

    private ?string $sortField = null;
    private string $sortDirection = 'asc';
    private array $activeFilters = [];

    public function __construct(
        string $defaultMode = 'server',
        int $defaultPerPage = 20,
        string $cssClassPrefix = 'owl-table',
    ) {
        $this->mode = $defaultMode;
        $this->perPage = $defaultPerPage;
        $this->cssClassPrefix = $cssClassPrefix;
    }

    public function create(string $id): self
    {
        $this->id = $id;
        $this->columns = [];
        $this->data = [];
        $this->page = 1;
        $this->totalItems = 0;
        $this->totalExplicitlySet = false;
        $this->sortField = null;
        $this->sortDirection = 'asc';
        $this->activeFilters = [];

        return $this;
    }

    /**
     * @param array{
     *     sortable?: bool,
     *     filterable?: bool,
     *     filter_type?: string,
     *     filter_options?: array,
     *     css_class?: string,
     *     formatter?: \Closure
     * } $options
     */
    public function addColumn(string $key, string $label, array $options = []): self
    {
        $this->columns[] = new Column(
            key: $key,
            label: $label,
            sortable: $options['sortable'] ?? false,
            filterable: $options['filterable'] ?? false,
            filterType: $options['filter_type'] ?? null,
            filterOptions: $options['filter_options'] ?? [],
            cssClass: $options['css_class'] ?? null,
            formatter: $options['formatter'] ?? null,
        );

        return $this;
    }

    public function setMode(string $mode): self
    {
        if (!in_array($mode, ['server', 'client'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid table mode "%s". Expected "server" or "client".', $mode)
            );
        }
        $this->mode = $mode;

        return $this;
    }

    public function setData(iterable $data): self
    {
        $this->data = $this->normalizeData($data);

        return $this;
    }

    public function setPagination(int $page = 1, int $perPage = 0, int $total = 0): self
    {
        $this->page = max(1, $page);
        if ($perPage > 0) {
            $this->perPage = $perPage;
        }
        if ($total > 0) {
            $this->totalItems = $total;
            $this->totalExplicitlySet = true;
        }

        return $this;
    }

    public function handleRequest(Request $request): self
    {
        // Sort params
        $sort = $request->query->get('sort');
        $direction = $request->query->get('direction', 'asc');
        if ($sort !== null && $this->isValidSortField($sort)) {
            $this->sortField = $sort;
            $this->sortDirection = in_array(strtolower($direction), ['asc', 'desc'], true)
                ? strtolower($direction)
                : 'asc';
        }

        // Page param
        $page = $request->query->getInt('page', 0);
        if ($page > 0) {
            $this->page = $page;
        }

        // Filter params: ?filter[name]=john&filter[role]=Admin
        $filters = $request->query->all('filter');
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if ($this->isValidFilterField($key) && $value !== '' && $value !== null) {
                    $this->activeFilters[$key] = $value;
                }
            }
        }

        return $this;
    }

    public function build(): TableView
    {
        $allRows = $this->data;
        $processedRows = $allRows;

        if ($this->mode === 'server') {
            // Apply server-side filtering
            $processedRows = $this->applyFilters($processedRows);

            // If total was not explicitly set, compute it after filtering
            if (!$this->totalExplicitlySet) {
                $this->totalItems = count($processedRows);
            }

            // Apply server-side sorting
            $processedRows = $this->applySorting($processedRows);

            // Apply server-side pagination
            $processedRows = $this->applyPagination($processedRows);
        }

        if ($this->mode === 'client') {
            if (!$this->totalExplicitlySet) {
                $this->totalItems = count($allRows);
            }
        }

        $pagination = new Pagination(
            currentPage: $this->page,
            perPage: $this->perPage,
            totalItems: $this->totalItems,
        );

        return new TableView(
            id: $this->id,
            columns: $this->columns,
            rows: $processedRows,
            mode: $this->mode,
            pagination: $pagination,
            sortField: $this->sortField,
            sortDirection: $this->sortDirection,
            activeFilters: $this->activeFilters,
            cssClassPrefix: $this->cssClassPrefix,
            allRows: $this->mode === 'client' ? $allRows : [],
        );
    }

    // -- Accessors for reading parsed request values (useful for DB queries) --

    public function getParsedSortField(): ?string
    {
        return $this->sortField;
    }

    public function getParsedSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function getParsedFilters(): array
    {
        return $this->activeFilters;
    }

    public function getParsedPage(): int
    {
        return $this->page;
    }

    public function getParsedPerPage(): int
    {
        return $this->perPage;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function normalizeData(iterable $data): array
    {
        $rows = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            } elseif (is_object($item)) {
                $rows[] = $this->objectToArray($item);
            }
        }

        return $rows;
    }

    private function objectToArray(object $obj): array
    {
        $row = [];
        foreach ($this->columns as $column) {
            $key = $column->getKey();

            if (isset($obj->$key)) {
                $row[$key] = $obj->$key;
            } else {
                $getter = 'get' . ucfirst($key);
                $isser = 'is' . ucfirst($key);

                if (method_exists($obj, $getter)) {
                    $row[$key] = $obj->$getter();
                } elseif (method_exists($obj, $isser)) {
                    $row[$key] = $obj->$isser();
                } else {
                    $row[$key] = null;
                }
            }
        }

        return $row;
    }

    private function isValidSortField(string $field): bool
    {
        foreach ($this->columns as $column) {
            if ($column->getKey() === $field && $column->isSortable()) {
                return true;
            }
        }

        return false;
    }

    private function isValidFilterField(string $field): bool
    {
        foreach ($this->columns as $column) {
            if ($column->getKey() === $field && $column->isFilterable()) {
                return true;
            }
        }

        return false;
    }

    private function applyFilters(array $rows): array
    {
        if (empty($this->activeFilters)) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) {
            foreach ($this->activeFilters as $key => $filterValue) {
                $column = $this->findColumn($key);
                if ($column === null) {
                    continue;
                }

                $cellValue = $row[$key] ?? '';

                switch ($column->getFilterType()) {
                    case 'text':
                        if (stripos((string) $cellValue, (string) $filterValue) === false) {
                            return false;
                        }
                        break;

                    case 'select':
                        if ((string) $cellValue !== (string) $filterValue) {
                            return false;
                        }
                        break;

                    case 'date_range':
                        if (is_array($filterValue)) {
                            $cellDate = $cellValue instanceof \DateTimeInterface
                                ? $cellValue->format('Y-m-d')
                                : (string) $cellValue;

                            if (!empty($filterValue['from']) && $cellDate < $filterValue['from']) {
                                return false;
                            }
                            if (!empty($filterValue['to']) && $cellDate > $filterValue['to']) {
                                return false;
                            }
                        }
                        break;
                }
            }

            return true;
        }));
    }

    private function applySorting(array $rows): array
    {
        if ($this->sortField === null) {
            return $rows;
        }

        $field = $this->sortField;
        $dir = $this->sortDirection === 'desc' ? -1 : 1;

        usort($rows, function (array $a, array $b) use ($field, $dir) {
            $va = $a[$field] ?? '';
            $vb = $b[$field] ?? '';

            if ($va instanceof \DateTimeInterface) {
                $va = $va->getTimestamp();
            }
            if ($vb instanceof \DateTimeInterface) {
                $vb = $vb->getTimestamp();
            }

            return ($va <=> $vb) * $dir;
        });

        return $rows;
    }

    private function applyPagination(array $rows): array
    {
        $offset = ($this->page - 1) * $this->perPage;

        return array_slice($rows, $offset, $this->perPage);
    }

    private function findColumn(string $key): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->getKey() === $key) {
                return $column;
            }
        }

        return null;
    }
}
