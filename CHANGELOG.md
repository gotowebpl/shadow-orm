# Changelog

All notable changes to ShadowORM MySQL Accelerator will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
