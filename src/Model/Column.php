<?php

namespace OwlConcept\TableBundle\Model;

class Column
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly bool $sortable = false,
        private readonly bool $filterable = false,
        private readonly ?string $filterType = null,
        private readonly array $filterOptions = [],
        private readonly ?string $cssClass = null,
        private readonly ?string $headerClass = null,
        private readonly ?\Closure $formatter = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function getFilterType(): ?string
    {
        return $this->filterType;
    }

    public function getFilterOptions(): array
    {
        return $this->filterOptions;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function getHeaderClass(): ?string
    {
        return $this->headerClass;
    }

    public function formatValue(mixed $value): string
    {
        if ($this->formatter !== null) {
            return ($this->formatter)($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        return (string) ($value ?? '');
    }
}
