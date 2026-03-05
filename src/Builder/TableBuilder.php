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

    /** @var Column[] Auto-generated columns (built at build() time) */
    private array $columns = [];

    /** @var array<string, array> User-provided column options, keyed by column key */
    private array $columnOptions = [];

    /** @var array<string, string> User-provided labels, keyed by column key */
    private array $labels = [];

    /** @var array<int, array<string, mixed>> Normalized data rows */
    private array $data = [];

    /** @var string[] All unique keys detected from data */
    private array $detectedKeys = [];

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
        $this->columnOptions = [];
        $this->labels = [];
        $this->data = [];
        $this->detectedKeys = [];
        $this->page = 1;
        $this->totalItems = 0;
        $this->totalExplicitlySet = false;
        $this->sortField = null;
        $this->sortDirection = 'asc';
        $this->activeFilters = [];

        return $this;
    }

    /**
     * Set the data rows. Columns are auto-detected from the data keys.
     * Each element can be an associative array or an object (getters/public properties).
     */
    public function setData(iterable $data): self
    {
        $this->data = $this->normalizeData($data);
        $this->detectedKeys = $this->detectKeys($this->data);

        return $this;
    }

    /**
     * Set custom labels for columns.
     *
     * Supports two modes:
     *   - Associative array: keys are column keys, values are labels.
     *     ->setLabels(['name' => 'Nom', 'created_at' => 'Créé le'])
     *
     *   - Indexed array: labels are applied in the order of detected columns.
     *     ->setLabels(['Nom', 'Email', 'Rôle', 'Créé le'])
     *
     * @param array<string|int, string> $labels
     */
    public function setLabels(array $labels): self
    {
        // Detect if associative or indexed
        if ($this->isAssociativeArray($labels)) {
            // Associative: ['name' => 'Nom', 'email' => 'Email']
            $this->labels = $labels;
        } else {
            // Indexed: ['Nom', 'Email', 'Rôle'] → map to detected keys in order
            $keys = $this->detectedKeys;
            foreach ($labels as $index => $label) {
                if (isset($keys[$index])) {
                    $this->labels[$keys[$index]] = $label;
                }
            }
        }

        return $this;
    }

    /**
     * Define which columns are sortable.
     *
     *   ->setSortable(['name', 'email', 'created_at'])
     *
     * @param string[] $keys Column keys that should be sortable
     */
    public function setSortable(array $keys): self
    {
        foreach ($keys as $key) {
            $this->mergeColumnOption($key, 'sortable', true);
        }

        return $this;
    }

    /**
     * Define which columns are filterable.
     *
     * Two modes:
     *   - Indexed: columns become filterable with default 'text' type.
     *     ->setFilterable(['name', 'role'])
     *
     *   - Associative: keys are column keys, values are filter types.
     *     ->setFilterable(['name' => 'text', 'role' => 'select', 'created_at' => 'date_range'])
     *
     * @param array<string|int, string> $columns
     */
    public function setFilterable(array $columns): self
    {
        if ($this->isAssociativeArray($columns)) {
            // ['name' => 'text', 'role' => 'select']
            foreach ($columns as $key => $filterType) {
                $this->mergeColumnOption($key, 'filterable', true);
                $this->mergeColumnOption($key, 'filter_type', $filterType);
            }
        } else {
            // ['name', 'role'] → default 'text'
            foreach ($columns as $key) {
                $this->mergeColumnOption($key, 'filterable', true);
                $this->mergeColumnOption($key, 'filter_type', 'text');
            }
        }

        return $this;
    }

    /**
     * Set filter options for 'select' type filters.
     *
     *   ->setFilterOptions(['role' => ['Admin', 'User', 'Editor'], 'status' => ['Active', 'Inactive']])
     *
     * @param array<string, string[]> $options Column key => array of possible values
     */
    public function setFilterOptions(array $options): self
    {
        foreach ($options as $key => $values) {
            $this->mergeColumnOption($key, 'filter_options', $values);
        }

        return $this;
    }

    /**
     * Set custom formatters for columns.
     *
     *   ->setFormatters([
     *       'price' => fn($v) => number_format($v, 2, ',', ' ') . ' €',
     *       'active' => fn($v) => $v ? 'Oui' : 'Non',
     *   ])
     *
     * @param array<string, \Closure> $formatters Column key => formatter closure
     */
    public function setFormatters(array $formatters): self
    {
        foreach ($formatters as $key => $formatter) {
            $this->mergeColumnOption($key, 'formatter', $formatter);
        }

        return $this;
    }

    /**
     * Set CSS classes on columns.
     *
     * Two modes:
     *   - Associative: keys are column keys, values are CSS classes.
     *     ->setCssClasses(['name' => 'text-bold', 'email' => 'text-muted'])
     *
     *   - Indexed: classes applied in the order of detected columns.
     *     ->setCssClasses(['text-bold', 'text-muted', '', 'text-right'])
     *
     * @param array<string|int, string> $classes
     */
    public function setCssClasses(array $classes): self
    {
        if ($this->isAssociativeArray($classes)) {
            foreach ($classes as $key => $cssClass) {
                $this->mergeColumnOption($key, 'css_class', $cssClass);
            }
        } else {
            $keys = $this->detectedKeys;
            foreach ($classes as $index => $cssClass) {
                if (isset($keys[$index]) && $cssClass !== '') {
                    $this->mergeColumnOption($keys[$index], 'css_class', $cssClass);
                }
            }
        }

        return $this;
    }

    /**
     * Configure a specific column (optional).
     * If the column key exists in the data, its options will be applied.
     * If the column key does not exist, it will be ignored.
     *
     * @param string $key     The data key (must match a key found in the data)
     * @param array{
     *     label?: string,
     *     sortable?: bool,
     *     filterable?: bool,
     *     filter_type?: string,
     *     filter_options?: array,
     *     css_class?: string,
     *     formatter?: \Closure
     * } $options
     */
    public function configureColumn(string $key, array $options = []): self
    {
        $this->columnOptions[$key] = array_merge(
            $this->columnOptions[$key] ?? [],
            $options
        );

        return $this;
    }

    /**
     * Merge a single option into a column's options array.
     */
    private function mergeColumnOption(string $key, string $option, mixed $value): void
    {
        if (!isset($this->columnOptions[$key])) {
            $this->columnOptions[$key] = [];
        }
        $this->columnOptions[$key][$option] = $value;
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
        // Must build columns first so we can validate sort/filter fields
        $this->buildColumns();

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
        // Build columns from detected keys + user options (if not already done by handleRequest)
        $this->buildColumns();

        $allRows = $this->data;
        $processedRows = $allRows;

        if ($this->mode === 'server') {
            $processedRows = $this->applyFilters($processedRows);

            if (!$this->totalExplicitlySet) {
                $this->totalItems = count($processedRows);
            }

            $processedRows = $this->applySorting($processedRows);
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

    /**
     * Auto-generate Column objects from detected keys, merging user-provided labels and options.
     * Priority for label: configureColumn 'label' > setLabels() > auto-humanized key.
     */
    private function buildColumns(): void
    {
        // Don't rebuild if already built with the same keys
        if (!empty($this->columns)) {
            return;
        }

        $this->columns = [];
        foreach ($this->detectedKeys as $key) {
            $options = $this->columnOptions[$key] ?? [];

            // Label priority: configureColumn('label') > setLabels() > humanizeKey()
            $label = $options['label']
                ?? $this->labels[$key]
                ?? $this->humanizeKey($key);

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
        }
    }

    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return !array_is_list($arr);
    }

    /**
     * Scan all rows to find every unique key.
     *
     * @return string[]
     */
    private function detectKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * Transform a snake_case or camelCase key into a human-readable label.
     * Examples: 'created_at' -> 'Created at', 'firstName' -> 'First name', 'email' -> 'Email'
     */
    private function humanizeKey(string $key): string
    {
        // camelCase -> snake_case
        $snake = strtolower(preg_replace('/[A-Z]/', '_$0', $key));
        // snake_case -> words
        $words = str_replace('_', ' ', trim($snake, '_'));

        return ucfirst($words);
    }

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

    /**
     * Convert an object to an associative array by extracting all accessible properties.
     * Uses public properties, then getter methods (getX, isX, hasX).
     */
    private function objectToArray(object $obj): array
    {
        $row = [];

        // Public properties
        $reflection = new \ReflectionClass($obj);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isStatic()) {
                $row[$prop->getName()] = $prop->getValue($obj);
            }
        }

        // Getter methods (getX, isX, hasX) — only if no public properties were found
        // or to supplement them
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $name = $method->getName();
            $propertyKey = null;

            if (str_starts_with($name, 'get') && $name !== 'getClass') {
                $propertyKey = lcfirst(substr($name, 3));
            } elseif (str_starts_with($name, 'is')) {
                $propertyKey = lcfirst(substr($name, 2));
            } elseif (str_starts_with($name, 'has')) {
                $propertyKey = lcfirst(substr($name, 3));
            }

            if ($propertyKey !== null && !array_key_exists($propertyKey, $row)) {
                try {
                    $row[$propertyKey] = $method->invoke($obj);
                } catch (\Throwable) {
                    // Skip methods that throw
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
