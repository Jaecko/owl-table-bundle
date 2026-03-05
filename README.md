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

- **Colonnes auto-détectées** — les colonnes sont générées automatiquement à partir des clés de vos données
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

#### Exemple minimal — tableau automatique

Passez simplement vos données, le tableau se construit tout seul :

```php
use OwlConcept\TableBundle\Builder\TableBuilder;

#[Route('/users', name: 'users_list')]
public function list(TableBuilder $tableBuilder): Response
{
    $users = [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'Admin'],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'User'],
    ];

    $table = $tableBuilder->create('users_table')
        ->setData($users)  // Les colonnes "name", "email", "role" sont auto-détectées
        ->build();

    return $this->render('user/list.html.twig', ['table' => $table]);
}
```

> Les en-têtes sont générés automatiquement : `created_at` → **Created at**, `firstName` → **First name**, `email` → **Email**

#### Renommer les colonnes avec `setLabels()`

Deux syntaxes possibles :

```php
// Mode associatif — par clé
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['name' => 'Nom', 'email' => 'Courriel', 'role' => 'Rôle', 'created_at' => 'Créé le'])
    ->build();

// Mode indexé — dans l'ordre des colonnes détectées
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['Nom', 'Courriel', 'Rôle', 'Créé le'])
    ->build();
```

> En mode indexé, les labels sont appliqués dans l'ordre des clés détectées. Vous pouvez ne renommer que les premières colonnes : `->setLabels(['Nom', 'Courriel'])` ne renomme que les 2 premières.

#### Configurer par tableaux (rapide)

Chaque aspect est configurable via sa propre méthode, sans toucher à `configureColumn()` :

```php
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['Nom', 'Email', 'Rôle', 'Créé le'])
    ->setSortable(['name', 'email', 'created_at'])
    ->setFilterable(['name' => 'text', 'role' => 'select', 'created_at' => 'date_range'])
    ->setFilterOptions(['role' => ['Admin', 'User', 'Editor']])
    ->setFormatters(['created_at' => fn($v) => date('d/m/Y', strtotime($v))])
    ->setMode('server')
    ->handleRequest($request)
    ->build();
```

| Méthode | Mode indexé | Mode associatif |
|---------|-------------|-----------------|
| `setLabels()` | `['Nom', 'Email']` (dans l'ordre) | `['name' => 'Nom']` (par clé) |
| `setSortable()` | `['name', 'email']` (ces clés deviennent triables) | — |
| `setFilterable()` | `['name', 'role']` (type `text` par défaut) | `['name' => 'text', 'role' => 'select']` |
| `setFilterOptions()` | — | `['role' => ['Admin', 'User']]` |
| `setFormatters()` | — | `['price' => fn($v) => ...]` |
| `setCssClasses()` | `['bold', '', 'right']` (dans l'ordre) | `['name' => 'bold']` |

#### Configurer colonne par colonne (avancé)

`configureColumn()` reste disponible pour tout regrouper sur une colonne :

```php
->configureColumn('role', [
    'label' => 'Rôle',
    'filterable' => true,
    'filter_type' => 'select',
    'filter_options' => ['Admin', 'User'],
    'css_class' => 'text-center',
])
```

> **Priorité** : `configureColumn()` > `setLabels()` / `setSortable()` / etc. > auto-détection

#### Colonnes avec des données hétérogènes

Si certaines lignes ont des clés que d'autres n'ont pas, le tableau détecte l'union de toutes les clés :

```php
$data = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'phone' => '06 12 34 56 78'],
];

// Résultat : 3 colonnes → Name, Email, Phone
// Alice aura une cellule vide pour "Phone"
$table = $tableBuilder->create('contacts')->setData($data)->build();
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

Le builder supporte directement les objets (via les getters et propriétés publiques) :

```php
$users = $userRepository->findAll();

// Les colonnes sont détectées via getName(), getEmail(), getRole(), etc.
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setSortable(['name', 'email'])
    ->build();
```

#### Formateurs personnalisés

```php
->setFormatters([
    'price' => fn($v) => number_format($v, 2, ',', ' ') . ' €',
    'active' => fn($v) => $v ? 'Oui' : 'Non',
    'created_at' => fn($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y') : $v,
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
    ->setData([]) // données vides pour l'instant
    ->setSortable(['name', 'email'])
    ->setFilterable(['name' => 'text'])
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

- **Auto-detected columns** — columns are automatically generated from your data keys
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

#### Minimal example — automatic table

Just pass your data, the table builds itself:

```php
use OwlConcept\TableBundle\Builder\TableBuilder;

#[Route('/users', name: 'users_list')]
public function list(TableBuilder $tableBuilder): Response
{
    $users = [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'Admin'],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'User'],
    ];

    $table = $tableBuilder->create('users_table')
        ->setData($users)  // Columns "name", "email", "role" are auto-detected
        ->build();

    return $this->render('user/list.html.twig', ['table' => $table]);
}
```

> Headers are generated automatically: `created_at` → **Created at**, `firstName` → **First name**, `email` → **Email**

#### Rename columns with `setLabels()`

Two syntaxes available:

```php
// Associative mode — by key
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['name' => 'Full Name', 'email' => 'Email Address', 'created_at' => 'Joined'])
    ->build();

// Indexed mode — in order of detected columns
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['Full Name', 'Email Address', 'Role', 'Joined'])
    ->build();
```

> In indexed mode, labels are applied in the order of detected keys. You can rename only the first columns: `->setLabels(['Full Name', 'Email Address'])` only renames the first 2.

#### Configure with arrays (quick)

Each aspect has its own method, no need for `configureColumn()`:

```php
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setLabels(['Full Name', 'Email', 'Role', 'Joined'])
    ->setSortable(['name', 'email', 'created_at'])
    ->setFilterable(['name' => 'text', 'role' => 'select', 'created_at' => 'date_range'])
    ->setFilterOptions(['role' => ['Admin', 'User', 'Editor']])
    ->setFormatters(['created_at' => fn($v) => date('M d, Y', strtotime($v))])
    ->setMode('server')
    ->handleRequest($request)
    ->build();
```

| Method | Indexed mode | Associative mode |
|--------|-------------|-----------------|
| `setLabels()` | `['Name', 'Email']` (in order) | `['name' => 'Name']` (by key) |
| `setSortable()` | `['name', 'email']` (these keys become sortable) | — |
| `setFilterable()` | `['name', 'role']` (default `text` type) | `['name' => 'text', 'role' => 'select']` |
| `setFilterOptions()` | — | `['role' => ['Admin', 'User']]` |
| `setFormatters()` | — | `['price' => fn($v) => ...]` |
| `setCssClasses()` | `['bold', '', 'right']` (in order) | `['name' => 'bold']` |

#### Configure column by column (advanced)

`configureColumn()` is still available to group all options on a single column:

```php
->configureColumn('role', [
    'label' => 'Role',
    'filterable' => true,
    'filter_type' => 'select',
    'filter_options' => ['Admin', 'User'],
    'css_class' => 'text-center',
])
```

> **Priority**: `configureColumn()` > `setLabels()` / `setSortable()` / etc. > auto-detection

#### Heterogeneous data

If some rows have keys that others don't, the table detects the union of all keys:

```php
$data = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'phone' => '+33 6 12 34 56 78'],
];

// Result: 3 columns → Name, Email, Phone
// Alice will have an empty cell for "Phone"
$table = $tableBuilder->create('contacts')->setData($data)->build();
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

The builder supports objects directly (via getters and public properties):

```php
$users = $userRepository->findAll();

// Columns are detected via getName(), getEmail(), getRole(), etc.
$table = $tableBuilder->create('users_table')
    ->setData($users)
    ->setSortable(['name', 'email'])
    ->build();
```

#### Custom formatters

```php
->setFormatters([
    'price' => fn($v) => '$' . number_format($v, 2),
    'active' => fn($v) => $v ? 'Yes' : 'No',
    'created_at' => fn($v) => $v instanceof \DateTimeInterface ? $v->format('M d, Y') : $v,
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
    ->setData([]) // empty data for now
    ->setSortable(['name', 'email'])
    ->setFilterable(['name' => 'text'])
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
