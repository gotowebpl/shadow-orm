# Troubleshooting

This page helps you diagnose and resolve common issues with ShadowORM.

## Common Issues

### Plugin Activation Fails

**Symptoms:**

- White screen on activation
- PHP Fatal Error

**Causes & Solutions:**

| Cause                         | Solution                               |
| ----------------------------- | -------------------------------------- |
| PHP version < 8.1             | Upgrade PHP to 8.1+                    |
| Missing Composer dependencies | Run `composer install`                 |
| Permission denied for db.php  | Set write permissions on `wp-content/` |

**Debug Steps:**

```bash
# Check PHP version
php -v

# Verify Composer dependencies
cd wp-content/plugins/shadow-orm
composer install --no-dev

# Check permissions
ls -la wp-content/
```

### MySQL Version Warning

**Symptom:**

```
ShadowORM requires MySQL 5.7 or higher. Current version: 5.6.x
```

**Solution:**

- Upgrade MySQL to 5.7+ or MariaDB 10.2+
- Contact your hosting provider for database upgrade

### Shadow Tables Not Created

**Symptoms:**

- `wp shadow status` shows 0 migrated
- Migration command succeeds but no data

**Causes & Solutions:**

| Cause                   | Solution                                    |
| ----------------------- | ------------------------------------------- |
| Table creation failed   | Check MySQL user permissions                |
| No posts to migrate     | Verify posts exist for the type             |
| Post type not supported | Add via `shadow_orm_supported_types` filter |

**Debug Steps:**

```bash
# Check what's happening
wp shadow migrate --type=post --dry-run

# Verify posts exist
wp post list --post_type=post --post_status=publish --format=count
```

### Data Not Syncing

**Symptoms:**

- Updates to posts not reflected in shadow table
- Stale data returned

**Causes & Solutions:**

| Cause                      | Solution                      |
| -------------------------- | ----------------------------- |
| Hooks not firing           | Check for conflicting plugins |
| Shadow table doesn't exist | Run migration first           |
| Post type not enabled      | Enable in admin panel         |

**Debug Steps:**

```php
// Check if hooks are registered
add_action('save_post', function($id) {
    error_log("save_post fired for: " . $id);
}, 1);

// Force sync
$syncService->syncPost($post_id);
```

### Read Interception Not Working

**Symptoms:**

- `get_post_meta()` still hitting wp_postmeta
- No performance improvement

**Causes & Solutions:**

| Cause                   | Solution              |
| ----------------------- | --------------------- |
| Post type not supported | Check supported types |
| Shadow table empty      | Run migration         |
| Cache poisoned          | Clear and re-sync     |

**Debug Steps:**

```bash
# Check if data exists in shadow table
wp db query "SELECT COUNT(*) FROM wp_app_shadow_post"

# Verify interception is working
wp eval "var_dump(get_post_meta(1));"
```

### Performance Not Improved

**Symptoms:**

- Query times unchanged
- Same number of database queries

**Causes & Solutions:**

| Cause                | Solution                                  |
| -------------------- | ----------------------------------------- |
| Legacy driver in use | Upgrade to MySQL 8.0                      |
| Missing indexes      | Create indexes on frequently queried keys |
| Large JSON documents | Consider normalizing data                 |

**Benchmarking:**

```bash
# Run benchmark
wp shadow benchmark --type=product --iterations=100

# Compare with Query Monitor plugin
```

## Database Issues

### Table Does Not Exist Error

```
Table 'wp_app_shadow_product' doesn't exist
```

**Solution:**

```bash
wp shadow migrate --type=product
```

### Foreign Key Constraint Errors

**Symptom:**

```
Cannot add or update a child row: foreign key constraint fails
```

**Solution:**
Shadow tables don't use foreign keys by default. If you've added custom constraints, ensure referenced posts exist.

### JSON Parse Errors

**Symptom:**

```
Malformed JSON in meta_data column
```

**Solution:**

```bash
# Re-sync affected post
wp eval "
require 'wp-content/plugins/shadow-orm/vendor/autoload.php';
\$syncService->syncPost(POST_ID);
"
```

## Plugin Conflicts

### Known Conflicting Plugins

| Plugin                           | Issue                | Workaround                  |
| -------------------------------- | -------------------- | --------------------------- |
| Other caching plugins            | May cache stale data | Clear cache after migration |
| Object caching (Redis/Memcached) | Separate cache layer | No conflict, works together |
| ACF (Free version)               | Limited meta format  | Works as expected           |

### Detecting Conflicts

```php
// Check if another plugin hooks into get_post_metadata
global $wp_filter;
print_r($wp_filter['get_post_metadata']);
```

## Debug Mode

Enable debug logging:

```php
// In wp-config.php
define('SHADOW_ORM_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will appear in `wp-content/debug.log`.

## Recovery Procedures

### Full Rollback

If ShadowORM causes issues, restore to WordPress default:

```bash
# Remove all shadow tables
wp shadow rollback --type=post
wp shadow rollback --type=page
wp shadow rollback --type=product

# Deactivate plugin
wp plugin deactivate shadow-orm

# WordPress will resume using wp_postmeta
```

### Rebuild Shadow Tables

If data is corrupted:

```bash
# Drop existing tables
wp shadow rollback --type=product

# Re-migrate
wp shadow migrate --type=product
```

### Emergency: Manual Cleanup

If WP-CLI doesn't work:

```sql
-- List shadow tables
SHOW TABLES LIKE '%shadow%';

-- Drop specific table
DROP TABLE wp_app_shadow_product;
DROP TABLE wp_app_shadow_product_lookup;

-- Delete db.php drop-in
-- Manually delete: wp-content/db.php
```

## Getting Help

If you can't resolve the issue:

1. **Check GitHub Issues**: [github.com/gotoweb/shadow-orm/issues](https://github.com/gotoweb/shadow-orm/issues)
2. **Open New Issue** with:
   - WordPress version
   - PHP version
   - MySQL version
   - Error message/logs
   - Steps to reproduce
3. **Email Support**: kontakt@gotoweb.pl (Pro users)
