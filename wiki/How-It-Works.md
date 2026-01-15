# How It Works

This page explains the internal workings of ShadowORM, including how data flows through the system for reads, writes, and queries.

## Overview

ShadowORM intercepts WordPress core functions to redirect metadata operations from `wp_postmeta` to optimized shadow tables:

```
WordPress Core                    ShadowORM
     │                               │
get_post_meta() ──────────────► ReadInterceptor
update_post_meta() ───────────► WriteInterceptor
WP_Query (meta_query) ────────► QueryInterceptor
```

## Read Flow

When `get_post_meta()` is called, ShadowORM intercepts the request.

### Step-by-Step Process

1. **Hook Triggered**: `get_post_metadata` filter fires
2. **Type Check**: Verify post type is supported (post, page, product)
3. **Cache Check**: Look for entity in RuntimeCache
4. **Not Found Marker**: Check if post was previously marked as not in shadow table
5. **Database Query**: If not cached, fetch entire row from shadow table
6. **Cache Update**: Store entity in RuntimeCache
7. **Return Value**: Extract requested meta key and return

### Code Flow

```php
// User code
$price = get_post_meta($product_id, '_price', true);

// ReadInterceptor::intercept()
// 1. Check if post type is supported
if (!in_array($post->post_type, ['post', 'page', 'product'])) {
    return $value; // Fall back to WordPress
}

// 2. Check RAM cache
$entity = $cache->get($postId);

// 3. If not cached, load from shadow table
if ($entity === null) {
    $entity = $repository->find($postId);
    $cache->set($postId, $entity);
}

// 4. Return the specific meta value
return $entity->getMeta('_price');
```

### Performance Benefit

| Scenario                  | Traditional wp_postmeta | ShadowORM          |
| ------------------------- | ----------------------- | ------------------ |
| Product with 50 meta keys | 50 queries              | 1 query            |
| Repeat read of same post  | 1 query each            | 0 queries (cached) |

## Write Flow

When post data is saved, ShadowORM syncs to the shadow table.

### Hooks Used

| Hook                | Priority | Purpose                  |
| ------------------- | -------- | ------------------------ |
| `save_post`         | 20       | Sync post after save     |
| `updated_post_meta` | 10       | Sync after meta update   |
| `added_post_meta`   | 10       | Sync after meta add      |
| `deleted_post_meta` | 10       | Sync after meta delete   |
| `deleted_post`      | 10       | Remove from shadow table |

### Sync Process

1. **Check Post Type**: Only sync supported types
2. **Skip Drafts**: Ignore auto-drafts and revisions
3. **Fetch Raw Meta**: Read directly from `wp_postmeta` (not shadow table)
4. **Normalize Data**: Unserialize arrays, remove internal keys
5. **Create Entity**: Build new `ShadowEntity`
6. **Save to Repository**: Insert or update shadow table
7. **Update Cache**: Refresh RuntimeCache

### Important: Data Source

During sync, ShadowORM reads from `wp_postmeta` directly (bypassing its own interceptor) to avoid circular dependencies:

```php
// SyncService::syncPost()
$rawMeta = $wpdb->get_results(
    "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
    $postId
);
```

## Query Flow

When `WP_Query` uses `meta_query`, ShadowORM transforms the SQL.

### When Interception Happens

- Post type is supported
- Query contains `meta_query` parameter
- Not in admin area (unless AJAX)
- Shadow table exists for the post type

### Transformation Process

1. **Detect Query**: `posts_clauses` filter fires
2. **Extract Meta Query**: Get `meta_query` from WP_Query
3. **Build Conditions**: Transform to JSON or JOIN syntax
4. **Modify Clauses**: Update WHERE, JOIN clauses
5. **Return Modified SQL**: WordPress executes optimized query

### MySQL 8.0 Example

Original WordPress query:

```sql
SELECT * FROM wp_posts
INNER JOIN wp_postmeta ON post_id = ID
WHERE meta_key = '_price' AND meta_value > 100
```

ShadowORM transformed (MySQL 8.0):

```sql
SELECT * FROM wp_posts
INNER JOIN wp_app_shadow_product ON post_id = ID
WHERE JSON_EXTRACT(meta_data, '$._price') > 100
```

### Legacy Driver Example

For MySQL 5.7/MariaDB, uses lookup table:

```sql
SELECT DISTINCT s.* FROM wp_app_shadow_product AS s
INNER JOIN wp_app_shadow_product_lookup AS l0 ON s.post_id = l0.post_id
WHERE l0.meta_key = '_price' AND l0.meta_value > 100
```

## RuntimeCache

The RuntimeCache provides per-request caching of ShadowEntity objects.

### Features

- **In-Memory Storage**: Array-based cache, no external dependencies
- **Not Found Tracking**: Marks posts that don't exist in shadow table
- **Request Scoped**: Cache is cleared at end of request
- **No TTL**: Lives for duration of PHP request

### Cache States

| State          | Description                   |
| -------------- | ----------------------------- |
| `null`         | Not yet checked               |
| `ShadowEntity` | Loaded and cached             |
| `not_found`    | Confirmed not in shadow table |

## Data Normalization

When syncing, metadata is normalized:

### Process

1. **Unserialize**: `maybe_unserialize()` for PHP serialized data
2. **Skip Internal**: Remove `_edit_lock`, `_edit_last`
3. **Flatten Arrays**: Single values extracted from arrays
4. **Preserve Types**: Arrays remain as JSON arrays

### Example

Input (wp_postmeta):

```
meta_key: _price, meta_value: "99.99"
meta_key: _sku, meta_value: "ABC123"
meta_key: _product_attributes, meta_value: a:2:{...}
```

Output (shadow table JSON):

```json
{
  "_price": "99.99",
  "_sku": "ABC123",
  "_product_attributes": {
    "color": ["red", "blue"],
    "size": ["S", "M", "L"]
  }
}
```
