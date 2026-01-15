# API Reference

This page documents the public API of ShadowORM for developers who want to extend or integrate with the plugin.

## Domain Layer

### ShadowEntity

Immutable entity representing a post with its metadata.

```php
namespace ShadowORM\Core\Domain\Entity;

final class ShadowEntity
{
    public readonly int $postId;
    public readonly string $postType;
    public readonly string $content;
}
```

#### Constructor

```php
public function __construct(
    int $postId,
    string $postType,
    string $content = '',
    array $metaData = []
)
```

#### Methods

| Method         | Parameters                           | Return         | Description                        |
| -------------- | ------------------------------------ | -------------- | ---------------------------------- |
| `getMeta()`    | `string $key, mixed $default = null` | `mixed`        | Get meta value                     |
| `setMeta()`    | `string $key, mixed $value`          | `ShadowEntity` | Set meta (returns new instance)    |
| `hasMeta()`    | `string $key`                        | `bool`         | Check if meta exists               |
| `removeMeta()` | `string $key`                        | `ShadowEntity` | Remove meta (returns new instance) |
| `getAllMeta()` | -                                    | `array`        | Get all metadata                   |
| `toArray()`    | -                                    | `array`        | Convert to array                   |
| `fromArray()`  | `array $data`                        | `ShadowEntity` | Static factory method              |

#### Example

```php
use ShadowORM\Core\Domain\Entity\ShadowEntity;

$entity = new ShadowEntity(
    postId: 123,
    postType: 'product',
    content: '<p>Product description</p>',
    metaData: ['_price' => '99.99', '_sku' => 'ABC123']
);

// Get meta value
$price = $entity->getMeta('_price'); // "99.99"

// Set meta (returns new instance)
$updated = $entity->setMeta('_sale_price', '79.99');

// Original unchanged
$entity->hasMeta('_sale_price'); // false
$updated->hasMeta('_sale_price'); // true
```

### ShadowRepositoryInterface

Repository pattern interface for entity persistence.

```php
namespace ShadowORM\Core\Domain\Contract;

interface ShadowRepositoryInterface
{
    public function save(ShadowEntity $entity): void;
    public function find(int $postId): ?ShadowEntity;
    public function remove(int $postId): void;
    public function findByMeta(string $key, mixed $value): array;
    public function findMany(array $postIds): array;
    public function exists(int $postId): bool;
}
```

### StorageDriverInterface

Low-level storage operations interface.

```php
namespace ShadowORM\Core\Domain\Contract;

interface StorageDriverInterface
{
    public function insert(string $table, ShadowEntity $entity): int;
    public function update(string $table, ShadowEntity $entity): bool;
    public function delete(string $table, int $postId): bool;
    public function findByPostId(string $table, int $postId): ?ShadowEntity;
    public function findByMetaQuery(string $table, array $metaQuery): array;
    public function createIndex(string $table, string $jsonPath, string $indexName): void;
    public function dropIndex(string $table, string $indexName): void;
    public function supportsNativeJson(): bool;
    public function getDriverName(): string;
}
```

## Application Layer

### SyncService

Handles synchronization between WordPress and shadow tables.

```php
namespace ShadowORM\Core\Application\Service;

final class SyncService
{
    public function __construct(
        ShadowRepositoryInterface $repository,
        RuntimeCache $cache
    );

    public function syncPost(int $postId): void;
    public function deletePost(int $postId): void;
    public function migrateAll(string $postType, int $batchSize = 500, ?callable $progress = null): int;
    public function rollback(string $postType): void;
}
```

#### Example

```php
use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

$syncService = new SyncService($repository, new RuntimeCache());

// Sync single post
$syncService->syncPost(123);

// Migrate all products with progress callback
$migrated = $syncService->migrateAll('product', 500, function($current, $total) {
    echo "Progress: {$current}/{$total}\n";
});
```

### RuntimeCache

In-memory cache for the current request.

```php
namespace ShadowORM\Core\Application\Cache;

final class RuntimeCache
{
    public function get(int $postId): ?ShadowEntity;
    public function set(int $postId, ShadowEntity $entity): void;
    public function delete(int $postId): void;
    public function clear(): void;
    public function has(int $postId): bool;
    public function isMarkedNotFound(int $postId): bool;
    public function markNotFound(int $postId): void;
}
```

## Infrastructure Layer

### DriverFactory

Creates the appropriate database driver based on MySQL version.

```php
namespace ShadowORM\Core\Infrastructure\Driver;

final class DriverFactory
{
    public function __construct(wpdb $wpdb);
    public function create(): StorageDriverInterface;
    public function supportsMySQL8(): bool;
}
```

#### Example

```php
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;

global $wpdb;
$factory = new DriverFactory($wpdb);

// Get appropriate driver
$driver = $factory->create();

// Check what we got
echo $driver->getDriverName(); // "MySQL8" or "Legacy"
echo $driver->supportsNativeJson() ? "Yes" : "No";
```

### ShadowTableManager

Manages DDL operations on shadow tables.

```php
namespace ShadowORM\Core\Infrastructure\Persistence;

final class ShadowTableManager
{
    public function __construct(wpdb $wpdb, DriverFactory $factory);
    public function createTable(SchemaDefinition $schema): void;
    public function dropTable(string $postType): void;
    public function tableExists(string $postType): bool;
    public function getTableStats(string $postType): array;
}
```

### SchemaDefinition

Value object for table schema configuration.

```php
namespace ShadowORM\Core\Domain\ValueObject;

final class SchemaDefinition
{
    public function __construct(string $postType);
    public function getTableName(string $prefix): string;
    public function getPostType(): string;
}
```

## WordPress Hooks

### Filters

| Filter                       | Parameters                 | Description                 |
| ---------------------------- | -------------------------- | --------------------------- |
| `shadow_orm_supported_types` | `array $types`             | Modify supported post types |
| `shadow_orm_bypass_read`     | `bool $bypass`             | Skip read interception      |
| `shadow_orm_bypass_write`    | `bool $bypass`             | Skip write interception     |
| `shadow_orm_normalize_meta`  | `array $meta, int $postId` | Modify meta before save     |

### Actions

| Action                      | Parameters             | Description          |
| --------------------------- | ---------------------- | -------------------- |
| `shadow_orm_entity_saved`   | `ShadowEntity $entity` | After entity saved   |
| `shadow_orm_entity_deleted` | `int $postId`          | After entity deleted |
| `shadow_orm_entity_loaded`  | `ShadowEntity $entity` | After entity loaded  |
| `shadow_orm_table_created`  | `string $postType`     | After table created  |

## REST API

### GET /wp-json/shadow-orm/v1/settings

Returns current settings.

**Response:**

```json
{
  "enabled": true,
  "types": {
    "post": { "enabled": true, "count": 150 },
    "page": { "enabled": true, "count": 25 },
    "product": { "enabled": true, "count": 500 }
  },
  "driver": "MySQL8"
}
```

### POST /wp-json/shadow-orm/v1/settings

Updates settings.

**Request:**

```json
{
  "enabled": true,
  "types": ["post", "page", "product"]
}
```

### POST /wp-json/shadow-orm/v1/sync/{type}

Triggers sync for post type.

**Response:**

```json
{
  "success": true,
  "migrated": 500
}
```
