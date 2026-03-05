<?php

namespace OwlConcept\TableBundle\Twig;

use OwlConcept\TableBundle\Model\Column;
use OwlConcept\TableBundle\Model\TableView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OwlTableTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('owl_table_sort_url', [$this, 'sortUrl'], [
                'needs_context' => true,
                'is_safe' => ['html'],
            ]),
            new TwigFunction('owl_table_cell', [$this, 'renderCell'], [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function sortUrl(array $context, TableView $table, string $columnKey): string
    {
        $request = $context['app']->getRequest();
        $currentParams = $request->query->all();
        $sortParams = $table->getSortParams($columnKey);

        $merged = array_merge($currentParams, $sortParams);

        return '?' . http_build_query($merged);
    }

    public function renderCell(Column $column, mixed $value): string
    {
        return htmlspecialchars($column->formatValue($value), ENT_QUOTES, 'UTF-8');
    }
}
