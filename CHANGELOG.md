# Changelog

All notable changes to `laravel-smart-migrations` will be documented in this file.

## [1.0.0] - 2025-12-28

### Added
- Initial release of Laravel Smart Migrations package
- `migrate:rollback-table` command to rollback migrations by table name
- `migrate:rollback-model` command to rollback migrations by Eloquent model
- `migrate:rollback-batch` command to rollback migrations by batch number
- `migrate:list-table-migrations` command to list all migrations for a table
- `migrate:list-model-migrations` command to list all migrations for a model
- Support for single and multiple table/model rollbacks with comma or space-separated input
- Short flags support: `-L` (latest), `-O` (oldest), `-A` (all), `-B` (batch), `-F` (force), `-I` (interactive)
- Long flags support: `--latest`, `--oldest`, `--all`, `--batch`, `--force`, `--interactive`
- Safe rollback with proper table drop execution via migration's `down()` method
- Configuration options for model namespace, confirmation requirements, and audit logging
- Migration finder and parser services for intelligent migration management
- Comprehensive error handling and validation before rollback
- Batch detection and warning for multi-batch rollbacks
- Confirmation prompts to prevent accidental data loss
- Audit logging capability for tracking rollback operations
- Programmatic API via SmartMigrations facade

### Features
- Intelligently detect and group migrations by table name
- Prevent orphaned database tables after rollback
- Handle both anonymous and named migration classes
- Support for custom model namespaces
- Detailed migration information display
- Multi-batch rollback prevention (configurable)
- Flexible input parsing (comma-separated, space-separated, or single values)

### Documentation
- Comprehensive README with all command examples
- Installation and configuration instructions
- Vendor publish commands documentation
- Programmatic API usage examples
- Command options reference table
