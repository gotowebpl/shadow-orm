=== ShadowORM MySQL Accelerator ===
Contributors: gotoweb
Donate link: https://gotoweb.pl
Tags: performance, database, woocommerce, optimization, mysql
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.2.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance ORM layer for WordPress/WooCommerce with Shadow Tables. Dramatically accelerates meta queries.

== Description ==

**ShadowORM MySQL Accelerator** dramatically speeds up your WordPress site by storing post metadata in optimized "shadow tables" instead of the traditional `wp_postmeta` table.

= The Problem =

WordPress uses the EAV (Entity-Attribute-Value) pattern in `wp_postmeta`, which means:

* Every meta key/value pair is a separate row
* Many JOINs required for complex queries
* Table grows massive with many posts
* Slow performance on large WooCommerce stores

= The Solution =

ShadowORM stores all metadata for a post in a single JSON column:

* One row per post (not per meta key)
* One query fetches all metadata
* Native JSON indexing for fast searches (MySQL 8.0+)
* Lookup tables for older MySQL versions

= Key Features =

* **Dual-Driver Strategy** - Automatic MySQL version detection
  * MySQL 8.0+: Native JSON columns with Multi-Valued Indexes
  * MySQL 5.7/MariaDB: Lookup tables with standard JOINs
* **Zero Configuration** - Works out of the box after activation
* **WP-CLI Support** - Full command-line interface for migrations
* **Admin Panel** - Visual management of shadow tables
* **Transparent Integration** - Intercepts `get_post_meta()` automatically
* **RAM Cache** - In-memory caching for repeated reads
* **Automatic Updates** - Updates directly from GitHub releases

= Performance Improvements =

| Scenario | Without ShadowORM | With ShadowORM |
|----------|-------------------|----------------|
| Single meta read | ~2ms | <0.5ms |
| 50 products with filters | ~200ms | ~50ms |
| Category page TTFB | ~400ms | ~150ms |

*Results on VPS with 2 vCPU, 4GB RAM, MySQL 8.0*

= Supported Post Types =

* Posts
* Pages
* WooCommerce Products (Simple)

= Pro Version =

The Pro version adds advanced caching and optimization features:

**Caching System:**
* Object Cache - Redis/Memcached integration with Symfony fallback
* Page Cache - Full page caching with tag-based invalidation
* Query Cache - WP_Query results caching for archives and widgets
* Automatic purge when stock changes, order completes, post updates

**WooCommerce Enhancements:**
* Product Variations support
* Stock/Price exclusion from cache (always fresh)
* WooCommerce Optimizer for bulk product loading

**Integrations:**
* ACF Repeater/Flexible Content support
* WPML/Polylang multilingual support

**Admin Features:**
* Virtual Mode - full shadow table mode
* Advanced Dashboard with statistics
* Quick purge options
* Transients management
* Autoload optimization

**Support:**
* Priority email support
* Regular updates

[Get ShadowORM Pro](https://gotowebplugins.com/shadow-orm-pro)

== Installation ==

= Automatic Installation =

1. Go to Plugins → Add New in WordPress Admin
2. Search for "ShadowORM"
3. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin from [GitHub](https://github.com/gotowebpl/shadow-orm)
2. Upload to `wp-content/plugins/shadow-orm/`
3. Run `composer install` in the plugin directory
4. Activate the plugin in WordPress Admin

= Post-Installation =

1. Check status: `wp shadow status`
2. Migrate posts: `wp shadow migrate --all`
3. Navigate to Settings → ShadowORM to manage shadow tables

== Frequently Asked Questions ==

= Is my data safe? =

Yes. ShadowORM keeps original data in `wp_postmeta` during sync. If you deactivate the plugin, WordPress falls back to the original data automatically.

= Do I need to change my code? =

No. ShadowORM intercepts standard WordPress functions like `get_post_meta()` and `update_post_meta()` transparently.

= Which MySQL versions are supported? =

* MySQL 8.0+ (full support with native JSON)
* MySQL 5.7 (full support with lookup tables)
* MariaDB 10.2+ (full support with lookup tables)

= Does it work with WooCommerce? =

Yes! ShadowORM works with WooCommerce products, including attributes, prices, and SKUs. Product Variations support requires the Pro version.

= Does it work with ACF? =

Yes for basic field types. ACF Repeater and Flexible Content require the Pro version.

= How do I migrate my data? =

Use WP-CLI commands:

`wp shadow migrate --type=post`
`wp shadow migrate --type=page`
`wp shadow migrate --type=product`

Or migrate all at once:

`wp shadow migrate --all`

= Can I rollback? =

Yes. Use `wp shadow rollback --type=<post_type>` to remove a shadow table. WordPress will immediately fall back to `wp_postmeta`.

== Screenshots ==

1. Admin panel showing shadow table status
2. WP-CLI migration in progress
3. Performance comparison dashboard

== Changelog ==

= 1.0.0 =
* Initial release
* Dual-Driver Strategy (MySQL 8.0 + Legacy 5.7)
* Domain Layer: ShadowEntity, SchemaDefinition, interfaces
* Infrastructure Layer: MySQL8Driver, LegacyDriver, DriverFactory
* Application Layer: SyncService, QueryService, RuntimeCache
* Presentation Layer: WP-CLI commands, Admin panel, REST API
* ReadInterceptor for get_post_meta() interception
* WriteInterceptor for save_post synchronization
* QueryInterceptor for posts_clauses modification
* 78 unit tests with 137 assertions
* GitHub auto-updater integration

== Upgrade Notice ==

= 1.0.0 =
Initial release. Run `wp shadow migrate --all` after activation to start using shadow tables.

== WP-CLI Commands ==

* `wp shadow status` - Show migration status for all types
* `wp shadow migrate --type=<type>` - Migrate specific post type
* `wp shadow migrate --all` - Migrate all configured types
* `wp shadow migrate --dry-run` - Preview without changes
* `wp shadow rollback --type=<type>` - Remove shadow table
* `wp shadow benchmark --type=<type>` - Performance benchmark

== Additional Information ==

= Requirements =

* PHP 8.1 or higher
* WordPress 6.0 or higher
* MySQL 5.7+ or MariaDB 10.2+

= Documentation =

Full documentation available at [GitHub Wiki](https://github.com/gotowebpl/shadow-orm/wiki)

= Support =

* GitHub Issues: [github.com/gotowebpl/shadow-orm/issues](https://github.com/gotowebpl/shadow-orm/issues)
* Email: kontakt@gotoweb.pl

= Contributing =

Contributions are welcome! See our [GitHub repository](https://github.com/gotowebpl/shadow-orm) for guidelines.

== Credits ==

Developed by [gotoweb.pl](https://gotoweb.pl) - WordPress & WooCommerce Experts
