# ShadowORM Wiki

Welcome to the **ShadowORM MySQL Accelerator** documentation!

ShadowORM is a high-performance ORM layer for WordPress/WooCommerce that dramatically accelerates meta queries by storing post metadata in optimized "Shadow Tables" with native JSON support.

## Quick Navigation

- [Installation](Installation)
- [Getting Started](Getting-Started)
- [Architecture](Architecture)
- [Configuration](Configuration)
- [WP-CLI Commands](WP-CLI-Commands)
- [How It Works](How-It-Works)
- [API Reference](API-Reference)
- [Troubleshooting](Troubleshooting)
- [FAQ](FAQ)

## Features

| Feature                       | Description                                                     |
| ----------------------------- | --------------------------------------------------------------- |
| **Dual-Driver Strategy**      | Automatic MySQL version detection with optimal driver selection |
| **MySQL 8.0+ Support**        | Native JSON columns with Multi-Valued Indexes                   |
| **MySQL 5.7/MariaDB Support** | Lookup tables with standard JOINs                               |
| **Zero Configuration**        | Works out of the box after activation                           |
| **WP-CLI Integration**        | Full command-line interface for migrations                      |
| **Admin Panel**               | Visual management of shadow tables                              |
| **Transparent Integration**   | Intercepts `get_post_meta()` automatically                      |
| **RAM Cache**                 | In-memory caching for repeated reads                            |

## Requirements

- PHP 8.1+
- WordPress 6.0+
- MySQL 5.7+ or MariaDB 10.2+

## Performance

| Scenario                 | Without ShadowORM | With ShadowORM |
| ------------------------ | ----------------- | -------------- |
| Single meta read         | ~2ms              | <0.5ms         |
| 50 products with filters | ~200ms            | ~50ms          |
| Category page TTFB       | ~400ms            | ~150ms         |

_Results on VPS with 2 vCPU, 4GB RAM, MySQL 8.0_

## Free vs Pro

| Feature                    | Free | Pro |
| -------------------------- | :--: | :-: |
| MySQL 8.0 Native JSON      | Yes  | Yes |
| MySQL 5.7 Lookup Tables    | Yes  | Yes |
| WP-CLI Commands            | Yes  | Yes |
| Admin Panel                | Yes  | Yes |
| RAM Cache                  | Yes  | Yes |
| **WooCommerce Variations** |  No  | Yes |
| **ACF Repeater/Flexible**  |  No  | Yes |
| **WPML/Polylang Support**  |  No  | Yes |
| **Advanced Dashboard**     |  No  | Yes |
| **Visual Index Builder**   |  No  | Yes |

## Support

- **Issues**: [GitHub Issues](https://github.com/gotoweb/shadow-orm/issues)
- **Email**: kontakt@gotoweb.pl
- **Pro Version**: [gotowebplugins.com](https://gotowebplugins.com/shadow-orm-pro)
