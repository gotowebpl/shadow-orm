# Installation

## Requirements

Before installing ShadowORM, ensure your server meets these requirements:

- **PHP**: 8.1 or higher
- **WordPress**: 6.0 or higher
- **MySQL**: 5.7+ or MariaDB 10.2+

## Installation Methods

### Via Composer (Recommended)

```bash
cd wp-content/plugins
git clone https://github.com/gotoweb/shadow-orm.git
cd shadow-orm
composer install
```

### Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/gotoweb/shadow-orm/releases)
2. Extract and upload to `wp-content/plugins/shadow-orm/`
3. Run `composer install` in the plugin directory
4. Activate the plugin in WordPress Admin

### Via WP-CLI

```bash
wp plugin install https://github.com/gotoweb/shadow-orm/archive/refs/heads/main.zip --activate
cd wp-content/plugins/shadow-orm
composer install
```

## Post-Installation

### 1. Verify Installation

```bash
wp shadow status
```

This command shows the migration status for all supported post types.

### 2. Initial Migration

Migrate existing posts to shadow tables:

```bash
# Migrate specific post type
wp shadow migrate --type=post

# Migrate all supported types
wp shadow migrate --all

# Preview migration (no changes)
wp shadow migrate --all --dry-run
```

### 3. Configure Settings

Navigate to **Settings → ShadowORM** in WordPress Admin to:

- Enable/disable the system
- Toggle shadow tables per post type
- View migration status

## What Gets Installed

During activation, ShadowORM installs:

1. **db.php Drop-in**: Located in `wp-content/db.php` - provides early database interception
2. **MU-Plugin Loader**: Ensures ShadowORM loads before other plugins
3. **Shadow Tables**: Created during migration for each enabled post type

## Uninstallation

When you deactivate ShadowORM:

- The `db.php` drop-in is removed
- The MU-plugin loader is removed
- **Shadow tables are preserved** (manual cleanup required via WP-CLI)

To completely remove shadow tables:

```bash
wp shadow rollback --type=post
wp shadow rollback --type=page
wp shadow rollback --type=product
```

## Troubleshooting Installation

### Permission Issues

Ensure write permissions for:

- `wp-content/` (for db.php drop-in)
- `wp-content/mu-plugins/` (for MU-plugin loader)

### MySQL Version Warning

If you see a MySQL version warning, ShadowORM will still work but will use the Legacy driver (lookup tables) instead of native JSON functions.

### Composer Issues

If `composer install` fails:

```bash
composer clear-cache
composer install --no-dev
```

## Next Steps

- [Getting Started](Getting-Started) - Learn basic usage
- [Configuration](Configuration) - Customize settings
- [WP-CLI Commands](WP-CLI-Commands) - Command reference
