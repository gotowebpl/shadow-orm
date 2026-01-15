# Frequently Asked Questions

## General

### What is ShadowORM?

ShadowORM is a WordPress plugin that accelerates meta queries by storing post metadata in optimized "shadow tables" instead of the traditional `wp_postmeta` table.

### How much faster is it?

Based on benchmarks:

- Single meta read: 4x faster (2ms → 0.5ms)
- Product listings: 4x faster (200ms → 50ms)
- Category page TTFB: 2.5x faster (400ms → 150ms)

### Is my data safe?

Yes. ShadowORM:

- Keeps original data in `wp_postmeta` during sync
- Falls back to `wp_postmeta` if plugin is deactivated
- Uses prepared statements for all queries
- Never deletes data without explicit rollback command

### Do I need to change my code?

No. ShadowORM intercepts standard WordPress functions:

- `get_post_meta()` - works transparently
- `update_post_meta()` - syncs automatically
- `WP_Query` with `meta_query` - transformed automatically

---

## Compatibility

### Which MySQL versions are supported?

| MySQL Version       | Support Level        |
| ------------------- | -------------------- |
| MySQL 8.0+          | Full (native JSON)   |
| MySQL 5.7           | Full (lookup tables) |
| MariaDB 10.2+       | Full (lookup tables) |
| MySQL 5.6 and below | Not supported        |

### Which WordPress versions are supported?

WordPress 6.0 and higher.

### Does it work with WooCommerce?

Yes! ShadowORM works with:

- Simple and Variable products
- Product attributes
- WooCommerce meta fields (\_price, \_sku, etc.)

Note: Product Variations support requires the Pro version.

### Does it work with ACF (Advanced Custom Fields)?

Yes for basic field types:

- Text, Number, URL, Email
- Select, Radio, Checkbox
- True/False, Date, Time

ACF Repeater and Flexible Content require the Pro version.

### Does it work with page builders?

Tested with:

- Elementor
- Divi (with limitations)
- Beaver Builder

Elementor's `_elementor_data` is stored in the shadow table.

---

## Performance

### Why is WordPress slow with metadata?

WordPress uses the EAV (Entity-Attribute-Value) pattern in `wp_postmeta`:

- Every meta key/value pair is a separate row
- Many JOINs required for complex queries
- Table grows massive with many posts

### How does ShadowORM fix this?

ShadowORM stores all metadata for a post in a single JSON column:

- One row per post (not per meta key)
- One query fetches all metadata
- JSON indexing for fast searches

### When will I see improvement?

Performance gains are most visible with:

- Many meta keys per post (5+)
- Complex meta queries
- Large catalogs (1000+ products)
- Category/archive pages

### Will it slow down writes?

Writes have minimal overhead:

- Sync happens after `save_post`
- Async-safe (doesn't block the response)
- Typically adds <5ms per save

---

## Technical

### What happens during activation?

1. MySQL version is checked
2. `db.php` drop-in is installed
3. MU-plugin loader is created
4. Admin panel is registered

No data is migrated automatically.

### What happens during deactivation?

1. `db.php` drop-in is removed
2. MU-plugin loader is removed
3. Shadow tables are preserved
4. WordPress falls back to `wp_postmeta`

### Can I use it on shared hosting?

Yes, if your host provides:

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.2+
- Composer access (or pre-install dependencies)

### Does it work with object caching?

Yes. ShadowORM's RuntimeCache is separate from WordPress object cache (Redis, Memcached). They work together without conflict.

### Can I query shadow tables directly?

Yes, but it's not recommended:

```php
// Works but not recommended
global $wpdb;
$wpdb->get_results("SELECT * FROM {$wpdb->prefix}app_shadow_product");

// Recommended approach
$repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
$entity = $repository->find($post_id);
```

---

## Migration

### How long does migration take?

Depends on your data:

- 1,000 posts: ~10 seconds
- 10,000 posts: ~2 minutes
- 100,000 posts: ~20 minutes

### Can I migrate in production?

Yes, but consider:

- Running during low-traffic periods
- Using smaller batch sizes
- Monitoring server resources

### What if migration fails?

- Data in `wp_postmeta` is unchanged
- Partially migrated data can be rolled back
- Re-run migration to continue

### Can I exclude certain meta keys?

Not in the Free version. All meta is included except:

- `_edit_lock`
- `_edit_last`

---

## Free vs Pro

### What's in the Free version?

- MySQL 8.0 and Legacy driver support
- WP-CLI commands
- Admin panel
- Runtime caching
- Post, Page, Product support

### What's in the Pro version?

- WooCommerce Variations
- ACF Repeater/Flexible Content
- WPML/Polylang support
- Advanced dashboard
- Visual index builder
- Priority support

### Where can I get Pro?

Visit [gotowebplugins.com/shadow-orm-pro](https://gotowebplugins.com/shadow-orm-pro)

---

## Support

### Where can I get help?

- **Documentation**: This wiki
- **Issues**: [GitHub Issues](https://github.com/gotoweb/shadow-orm/issues)
- **Email**: kontakt@gotoweb.pl
- **Pro Support**: Priority response for Pro customers

### How do I report a bug?

Open a GitHub issue with:

1. WordPress version
2. PHP version
3. MySQL version
4. Error message/logs
5. Steps to reproduce

### Can I contribute?

Yes! See [Contributing](https://github.com/gotoweb/shadow-orm/blob/main/CONTRIBUTING.md) for guidelines.
