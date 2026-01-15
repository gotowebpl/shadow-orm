# WP-CLI Commands

ShadowORM provides a comprehensive set of WP-CLI commands for managing shadow tables.

## Command Overview

| Command               | Description                         |
| --------------------- | ----------------------------------- |
| `wp shadow status`    | Show migration status for all types |
| `wp shadow migrate`   | Migrate posts to shadow tables      |
| `wp shadow rollback`  | Remove shadow table for a type      |
| `wp shadow benchmark` | Run performance benchmark           |

## wp shadow status

Shows the current state of shadow tables.

### Usage

```bash
wp shadow status
```

### Output

```
+------------+-------+----------+--------+--------+
| Post Type  | Total | Migrated | Size   | Driver |
+------------+-------+----------+--------+--------+
| post       | 150   | 150      | 1.2 MB | MySQL8 |
| page       | 25    | 25       | 0.3 MB | MySQL8 |
| product    | 500   | 500      | 5.6 MB | MySQL8 |
+------------+-------+----------+--------+--------+
```

### Columns Explained

| Column    | Description                     |
| --------- | ------------------------------- |
| Post Type | WordPress post type             |
| Total     | Number of published posts       |
| Migrated  | Number of posts in shadow table |
| Size      | Disk space used by shadow table |
| Driver    | MySQL8 or Legacy                |

## wp shadow migrate

Migrates posts to shadow tables.

### Options

| Option          | Description                 | Default                 |
| --------------- | --------------------------- | ----------------------- |
| `--type=<type>` | Post type to migrate        | Required (unless --all) |
| `--all`         | Migrate all supported types | false                   |
| `--batch=<num>` | Batch size for migration    | 500                     |
| `--dry-run`     | Preview without changes     | false                   |

### Examples

```bash
# Migrate specific post type
wp shadow migrate --type=post

# Migrate all supported types
wp shadow migrate --all

# Custom batch size (for large sites)
wp shadow migrate --type=product --batch=1000

# Preview what would happen
wp shadow migrate --all --dry-run
```

### Sample Output

```bash
$ wp shadow migrate --type=product
Migrating product  100% [████████████████████████████████] 500/500  2.3s
Success: Migrated 500 posts of type 'product'
```

### Batch Size Recommendations

| Site Size | Posts          | Recommended Batch |
| --------- | -------------- | ----------------- |
| Small     | < 1,000        | 500 (default)     |
| Medium    | 1,000 - 10,000 | 1,000             |
| Large     | > 10,000       | 2,000             |

## wp shadow rollback

Removes shadow table for a post type.

### Options

| Option          | Description           | Required |
| --------------- | --------------------- | -------- |
| `--type=<type>` | Post type to rollback | Yes      |

### Example

```bash
$ wp shadow rollback --type=product
This will delete shadow table for 'product'. Continue? [y/n] y
Success: Shadow table for 'product' dropped
```

### Warning

- Rollback deletes all data from the shadow table
- WordPress will fall back to `wp_postmeta` for reads
- Re-migration is required to use shadow tables again

## wp shadow benchmark

Runs a simple performance benchmark.

### Options

| Option               | Description            | Default |
| -------------------- | ---------------------- | ------- |
| `--type=<type>`      | Post type to benchmark | post    |
| `--iterations=<num>` | Number of iterations   | 10      |

### Example

```bash
$ wp shadow benchmark --type=product --iterations=100
Benchmark results for 100 iterations:
  Average: 0.342 ms
  Min: 0.218 ms
  Max: 0.567 ms
```

### Interpreting Results

| Metric  | Description     |
| ------- | --------------- |
| Average | Mean query time |
| Min     | Fastest query   |
| Max     | Slowest query   |

### Expected Results

| Driver | Average Time |
| ------ | ------------ |
| MySQL8 | < 0.5ms      |
| Legacy | < 1.0ms      |

## Error Handling

### Common Errors

| Error                     | Cause              | Solution                  |
| ------------------------- | ------------------ | ------------------------- |
| "Specify --type or --all" | Missing argument   | Add --type=post or --all  |
| "No posts found"          | Empty shadow table | Run migrate first         |
| "Table does not exist"    | Not migrated       | Run migrate for this type |

### Example Error

```bash
$ wp shadow migrate
Error: Specify --type=<post_type> or --all
```

## Scripting Examples

### Automated Migration

```bash
#!/bin/bash
# migrate-all.sh

for type in post page product; do
    wp shadow migrate --type=$type --batch=1000
done

wp shadow status
```

### Health Check

```bash
#!/bin/bash
# Check if all posts are migrated

wp shadow status | grep -q " 0 " && echo "Warning: Some types not migrated"
```

### Pre-Deployment

```bash
#!/bin/bash
# Pre-deployment script

# Run dry-run first
wp shadow migrate --all --dry-run

# If okay, run actual migration
wp shadow migrate --all --batch=500
```
