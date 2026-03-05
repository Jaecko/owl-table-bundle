# OwlTableBundle

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x-000000.svg)](https://symfony.com/)

**[FR]** Bundle Symfony pour la génération dynamique de tableaux HTML avec tri, filtres et pagination.
**[EN]** Symfony bundle for dynamic HTML table generation with sorting, filtering and pagination.

---

## Sommaire / Table of Contents

- [Français](#-français)
  - [Fonctionnalités](#fonctionnalités)
  - [Installation](#installation)
  - [Configuration](#configuration)
  - [Utilisation](#utilisation)
  - [Types de filtres](#types-de-filtres)
  - [Mode serveur vs client](#mode-serveur-vs-mode-client)
- [English](#-english)
  - [Features](#features)
  - [Installation](#installation-1)
  - [Configuration](#configuration-1)
  - [Usage](#usage)
  - [Filter types](#filter-types)
  - [Server vs client mode](#server-vs-client-mode)

---

## 🇫🇷 Français

### Fonctionnalités

- **Génération dynamique de tableaux** via un composant Twig réutilisable
- **Tri des colonnes** — côté serveur (rechargement de page) ou côté client (JavaScript)
- **Filtres configurables** — texte libre, liste déroulante, plage de dates
- **Filtres séparés** — template indépendant, plaçable n'importe où dans la page
- **Pagination intégrée** — avec navigation et ellipsis
- **CSS par défaut** — nommage BEM, responsive mobile (les lignes se transforment en cartes)
- **JavaScript vanilla** — zéro dépendance, auto-initialisation
- **API fluide** — style Builder inspiré du FormBuilder de Symfony

### Installation

Ajoutez le repository et le package dans votre `composer.json` :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Jaecko/owl-table-bundle"
        }
    ],
    "require": {
        "owl-concept/table-bundle": "dev-main"
    }
}
```

Puis lancez :

```bash
composer update
```

Enregistrez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    OwlConcept\TableBundle\OwlTableBundle::class => ['all' => true],
];
```

Installez les assets (CSS & JS) :

```bash
php bin/console assets:install
```

Incluez le CSS et le JS dans votre template de base :

```twig
<link rel="stylesheet" href="{{ asset('bundles/owltable/css/owl-table.css') }}">
<script src="{{ asset('bundles/owltable/js/owl-table.js') }}" defer></script>
```

### Configuration

Configuration optionnelle dans `config/packages/owl_table.yaml` :

```yaml
owl_table:
    default_mode: server    # 'server' ou 'client' (défaut: server)
    default_per_page: 20    # Éléments par page (défaut: 20)
    css_class_prefix: owl-table  # Préfixe CSS (défaut: owl-table)
```

### Utilisation

#### Dans le controller

```php
use OwlConcept\TableBundle\Builder\TableBuilder;
use Symfony\Component\HttpFoundation\Request;

#[Route('/users', name: 'users_list')]
public function list(Request $request, TableBuilder $tableBuilder): Response
{
    $users = [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'Admin', 'created_at' => '2024-01-15'],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'User', 'created_at' => '2024-03-22'],
        // ...
    ];

    $table = $tableBuilder->create('users_table')
        ->addColumn('name', 'Nom', [
            'sortable' => true,
            'filterable' => true,
            'filter_type' => 'text',
        ])
        ->addColumn('email', 'Email', [
            'sortable' => true,
        ])
        ->addColumn('role', 'Rôle', [
            'filterable' => true,
            'filter_type' => 'select',
            'filter_options' => ['Admin', 'User', 'Editor'],
        ])
        ->addColumn('created_at', 'Créé le', [
            'sortable' => true,
            'filterable' => true,
            'filter_type' => 'date_range',
        ])
        ->setMode('server')
        ->setData($users)
        ->setPagination(
            page: $request->query->getInt('page', 1),
            perPage: 20,
        )
        ->handleRequest($request)
        ->build();

    return $this->render('user/list.html.twig', ['table' => $table]);
}
```

#### Dans le template Twig

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Utilisateurs</h1>

    {# Les filtres peuvent être placés n'importe où : sidebar, en-tête, modal... #}
    <div class="sidebar">
        {% include '@OwlTable/filters.html.twig' with { table: table } %}
    </div>

    {# Le tableau avec pagination incluse automatiquement #}
    {% include '@OwlTable/table.html.twig' with { table: table } %}
{% endblock %}
```

#### Avec des entités Doctrine

Le builder supporte directement les objets (via les getters) :

```php
$users = $userRepository->findAll();

$table = $tableBuilder->create('users_table')
    ->addColumn('name', 'Nom', ['sortable' => true])
    ->addColumn('email', 'Email', ['sortable' => true])
    ->setData($users) // Les getters getName(), getEmail() sont appelés automatiquement
    ->build();
```

#### Formateur personnalisé

```php
->addColumn('price', 'Prix', [
    'sortable' => true,
    'formatter' => fn($value) => number_format($value, 2, ',', ' ') . ' €',
])
->addColumn('active', 'Actif', [
    'formatter' => fn($value) => $value ? 'Oui' : 'Non',
])
```

### Types de filtres

| Type | Description | Paramètres |
|------|-------------|------------|
| `text` | Champ texte libre avec recherche partielle (insensible à la casse) | — |
| `select` | Liste déroulante | `filter_options` : tableau des valeurs possibles |
| `date_range` | Deux champs date (du / au) | — |

### Mode serveur vs mode client

| | Mode serveur | Mode client |
|---|---|---|
| **Tri** | Liens `<a>` avec paramètres URL, rechargement de page | Boutons `<button>`, tri instantané en JS |
| **Filtres** | Formulaire `<form method="get">`, soumission classique | Écoute des événements `input`/`change`, filtrage instantané |
| **Pagination** | Liens `<a>` avec `?page=N` | Boutons `<button>`, changement de page sans rechargement |
| **Données** | Seule la page courante est dans le HTML | Toutes les données sont embarquées en JSON dans un attribut `data-*` |
| **Cas d'usage** | Grands jeux de données, requêtes DB paginées | Petits jeux de données (< 500 lignes) |

#### Requêtes DB avec le mode serveur

En mode serveur avec une base de données, utilisez les accesseurs du builder pour construire vos requêtes :

```php
$table = $tableBuilder->create('users_table')
    ->addColumn('name', 'Nom', ['sortable' => true, 'filterable' => true, 'filter_type' => 'text'])
    ->addColumn('email', 'Email', ['sortable' => true])
    ->setMode('server')
    ->handleRequest($request);

// Récupérer les valeurs parsées pour construire la requête DB
$sortField = $tableBuilder->getParsedSortField();       // ex: 'name'
$sortDir = $tableBuilder->getParsedSortDirection();      // ex: 'asc'
$filters = $tableBuilder->getParsedFilters();            // ex: ['name' => 'alice']
$page = $tableBuilder->getParsedPage();                  // ex: 2
$perPage = $tableBuilder->getParsedPerPage();            // ex: 20

// Requête Doctrine avec ces paramètres
$qb = $repo->createQueryBuilder('u');
// ... appliquer les filtres et le tri ...
$total = count($qb->getQuery()->getResult());
$users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

$table = $tableBuilder
    ->setData($users)
    ->setPagination(page: $page, perPage: $perPage, total: $total)
    ->build();
```

---

## 🇬🇧 English

### Features

- **Dynamic table generation** via a reusable Twig component
- **Column sorting** — server-side (page reload) or client-side (JavaScript)
- **Configurable filters** — free text, dropdown select, date range
- **Separate filter template** — independent include, placeable anywhere on the page
- **Built-in pagination** — with navigation and ellipsis
- **Default CSS** — BEM naming, responsive mobile (rows become stacked cards)
- **Vanilla JavaScript** — zero dependencies, auto-initialization
- **Fluent API** — Builder pattern inspired by Symfony's FormBuilder

### Installation

Add the repository and package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Jaecko/owl-table-bundle"
        }
    ],
    "require": {
        "owl-concept/table-bundle": "dev-main"
    }
}
```

Then run:

```bash
composer update
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    OwlConcept\TableBundle\OwlTableBundle::class => ['all' => true],
];
```

Install the assets (CSS & JS):

```bash
php bin/console assets:install
```

Include the CSS and JS in your base template:

```twig
<link rel="stylesheet" href="{{ asset('bundles/owltable/css/owl-table.css') }}">
<script src="{{ asset('bundles/owltable/js/owl-table.js') }}" defer></script>
```

### Configuration

Optional configuration in `config/packages/owl_table.yaml`:

```yaml
owl_table:
    default_mode: server    # 'server' or 'client' (default: server)
    default_per_page: 20    # Items per page (default: 20)
    css_class_prefix: owl-table  # CSS prefix (default: owl-table)
```

### Usage

#### In the controller

```php
use OwlConcept\TableBundle\Builder\TableBuilder;
use Symfony\Component\HttpFoundation\Request;

#[Route('/users', name: 'users_list')]
public function list(Request $request, TableBuilder $tableBuilder): Response
{
    $users = [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'Admin', 'created_at' => '2024-01-15'],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'User', 'created_at' => '2024-03-22'],
        // ...
    ];

    $table = $tableBuilder->create('users_table')
        ->addColumn('name', 'Name', [
            'sortable' => true,
            'filterable' => true,
            'filter_type' => 'text',
        ])
        ->addColumn('email', 'Email', [
            'sortable' => true,
        ])
        ->addColumn('role', 'Role', [
            'filterable' => true,
            'filter_type' => 'select',
            'filter_options' => ['Admin', 'User', 'Editor'],
        ])
        ->addColumn('created_at', 'Created at', [
            'sortable' => true,
            'filterable' => true,
            'filter_type' => 'date_range',
        ])
        ->setMode('server')
        ->setData($users)
        ->setPagination(
            page: $request->query->getInt('page', 1),
            perPage: 20,
        )
        ->handleRequest($request)
        ->build();

    return $this->render('user/list.html.twig', ['table' => $table]);
}
```

#### In the Twig template

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Users</h1>

    {# Filters can be placed anywhere: sidebar, header, modal... #}
    <div class="sidebar">
        {% include '@OwlTable/filters.html.twig' with { table: table } %}
    </div>

    {# Table with pagination automatically included #}
    {% include '@OwlTable/table.html.twig' with { table: table } %}
{% endblock %}
```

#### With Doctrine entities

The builder supports objects directly (via getters):

```php
$users = $userRepository->findAll();

$table = $tableBuilder->create('users_table')
    ->addColumn('name', 'Name', ['sortable' => true])
    ->addColumn('email', 'Email', ['sortable' => true])
    ->setData($users) // Automatically calls getName(), getEmail()
    ->build();
```

#### Custom formatter

```php
->addColumn('price', 'Price', [
    'sortable' => true,
    'formatter' => fn($value) => '$' . number_format($value, 2),
])
->addColumn('active', 'Active', [
    'formatter' => fn($value) => $value ? 'Yes' : 'No',
])
```

### Filter types

| Type | Description | Parameters |
|------|-------------|------------|
| `text` | Free text field with partial search (case-insensitive) | — |
| `select` | Dropdown select | `filter_options`: array of possible values |
| `date_range` | Two date fields (from / to) | — |

### Server vs client mode

| | Server mode | Client mode |
|---|---|---|
| **Sorting** | `<a>` links with URL parameters, page reload | `<button>` elements, instant JS sorting |
| **Filters** | `<form method="get">`, classic submission | Listens to `input`/`change` events, instant filtering |
| **Pagination** | `<a>` links with `?page=N` | `<button>` elements, page change without reload |
| **Data** | Only the current page is in the HTML | Full dataset embedded as JSON in a `data-*` attribute |
| **Use case** | Large datasets, paginated DB queries | Small datasets (< 500 rows) |

#### DB queries with server mode

In server mode with a database, use the builder's accessors to build your queries:

```php
$table = $tableBuilder->create('users_table')
    ->addColumn('name', 'Name', ['sortable' => true, 'filterable' => true, 'filter_type' => 'text'])
    ->addColumn('email', 'Email', ['sortable' => true])
    ->setMode('server')
    ->handleRequest($request);

// Get parsed values to build your DB query
$sortField = $tableBuilder->getParsedSortField();       // e.g. 'name'
$sortDir = $tableBuilder->getParsedSortDirection();      // e.g. 'asc'
$filters = $tableBuilder->getParsedFilters();            // e.g. ['name' => 'alice']
$page = $tableBuilder->getParsedPage();                  // e.g. 2
$perPage = $tableBuilder->getParsedPerPage();            // e.g. 20

// Doctrine query using these parameters
$qb = $repo->createQueryBuilder('u');
// ... apply filters and sorting ...
$total = count($qb->getQuery()->getResult());
$users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

$table = $tableBuilder
    ->setData($users)
    ->setPagination(page: $page, perPage: $perPage, total: $total)
    ->build();
```

---

## License

Proprietary — Owl Concept
