# Configuration

ShadowORM is designed to work out of the box with minimal configuration. This page covers available configuration options.

## Admin Panel Settings

Navigate to **Settings → ShadowORM** in WordPress Admin.

### System Settings

| Setting          | Description                        | Default             |
| ---------------- | ---------------------------------- | ------------------- |
| Enable ShadowORM | Master switch for the plugin       | Enabled             |
| Post Types       | Which post types use shadow tables | post, page, product |

### Per-Post-Type Settings

For each post type, you can:

- **Enable/Disable**: Toggle shadow table usage
- **Sync**: Trigger manual synchronization
- **Rollback**: Remove shadow table

## REST API Configuration

ShadowORM exposes REST API endpoints for admin operations.

### Endpoints

| Endpoint                                 | Method | Description          |
| ---------------------------------------- | ------ | -------------------- |
| `/wp-json/shadow-orm/v1/settings`        | GET    | Get current settings |
| `/wp-json/shadow-orm/v1/settings`        | POST   | Update settings      |
| `/wp-json/shadow-orm/v1/types`           | GET    | List post types      |
| `/wp-json/shadow-orm/v1/sync/{type}`     | POST   | Sync post type       |
| `/wp-json/shadow-orm/v1/rollback/{type}` | POST   | Rollback post type   |

### Authentication

All endpoints require `manage_options` capability (Administrator role).

## Programmatic Configuration

### Adding Custom Post Types

By default, ShadowORM supports: `post`, `page`, `product`.

To add custom post types, use the filter:

```php
add_filter('shadow_orm_supported_types', function(array $types): array {
    $types[] = 'my_custom_type';
    return $types;
});
```

### Disabling for Specific Queries

To bypass ShadowORM for specific queries:

```php
// Temporarily disable read interception
add_filter('shadow_orm_bypass_read', '__return_true');

// Run your query
$meta = get_post_meta($post_id, 'my_key', true);

// Re-enable
remove_filter('shadow_orm_bypass_read', '__return_true');
```

## Database Configuration

### Table Prefix

Shadow tables use the WordPress table prefix:

- Table name format: `{prefix}app_shadow_{post_type}`
- Example: `wp_app_shadow_product`

### MySQL 8.0 JSON Indexing

For MySQL 8.0+, you can create custom indexes:

```php
// Create index on specific meta key
$driver->createIndex(
    'wp_app_shadow_product',
    '$._price',
    'price_idx'
);
```

This creates a virtual column and index:

```sql
ALTER TABLE wp_app_shadow_product
ADD COLUMN price_idx_idx VARCHAR(255)
GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(meta_data, '$._price'))) STORED;

CREATE INDEX price_idx ON wp_app_shadow_product (price_idx_idx);
```

## Cache Configuration

### RuntimeCache Behavior

The RuntimeCache stores entities for the duration of the PHP request.

| Behavior     | Description                   |
| ------------ | ----------------------------- |
| Scope        | Single request                |
| Size Limit   | None (memory limited)         |
| Persistence  | No (cleared after request)    |
| Invalidation | Automatic on write operations |

### Object Cache Integration

ShadowORM's RuntimeCache is independent of WordPress object cache. For persistent caching, consider:

```php
// Hook into entity load for persistent caching
add_action('shadow_orm_entity_loaded', function(ShadowEntity $entity): void {
    wp_cache_set(
        'shadow_entity_' . $entity->postId,
        $entity->toArray(),
        'shadow_orm',
        3600
    );
});
```

## Performance Tuning

### Batch Size for Migrations

Adjust batch size based on your server capacity:

```bash
# Low memory server
wp shadow migrate --type=product --batch=100

# High memory server
wp shadow migrate --type=product --batch=2000
```

### Recommended Settings by Site Size

| Site Size | Posts          | Batch Size | Memory Limit |
| --------- | -------------- | ---------- | ------------ |
| Small     | < 1,000        | 500        | 128M         |
| Medium    | 1,000 - 10,000 | 1,000      | 256M         |
| Large     | > 10,000       | 2,000      | 512M         |

## Environment-Specific Configuration

### Development

```php
// In wp-config.php for development
define('SHADOW_ORM_DEBUG', true);
```

### Production

```php
// Disable debug mode
define('SHADOW_ORM_DEBUG', false);
```

## Constants

| Constant             | Description          | Default         |
| -------------------- | -------------------- | --------------- |
| `SHADOW_ORM_VERSION` | Plugin version       | Current version |
| `SHADOW_ORM_DEBUG`   | Enable debug logging | false           |
