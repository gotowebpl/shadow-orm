# Getting Started

This guide will help you get up and running with ShadowORM in minutes.

## Quick Start

### Step 1: Activate the Plugin

After installation, activate ShadowORM via WordPress Admin or WP-CLI:

```bash
wp plugin activate shadow-orm
```

### Step 2: Check System Status

```bash
wp shadow status
```

Expected output:

```
+------------+-------+----------+------+--------+
| Post Type  | Total | Migrated | Size | Driver |
+------------+-------+----------+------+--------+
| post       | 150   | 0        | -    | -      |
| page       | 25    | 0        | -    | -      |
| product    | 500   | 0        | -    | -      |
+------------+-------+----------+------+--------+
```

### Step 3: Migrate Your Data

```bash
# Start with posts
wp shadow migrate --type=post

# Then other types
wp shadow migrate --type=page
wp shadow migrate --type=product

# Or migrate everything at once
wp shadow migrate --all
```

### Step 4: Verify Migration

```bash
wp shadow status
```

After migration:

```
+------------+-------+----------+--------+--------+
| Post Type  | Total | Migrated | Size   | Driver |
+------------+-------+----------+--------+--------+
| post       | 150   | 150      | 1.2 MB | MySQL8 |
| page       | 25    | 25       | 0.3 MB | MySQL8 |
| product    | 500   | 500      | 5.6 MB | MySQL8 |
+------------+-------+----------+--------+--------+
```

## Using the Admin Panel

Navigate to **Settings → ShadowORM** to access the admin panel.

### Features Available

1. **System Status**: Enable/disable ShadowORM globally
2. **Post Type Management**: Toggle shadow tables per post type
3. **Migration Status**: View how many posts are migrated
4. **Sync Actions**: Trigger sync or rollback operations

### Enabling/Disabling Post Types

In the admin panel, you can:

- Enable shadow tables for specific post types
- Disable shadow tables (falls back to wp_postmeta)
- Sync individual post types

## How Your Code Changes

**Good news: It doesn't!**

ShadowORM is fully transparent. Your existing code continues to work:

```php
// These work exactly the same with ShadowORM
$price = get_post_meta($product_id, '_price', true);
$all_meta = get_post_meta($post_id);

// Updates are synced automatically
update_post_meta($post_id, 'my_field', 'value');
```

## What Happens Behind the Scenes

1. **Read Request**: `get_post_meta()` is intercepted
2. **Cache Check**: RAM cache is checked first
3. **Shadow Table**: If not cached, data is fetched from shadow table (1 query)
4. **Cache Update**: Result is stored in RAM cache
5. **Return**: Value is returned to your code

## Performance Testing

Run a quick benchmark:

```bash
wp shadow benchmark --type=product --iterations=100
```

Example output:

```
Benchmark results for 100 iterations:
  Average: 0.342 ms
  Min: 0.218 ms
  Max: 0.567 ms
```

## Next Steps

- [Architecture](Architecture) - Understand how ShadowORM works
- [WP-CLI Commands](WP-CLI-Commands) - Full command reference
- [Configuration](Configuration) - Advanced configuration options
