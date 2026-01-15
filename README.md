# ShadowORM MySQL Accelerator

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

**High-performance ORM layer for WordPress/WooCommerce with Shadow Tables**

ShadowORM dramatically accelerates meta queries by storing post metadata in optimized "shadow tables" with native JSON support (MySQL 8.0+) or lookup tables (MySQL 5.7/MariaDB).

## Features

- **Dual-Driver Strategy** - Automatic detection of MySQL version
  - MySQL 8.0+: Native JSON columns with Multi-Valued Indexes
  - MySQL 5.7/MariaDB: Lookup tables with standard JOINs
- **Zero Configuration** - Works out of the box after activation
- **WP-CLI Support** - Full command-line interface for migrations
- **Admin Panel** - Visual management of shadow tables
- **Transparent Integration** - Intercepts `get_post_meta()` automatically
- **RAM Cache** - In-memory caching for repeat reads

## Requirements

- PHP 8.1+
- WordPress 6.0+
- MySQL 5.7+ or MariaDB 10.2+

## Installation

### Via Composer

```bash
cd wp-content/plugins
git clone https://github.com/gotoweb/shadow-orm.git
cd shadow-orm
composer install
```

### Manual

1. Download the latest release from [Releases](https://github.com/gotoweb/shadow-orm/releases)
2. Upload to `wp-content/plugins/shadow-orm/`
3. Run `composer install`
4. Activate in WordPress Admin

## Quick Start

```bash
# Check status
wp shadow status

# Migrate posts
wp shadow migrate --type=post

# Migrate all supported types
wp shadow migrate --all

# Benchmark performance
wp shadow benchmark --type=product --iterations=10
```

## Admin Panel

Navigate to **Settings → ShadowORM** to:

- Enable/disable the system
- Toggle shadow tables per post type
- View migration status
- Sync or rollback tables

## Free vs Pro

| Feature                    | Free | Pro |
| -------------------------- | :--: | :-: |
| MySQL 8.0 Native JSON      |  ✅  | ✅  |
| MySQL 5.7 Lookup Tables    |  ✅  | ✅  |
| WP-CLI Commands            |  ✅  | ✅  |
| Admin Panel                |  ✅  | ✅  |
| RAM Cache                  |  ✅  | ✅  |
| **WooCommerce Variations** |  ❌  | ✅  |
| **ACF Repeater/Flexible**  |  ❌  | ✅  |
| **WPML/Polylang Support**  |  ❌  | ✅  |
| **Advanced Dashboard**     |  ❌  | ✅  |
| **Visual Index Builder**   |  ❌  | ✅  |
| **Priority Support**       |  ❌  | ✅  |

**[Get ShadowORM Pro →](https://gotowebplugins.com/shadow-orm-pro)**

## WP-CLI Commands

| Command                             | Description                         |
| ----------------------------------- | ----------------------------------- |
| `wp shadow status`                  | Show migration status for all types |
| `wp shadow migrate --type=<type>`   | Migrate specific post type          |
| `wp shadow migrate --all`           | Migrate all configured types        |
| `wp shadow migrate --dry-run`       | Preview without changes             |
| `wp shadow rollback --type=<type>`  | Remove shadow table                 |
| `wp shadow benchmark --type=<type>` | Performance benchmark               |

## How It Works

1. **Activation**: Plugin installs `db.php` drop-in and MU-plugin loader
2. **Migration**: `wp shadow migrate` copies meta to shadow tables
3. **Read Interception**: `get_post_meta()` reads from shadow tables first
4. **Write Sync**: `save_post` hook keeps shadow tables updated
5. **Query Optimization**: `meta_query` uses JSON functions or lookup JOINs

## Performance

| Scenario                 | Without ShadowORM | With ShadowORM |
| ------------------------ | ----------------- | -------------- |
| Single meta read         | ~2ms              | <0.5ms         |
| 50 products with filters | ~200ms            | ~50ms          |
| Category page TTFB       | ~400ms            | ~150ms         |

_Results on VPS with 2 vCPU, 4GB RAM, MySQL 8.0_

## Development

```bash
# Run tests
./vendor/bin/phpunit --testsuite=unit

# Static analysis
./vendor/bin/phpstan analyse

# Code style
./vendor/bin/phpcs src/
```

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing`)
5. Open Pull Request

## Support

- **Documentation**: [GitHub Wiki](https://github.com/gotoweb/shadow-orm/wiki)
- **Issues**: [GitHub Issues](https://github.com/gotoweb/shadow-orm/issues)
- **Email**: kontakt@gotoweb.pl

## Author

**[gotoweb.pl](https://gotoweb.pl)** - WordPress & WooCommerce Experts

- Website: https://gotoweb.pl
- Email: kontakt@gotoweb.pl
- Pro Version: https://gotowebplugins.com/shadow-orm-pro

## License

This project is licensed under the GPL-2.0-or-later License - see the [LICENSE](LICENSE) file for details.

---

Made with ❤️ by [gotoweb.pl](https://gotoweb.pl)
