# Changelog

All notable changes to ShadowORM MySQL Accelerator will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.3] - 2026-01-15

### Added

- **Async Write** - eliminate "double write" performance penalty
  - Use Action Scheduler (WooCommerce standard) for background sync
  - Fallback to shutdown hook when Action Scheduler not available
  - Debounce: prevent multiple syncs per post per request
- **Integrity Check** - detect wp_postmeta vs shadow table mismatches
  - Random sample verification (100 posts by default)
  - REST API endpoints: `/integrity/check`, `/integrity/status`
  - Repair functionality for mismatched data
- **Admin Panel Support** - work in wp-admin, not just frontend
  - Enable QueryInterceptor and PostQueryPreloader in admin
  - Skip only on single post edit screens (need fresh data there)
  - Accelerate WooCommerce order/product list filtering

### Performance

- **ReadInterceptor optimization** - eliminate 10% overhead on single meta reads
  - Cache `post_type` lookups in static array (avoid repeated `get_post_type()` calls)
  - Add `$skipCache` for unsupported posts (fast early-return)
  - Pre-cache post types during preload operations
- **SupportedTypes optimization** - O(1) lookup instead of O(n)
  - Replace `in_array()` with `isset()` using hash map structure
- **RuntimeCache with wp*cache*\* support** - persistent cache across requests
  - Auto-detect external object cache (Redis/Memcached) via `wp_using_ext_object_cache()`
  - Use `wp_cache_set/get` when available for data persistence between requests
  - In-memory cache still used as first-level cache for current request

## [1.2.2] - 2026-01-15

### Fixed

- Remove hidden files (.distignore, phpcs.xml.dist) not permitted by WordPress.org
- Fix Text Domain to match WordPress.org slug (shadoworm-mysql-accelerator)

## [1.2.1] - 2026-01-15

### Fixed

- **WordPress.org Plugin Check compliance**
  - Add `ABSPATH` check to all PHP files in `src/`
  - Use `WP_Filesystem` API in `DropInInstaller` instead of direct PHP calls
  - Add output escaping (`esc_html`) for exception messages
  - Replace `.gitattributes` with `.distignore` for release exclusions
  - Exclude `tests/`, `.github/`, `phpunit.xml.dist` from distribution packages

## [1.2.0] - 2026-01-15

### Added

- **Batch Migration**: Chunked processing for large sites (100 posts/batch)
  - New REST API endpoints: `sync/start`, `sync/batch`, `sync/progress`
  - Real-time progress bar in admin panel
  - No more 504 timeout errors on large installations
- **Tabbed Admin Panel**: New UI with Dashboard, Post Types, Settings tabs
  - Extensible via `shadow_orm_admin_tabs` filter
  - Prepared for Pro version extensions

### Changed

- Migration now uses asynchronous batch processing instead of synchronous
- WP tested up to: 6.9

### Fixed

- Progress tracking during synchronization

## [1.1.0] - 2026-01-15

### Changed

- **Architecture refactoring**: Improved DDD layer separation
  - Moved `SupportedTypes` from Presentation to Domain layer
  - Created `PostMetaReaderInterface` for wp_posts/wp_postmeta access isolation
  - Extended `StorageDriverInterface` with `findMany()` and `exists()` methods
  - Removed direct `$wpdb` usage from `ShadowRepository` and `SyncService`
- `SyncService` now uses dependency injection for `PostMetaReaderInterface`
- `rollback()` method now properly deletes data from shadow tables

### Fixed

- Fix: Corrected template path in `AdminPage.php` (`dirname(__DIR__, 3)` → `dirname(__DIR__, 4)`)
- Fix: Corrected asset URL path in `AdminPage.php` for admin.css/admin.js
- Fix: `WriteInterceptor::onMetaDeleted()` now accepts `array|int` for `deleted_post_meta` hook compatibility
- Fix: REST API routes now registered globally (not just in admin context)
- Fix: Added Save Settings button with handler in admin panel
- Fix: Sanitize table names - replace hyphens with underscores for SQL compatibility
- Fix: Clean up orphaned shadow records during sync (records from deleted posts)
- Fix: Null safety for `relation` parameter in `MySQL8Driver::buildMetaQueryWhere()`

## [1.0.0] - 2026-01-15

### Added

- Initial release of ShadowORM MySQL Accelerator
- **Dual-Driver Strategy**
  - MySQL8Driver with native JSON and JSON_EXTRACT functions
  - LegacyDriver with lookup tables for MySQL 5.7/MariaDB
- **Domain Layer**
  - ShadowEntity immutable entity class
  - SchemaDefinition value object
  - StorageDriverInterface and ShadowRepositoryInterface contracts
- **Infrastructure Layer**
  - DriverFactory with automatic MySQL version detection
  - ShadowTableManager for table creation and migrations
  - ShadowRepository implementation
  - DropInInstaller for db.php and MU-plugin management
- **Application Layer**
  - SyncService for post synchronization
  - QueryService for meta_query transformation
  - AutoDiscoveryService for post type detection
  - RuntimeCache for in-memory caching
- **Presentation Layer**
  - WP-CLI commands: migrate, status, rollback, benchmark
  - ReadInterceptor for get_post_meta() interception
  - WriteInterceptor for save_post synchronization
  - QueryInterceptor for posts_clauses modification
  - Admin panel with REST API
- **Testing**
  - 78 unit tests with 137 assertions
  - PHPUnit 10.x configuration
  - Mockery and Brain Monkey integration

### Security

- All database queries use prepared statements
- REST API endpoints require `manage_options` capability

## [0.1.0] - 2026-01-10

### Added

- Project initialization
- Basic architecture design
- DDD structure implementation

---

**Full Changelog**: https://github.com/gotoweb/shadow-orm/commits/v1.0.0
