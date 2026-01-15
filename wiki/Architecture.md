# Architecture

ShadowORM is built using **Domain-Driven Design (DDD)** principles with a clear separation of concerns between layers.

## Directory Structure

```
/src
  /Core                          # Core plugin (Free version)
    /Domain                      # Pure domain logic and interfaces
      /Contract                  # Interfaces (StorageDriverInterface, ShadowRepositoryInterface)
      /Entity                    # Immutable entities (ShadowEntity)
      /ValueObject               # Value objects (SchemaDefinition)
    /Application                 # Business logic services
      /Cache                     # RuntimeCache
      /Service                   # SyncService, QueryService, AutoDiscoveryService
    /Infrastructure              # Technical implementations
      /Driver                    # MySQL8Driver, LegacyDriver, DriverFactory
      /Persistence               # ShadowRepository, ShadowTableManager
      /Installer                 # DropInInstaller
      /Exception                 # Custom exceptions
    /Presentation                # User interface layer
      /Admin                     # AdminPage, SettingsController
      /Cli                       # WP-CLI ShadowCommand
      /Hook                      # ReadInterceptor, WriteInterceptor, QueryInterceptor
```

## Layer Responsibilities

### Domain Layer

The innermost layer containing pure business logic with no external dependencies.

| Component                   | File                                     | Purpose                    |
| --------------------------- | ---------------------------------------- | -------------------------- |
| `StorageDriverInterface`    | `Contract/StorageDriverInterface.php`    | Storage abstraction        |
| `ShadowRepositoryInterface` | `Contract/ShadowRepositoryInterface.php` | Repository pattern         |
| `ShadowEntity`              | `Entity/ShadowEntity.php`                | Immutable post data entity |
| `SchemaDefinition`          | `ValueObject/SchemaDefinition.php`       | Table schema definition    |

### Application Layer

Orchestrates domain objects and implements use cases.

| Component              | File                               | Purpose                    |
| ---------------------- | ---------------------------------- | -------------------------- |
| `SyncService`          | `Service/SyncService.php`          | Post synchronization logic |
| `QueryService`         | `Service/QueryService.php`         | Query transformation       |
| `AutoDiscoveryService` | `Service/AutoDiscoveryService.php` | Post type detection        |
| `RuntimeCache`         | `Cache/RuntimeCache.php`           | In-memory caching          |

### Infrastructure Layer

Implements interfaces defined in Domain layer.

| Component            | File                                 | Purpose                   |
| -------------------- | ------------------------------------ | ------------------------- |
| `MySQL8Driver`       | `Driver/MySQL8Driver.php`            | Native JSON driver        |
| `LegacyDriver`       | `Driver/LegacyDriver.php`            | Lookup tables driver      |
| `DriverFactory`      | `Driver/DriverFactory.php`           | Driver selection          |
| `ShadowRepository`   | `Persistence/ShadowRepository.php`   | Repository implementation |
| `ShadowTableManager` | `Persistence/ShadowTableManager.php` | DDL operations            |
| `DropInInstaller`    | `Installer/DropInInstaller.php`      | Plugin installation       |

### Presentation Layer

User-facing interfaces (CLI, Admin, WordPress hooks).

| Component            | File                           | Purpose              |
| -------------------- | ------------------------------ | -------------------- |
| `ShadowCommand`      | `Cli/ShadowCommand.php`        | WP-CLI commands      |
| `AdminPage`          | `Admin/AdminPage.php`          | WordPress admin page |
| `SettingsController` | `Admin/SettingsController.php` | REST API endpoints   |
| `ReadInterceptor`    | `Hook/ReadInterceptor.php`     | get_post_meta hook   |
| `WriteInterceptor`   | `Hook/WriteInterceptor.php`    | save_post hook       |
| `QueryInterceptor`   | `Hook/QueryInterceptor.php`    | posts_clauses hook   |

## Dual-Driver Strategy

ShadowORM automatically detects MySQL version and selects the optimal driver:

```
┌─────────────────────────────────────────────────────────┐
│                    DriverFactory                        │
│  ┌─────────────────────────────────────────────────┐   │
│  │  MySQL Version >= 8.0?                          │   │
│  │     YES → MySQL8Driver (Native JSON)            │   │
│  │     NO  → LegacyDriver (Lookup Tables)          │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

### MySQL8Driver

Uses native MySQL 8.0 JSON functions:

- `JSON_EXTRACT()` for reading values
- `JSON_UNQUOTE()` for string extraction
- `JSON_CONTAINS_PATH()` for existence checks
- Virtual columns with `GENERATED ALWAYS AS` for indexing

### LegacyDriver

Uses additional lookup tables for MySQL 5.7/MariaDB:

- `{table}_lookup` with `(post_id, meta_key, meta_value)` structure
- Standard SQL JOINs for meta queries
- Automatic sync between JSON and lookup table

## Shadow Table Schema

For each enabled post type, a shadow table is created:

| Column      | Type (MySQL 8) | Type (Legacy) | Description                   |
| ----------- | -------------- | ------------- | ----------------------------- |
| `post_id`   | BIGINT (PK)    | BIGINT (PK)   | Foreign key to wp_posts       |
| `post_type` | VARCHAR(20)    | VARCHAR(20)   | Post type identifier          |
| `content`   | LONGTEXT       | LONGTEXT      | HTML content from the_content |
| `meta_data` | JSON           | LONGTEXT      | All metadata as JSON          |

Table naming: `{prefix}app_shadow_{post_type}`

Example: `wp_app_shadow_product`

## Entity Pattern

`ShadowEntity` is an immutable entity with copy-on-write semantics:

```php
// Immutable - modifications return new instances
$entity = new ShadowEntity(postId: 123, postType: 'post');
$updated = $entity->setMeta('price', 99.99); // Returns new entity

// Original unchanged
$entity !== $updated; // true
```

## Dependency Flow

```
Presentation → Application → Domain
                   ↓
             Infrastructure
```

- **Presentation** depends on Application
- **Application** depends on Domain (interfaces only)
- **Infrastructure** implements Domain interfaces
- **Domain** has no dependencies
